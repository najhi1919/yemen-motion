<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksVisibilityActionRequest;
use App\Models\Work;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class WorksVisibilityActionController extends Controller
{
    /** @var array<string, string> */
    private const AUDIT_EVENT_TYPES = [
        'publish' => 'works.visibility.published',
        'unpublish' => 'works.visibility.unpublished',
        'hide' => 'works.visibility.hidden',
        'restore' => 'works.visibility.restored',
        'feature' => 'works.visibility.featured',
        'unfeature' => 'works.visibility.unfeatured',
        'pin' => 'works.visibility.pinned',
        'unpin' => 'works.visibility.unpinned',
    ];

    public function __construct(private readonly AuditEventLogger $auditEventLogger) {}

    public function publish(WorksVisibilityActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'publish');
    }

    public function unpublish(WorksVisibilityActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'unpublish');
    }

    public function hide(WorksVisibilityActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'hide');
    }

    public function restore(WorksVisibilityActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'restore');
    }

    public function feature(WorksVisibilityActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'feature');
    }

    public function unfeature(WorksVisibilityActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'unfeature');
    }

    public function pin(WorksVisibilityActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'pin');
    }

    public function unpin(WorksVisibilityActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'unpin');
    }

    private function execute(
        WorksVisibilityActionRequest $request,
        Work $work,
        string $action,
    ): JsonResponse {
        $result = DB::transaction(function () use ($request, $work, $action): array {
            $currentWork = Work::query()
                ->whereKey($work->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $oldState = $this->visibilityState($currentWork);
            $changes = $this->changesFor($currentWork, $action);
            $changed = $changes !== [];

            if ($changed) {
                $currentWork->fill($changes);
                $currentWork->save();
                $currentWork->refresh();

                $this->recordAuditEvent($request, $currentWork, $action, $oldState);
            }

            return [
                'changed' => $changed,
                'work' => $currentWork,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'action' => $action,
                'changed' => $result['changed'],
                'work' => $this->workPayload($result['work']),
            ],
            'message' => 'تم تنفيذ إجراء الظهور بنجاح',
            'errors' => null,
        ]);
    }

    /** @return array<string, mixed> */
    private function changesFor(Work $work, string $action): array
    {
        return match ($action) {
            'publish' => $this->publishChanges($work),
            'unpublish' => $this->unpublishChanges($work),
            'hide' => $this->hideChanges($work),
            'restore' => $this->restoreChanges($work),
            'feature' => $this->featureChanges($work),
            'unfeature' => $work->is_featured ? ['is_featured' => false] : [],
            'pin' => $this->pinChanges($work),
            'unpin' => $work->is_pinned ? ['is_pinned' => false] : [],
            default => throw new HttpException(422, 'إجراء الظهور المطلوب غير مدعوم.'),
        };
    }

    /** @return array<string, mixed> */
    private function publishChanges(Work $work): array
    {
        if (! in_array($work->status, [Work::STATUS_APPROVED, Work::STATUS_HIDDEN, Work::STATUS_PUBLISHED], true)) {
            throw new HttpException(422, 'لا يمكن نشر العمل من حالته الحالية.');
        }

        if ($work->status === Work::STATUS_PUBLISHED && $work->visibility_status === Work::VISIBILITY_PUBLIC) {
            return [];
        }

        return [
            'status' => Work::STATUS_PUBLISHED,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'published_at' => $work->published_at ?? now(),
        ];
    }

    /** @return array<string, mixed> */
    private function unpublishChanges(Work $work): array
    {
        if (! in_array($work->status, [Work::STATUS_PUBLISHED, Work::STATUS_APPROVED], true)) {
            throw new HttpException(422, 'لا يمكن إلغاء نشر العمل من حالته الحالية.');
        }

        if ($work->status === Work::STATUS_APPROVED && $work->visibility_status === Work::VISIBILITY_HIDDEN) {
            return [];
        }

        return [
            'status' => Work::STATUS_APPROVED,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'hidden_at' => now(),
        ];
    }

    /** @return array<string, mixed> */
    private function hideChanges(Work $work): array
    {
        if ($work->status === Work::STATUS_ARCHIVED) {
            throw new HttpException(422, 'لا يمكن إخفاء عمل مؤرشف.');
        }

        if ($work->status === Work::STATUS_HIDDEN && $work->visibility_status === Work::VISIBILITY_HIDDEN) {
            return [];
        }

        return [
            'status' => Work::STATUS_HIDDEN,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'hidden_at' => now(),
        ];
    }

    /** @return array<string, mixed> */
    private function restoreChanges(Work $work): array
    {
        if ($work->status === Work::STATUS_ARCHIVED) {
            throw new HttpException(422, 'لا يمكن استعادة ظهور عمل مؤرشف.');
        }

        if ($work->status === Work::STATUS_PUBLISHED && $work->visibility_status === Work::VISIBILITY_PUBLIC) {
            return [];
        }

        $canRestore = $work->status === Work::STATUS_HIDDEN
            || ($work->visibility_status === Work::VISIBILITY_HIDDEN
                && in_array($work->status, [Work::STATUS_APPROVED, Work::STATUS_PUBLISHED], true));

        if (! $canRestore) {
            throw new HttpException(422, 'لا يمكن استعادة ظهور العمل من حالته الحالية.');
        }

        return [
            'status' => Work::STATUS_PUBLISHED,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'published_at' => $work->published_at ?? now(),
        ];
    }

    /** @return array<string, bool> */
    private function featureChanges(Work $work): array
    {
        $this->ensurePublishedAndPublic($work, 'تمييز');

        return $work->is_featured ? [] : ['is_featured' => true];
    }

    /** @return array<string, bool> */
    private function pinChanges(Work $work): array
    {
        $this->ensurePublishedAndPublic($work, 'تثبيت');

        return $work->is_pinned ? [] : ['is_pinned' => true];
    }

    private function ensurePublishedAndPublic(Work $work, string $actionLabel): void
    {
        if ($work->status !== Work::STATUS_PUBLISHED || $work->visibility_status !== Work::VISIBILITY_PUBLIC) {
            throw new HttpException(422, "لا يمكن {$actionLabel} العمل ما لم يكن منشورًا وعامًا.");
        }
    }

    /** @return array<string, mixed> */
    private function workPayload(Work $work): array
    {
        $isPublic = $work->visibility_status === Work::VISIBILITY_PUBLIC;
        $isHidden = $work->visibility_status === Work::VISIBILITY_HIDDEN || $work->status === Work::STATUS_HIDDEN;

        return [
            'id' => $work->id,
            'title' => $work->title,
            'slug' => $work->slug,
            'summary' => $work->summary,
            'status' => $work->status,
            'visibility_status' => $work->visibility_status,
            'media_type' => $work->media_type,
            'category_id' => $work->category_id,
            'is_featured' => (bool) $work->is_featured,
            'is_pinned' => (bool) $work->is_pinned,
            'reports_count' => (int) $work->reports_count,
            'views_count' => (int) $work->views_count,
            'likes_count' => (int) $work->likes_count,
            'published_at' => $work->published_at?->toISOString(),
            'hidden_at' => $work->hidden_at?->toISOString(),
            'updated_at' => $work->updated_at?->toISOString(),
            'created_at' => $work->created_at?->toISOString(),
            'visibility_flags' => [
                'is_public' => $isPublic,
                'is_hidden' => $isHidden,
                'is_promoted' => (bool) ($work->is_featured || $work->is_pinned),
                'has_reports' => (int) $work->reports_count > 0,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function visibilityState(Work $work): array
    {
        return [
            'status' => $work->status,
            'visibility_status' => $work->visibility_status,
            'is_featured' => (bool) $work->is_featured,
            'is_pinned' => (bool) $work->is_pinned,
        ];
    }

    /** @param array<string, mixed> $oldState */
    private function recordAuditEvent(
        WorksVisibilityActionRequest $request,
        Work $work,
        string $action,
        array $oldState,
    ): void {
        $actor = $request->user();

        try {
            $this->auditEventLogger->record([
                'event_type' => self::AUDIT_EVENT_TYPES[$action],
                'category' => 'works',
                'severity' => 'notice',
                'actor_type' => $actor ? 'user' : 'system',
                'actor_id' => $actor?->getKey(),
                'actor_role' => $actor?->roles->first()?->name,
                'target_type' => 'work',
                'target_id' => $work->getKey(),
                'action' => $action,
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'work_id' => $work->getKey(),
                    'action' => $action,
                    'old_status' => $oldState['status'],
                    'new_status' => $work->status,
                    'old_visibility_status' => $oldState['visibility_status'],
                    'new_visibility_status' => $work->visibility_status,
                    'old_is_featured' => $oldState['is_featured'],
                    'new_is_featured' => (bool) $work->is_featured,
                    'old_is_pinned' => $oldState['is_pinned'],
                    'new_is_pinned' => (bool) $work->is_pinned,
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            if (app()->environment('testing')) {
                throw $exception;
            }
        }
    }
}
