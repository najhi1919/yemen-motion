<?php

declare(strict_types=1);

namespace App\Services\Works;

use App\Models\User;
use App\Models\Work;
use App\Services\Audit\AuditEventLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class DesignerWorksArchiveService
{
    public function __construct(private readonly AuditEventLogger $auditEventLogger) {}

    /**
     * @param array<string, string|null> $requestContext
     * @return array<string, mixed>
     */
    public function archive(
        int $workId,
        User $actor,
        string $expectedUpdatedAt,
        array $requestContext,
    ): array {
        return DB::transaction(function () use ($workId, $actor, $expectedUpdatedAt, $requestContext): array {
            $work = $this->ownedLockedWork($workId, $actor);
            $this->ensureCurrentVersion($work, $expectedUpdatedAt);
            $previousStatus = (string) $work->status;

            if ($work->status === Work::STATUS_ARCHIVED) {
                $work->load(['coverMedia', 'category', 'tags']);

                return $this->result(false, 'archive', $previousStatus, $work);
            }

            if (! $work->canBeArchivedByDesigner()) {
                $this->conflict(
                    'لا يمكن أرشفة العمل في حالته الحالية.',
                    'work_state_not_archivable',
                    $work,
                    false,
                );
            }

            $previousVisibility = (string) $work->visibility_status;
            $featuredRemoved = (bool) $work->is_featured;
            $pinnedRemoved = (bool) $work->is_pinned;

            $work->forceFill([
                'archived_from_status' => $previousStatus,
                'archived_from_visibility_status' => $previousVisibility,
                'status' => Work::STATUS_ARCHIVED,
                'visibility_status' => Work::VISIBILITY_HIDDEN,
                'archived_at' => now(),
                'is_featured' => false,
                'is_pinned' => false,
            ])->save();

            $this->recordAudit(
                'works.designer.archived',
                'archive',
                $actor,
                $work,
                $previousStatus,
                $previousVisibility,
                $featuredRemoved,
                $pinnedRemoved,
                $requestContext,
            );

            $work->refresh()->load(['coverMedia', 'category', 'tags']);

            return $this->result(true, 'archive', $previousStatus, $work);
        });
    }

    /**
     * @param array<string, string|null> $requestContext
     * @return array<string, mixed>
     */
    public function restore(
        int $workId,
        User $actor,
        string $expectedUpdatedAt,
        array $requestContext,
    ): array {
        return DB::transaction(function () use ($workId, $actor, $expectedUpdatedAt, $requestContext): array {
            $work = $this->ownedLockedWork($workId, $actor);
            $this->ensureCurrentVersion($work, $expectedUpdatedAt);
            $previousStatus = (string) $work->status;

            if (! $work->canBeRestoredByDesigner()) {
                $this->conflict('العمل غير مؤرشف.', 'work_not_archived', $work, false);
            }

            $previousVisibility = (string) $work->visibility_status;
            $archivedFromStatus = $work->archived_from_status;
            $archivedFromVisibility = $work->archived_from_visibility_status;
            $featuredRemoved = (bool) $work->is_featured;
            $pinnedRemoved = (bool) $work->is_pinned;
            $target = $work->designerRestoreTarget();

            $work->forceFill([
                'status' => $target['status'],
                'visibility_status' => $target['visibility_status'],
                'archived_at' => null,
                'archived_from_status' => null,
                'archived_from_visibility_status' => null,
                'is_featured' => false,
                'is_pinned' => false,
            ])->save();

            $this->recordAudit(
                'works.designer.restored',
                'restore',
                $actor,
                $work,
                $previousStatus,
                $previousVisibility,
                $featuredRemoved,
                $pinnedRemoved,
                $requestContext,
                $archivedFromStatus,
                $archivedFromVisibility,
            );

            $work->refresh()->load(['coverMedia', 'category', 'tags']);

            return $this->result(true, 'restore', $previousStatus, $work);
        });
    }

    private function ownedLockedWork(int $workId, User $actor): Work
    {
        return Work::query()
            ->whereKey($workId)
            ->where('designer_id', $actor->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureCurrentVersion(Work $work, string $expectedUpdatedAt): void
    {
        $expected = CarbonImmutable::parse($expectedUpdatedAt);

        if ($work->updated_at === null || ! $expected->equalTo($work->updated_at)) {
            $this->conflict(
                'تغيرت نسخة العمل في الخادم. حمّل النسخة الأحدث ثم حاول مجددًا.',
                'work_version_conflict',
                $work,
                true,
            );
        }
    }

    private function conflict(string $message, string $code, Work $work, bool $includeVersion): never
    {
        $data = ['code' => $code, 'current_status' => $work->status];

        if ($includeVersion) {
            $data['current_updated_at'] = $work->updated_at?->toJSON();
        }

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'data' => $data,
        ], 409));
    }

    /**
     * @param array<string, string|null> $requestContext
     */
    private function recordAudit(
        string $eventType,
        string $action,
        User $actor,
        Work $work,
        string $previousStatus,
        string $previousVisibility,
        bool $featuredRemoved,
        bool $pinnedRemoved,
        array $requestContext,
        ?string $archivedFromStatus = null,
        ?string $archivedFromVisibility = null,
    ): void {
        $this->auditEventLogger->record([
            'event_type' => $eventType,
            'category' => 'works',
            'severity' => 'notice',
            'actor_type' => 'user',
            'actor_id' => $actor->getKey(),
            'actor_role' => 'designer',
            'target_type' => 'work',
            'target_id' => $work->getKey(),
            'action' => $action,
            'outcome' => 'success',
            'ip_address' => $requestContext['ip_address'] ?? null,
            'user_agent' => $requestContext['user_agent'] ?? null,
            'metadata' => [
                'work_id' => $work->getKey(),
                'previous_status' => $previousStatus,
                'current_status' => $work->status,
                'previous_visibility_status' => $previousVisibility,
                'current_visibility_status' => $work->visibility_status,
                'archived_from_status' => $archivedFromStatus ?? $work->archived_from_status,
                'archived_from_visibility_status' => $archivedFromVisibility ?? $work->archived_from_visibility_status,
                'featured_removed' => $featuredRemoved,
                'pinned_removed' => $pinnedRemoved,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function result(bool $changed, string $action, string $previousStatus, Work $work): array
    {
        return [
            'changed' => $changed,
            'action' => $action,
            'previous_status' => $previousStatus,
            'work' => $work,
        ];
    }
}
