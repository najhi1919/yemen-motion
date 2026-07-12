<?php

namespace App\Http\Controllers\Api\Audit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\StorePageViewAuditRequest;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Http\JsonResponse;
use Throwable;

class PageViewAuditController extends Controller
{
    public function __construct(
        private readonly AuditEventLogger $auditEventLogger,
    ) {}

    public function __invoke(StorePageViewAuditRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $metadata = [
            'page_key' => $validated['page_key'],
            'path' => $validated['path'],
            'source' => 'admin_page_view_tracking',
        ];

        if (filled($validated['section'] ?? null)) {
            $metadata['section'] = $validated['section'];
        }

        // نسجل زيارة الصفحة بعد اكتمال التفويض والتحقق من المسار والحقول المسموحة.
        // لا نمرر الطلب أو payload كاملًا، ولا نحفظ query string أو referrer.
        $this->recordPageViewAuditEvent($request, $metadata);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'تم تسجيل زيارة الصفحة بنجاح',
            'errors' => null,
        ], 201);
    }

    /**
     * يسجل زيارة صفحة الإدارة بسياق محدود وآمن.
     *
     * @param array<string, string> $metadata
     */
    private function recordPageViewAuditEvent(
        StorePageViewAuditRequest $request,
        array $metadata,
    ): void {
        $actor = $request->user();

        try {
            $this->auditEventLogger->record([
                'event_type' => 'admin.page.viewed',
                'category' => 'page_view',
                'severity' => 'info',
                'actor_type' => 'user',
                'actor_id' => $actor?->id,
                'actor_role' => $actor?->roles->first()?->name,
                'target_type' => 'page',
                'target_id' => null,
                'action' => 'view',
                'outcome' => 'success',
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
