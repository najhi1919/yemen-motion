<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Audit\AuditEventLogger;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RecordAccessDeniedAuditEvent
{
    private const RECORDED_ATTRIBUTE = 'audit_access_denied_recorded';

    public function __construct(
        private readonly AuditEventLogger $auditEventLogger,
    ) {}

    /**
     * يلتقط رفض الوصول للمسارات الداخلية دون تغيير الاستجابة أو الاستثناء الأصلي.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isTrackedInternalPath($request)) {
            return $next($request);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $status = $this->deniedStatus($exception);

            if ($status !== null) {
                $this->recordDeniedEvent($request, $status);
            }

            throw $exception;
        }

        if (in_array($response->getStatusCode(), [401, 403], true)) {
            $this->recordDeniedEvent($request, $response->getStatusCode());
        }

        return $response;
    }

    private function isTrackedInternalPath(Request $request): bool
    {
        return $request->is(
            'api/admin',
            'api/admin/*',
            'api/dashboard',
            'api/dashboard/*',
            'api/audit',
            'api/audit/*',
            'api/user',
            'api/auth/logout',
        );
    }

    private function deniedStatus(Throwable $exception): ?int
    {
        if ($exception instanceof AuthenticationException) {
            return 401;
        }

        if ($exception instanceof AuthorizationException) {
            $status = $exception->hasStatus() ? $exception->status() : 403;

            return in_array($status, [401, 403], true) ? $status : null;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return in_array($status, [401, 403], true) ? $status : null;
        }

        return null;
    }

    private function recordDeniedEvent(Request $request, int $status): void
    {
        if ($request->attributes->getBoolean(self::RECORDED_ATTRIBUTE)) {
            return;
        }

        // نضع العلامة قبل الحفظ حتى لا يتكرر الحدث إذا مر الرفض عبر أكثر من مسار معالجة.
        $request->attributes->set(self::RECORDED_ATTRIBUTE, true);

        try {
            $actor = $request->user();
            $actorRole = $actor instanceof User
                ? $actor->roles->first()?->name
                : null;
            $routeName = $request->route()?->getName();
            $metadata = [
                'method' => $request->method(),
                'path' => $request->getPathInfo(),
                'status' => $status,
                'source' => 'access_denied_tracking',
            ];

            if (is_string($routeName) && $routeName !== '') {
                $metadata['route_name'] = $routeName;
            }

            // لا نمرر query أو URL كاملًا أو headers أو request أو payload إلى سجل الرفض.
            $this->auditEventLogger->record([
                'event_type' => 'access.denied',
                'category' => 'access_control',
                'severity' => 'warning',
                'actor_type' => $actor ? 'user' : 'guest',
                'actor_id' => $actor?->getAuthIdentifier(),
                'actor_role' => $actorRole,
                'target_type' => 'route',
                'target_id' => null,
                'action' => 'access_denied',
                'outcome' => 'denied',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-ID'),
                'correlation_id' => $request->header('X-Correlation-ID'),
                'metadata' => $metadata,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            // في الاختبارات نعيد الخطأ حتى لا تمر عيوب audit أو مخطط البيانات بصمت.
            if (app()->environment('testing')) {
                throw $exception;
            }
        }
    }
}
