<?php

declare(strict_types=1);

namespace App\Services\Works;

use App\Exceptions\WorksReviewReadinessException;
use App\Exceptions\WorksReviewSubmissionConflictException;
use App\Models\User;
use App\Models\Work;
use App\Services\Audit\AuditEventLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WorksReviewSubmissionService
{
    public function __construct(
        private readonly WorksReviewReadinessService $readinessService,
        private readonly WorksSettingsStore $settingsStore,
        private readonly AuditEventLogger $auditEventLogger,
    ) {}

    /**
     * @param array<string, string|null> $requestContext
     * @return array<string, mixed>
     */
    public function submit(
        int $workId,
        string $expectedUpdatedAt,
        User $actor,
        array $requestContext,
    ): array {
        return DB::transaction(function () use ($workId, $expectedUpdatedAt, $actor, $requestContext): array {
            $work = Work::query()->whereKey($workId)->lockForUpdate()->firstOrFail();
            $settings = $this->settingsStore->getGlobalSettings();
            $expected = CarbonImmutable::parse($expectedUpdatedAt);
            $submittableStatus = in_array(
                $work->status,
                [Work::STATUS_DRAFT, Work::STATUS_CHANGES_REQUESTED],
                true,
            );

            if (
                $work->updated_at === null
                || ! $expected->equalTo($work->updated_at)
                || ! $submittableStatus
            ) {
                $readiness = $this->readinessService->evaluate(
                    $work,
                    $settings,
                    $actor->hasRole('super-admin') || $actor->can('admin.works.update.private_notes'),
                );

                throw new WorksReviewSubmissionConflictException(
                    $work->status,
                    $work->updated_at?->toJSON(),
                    $readiness,
                );
            }

            $readiness = $this->readinessService->evaluate(
                $work,
                $settings,
                $actor->hasRole('super-admin') || $actor->can('admin.works.update.private_notes'),
            );

            if (! $readiness['ready']) {
                throw new WorksReviewReadinessException($readiness);
            }

            $oldStatus = $work->status;
            $resubmission = $oldStatus === Work::STATUS_CHANGES_REQUESTED;
            $retainedReviewer = $resubmission && $work->reviewer_id !== null;
            $submittedAt = now();

            $work->forceFill([
                'status' => Work::STATUS_SUBMITTED,
                'visibility_status' => Work::VISIBILITY_HIDDEN,
                'submitted_at' => $submittedAt,
                'reviewed_at' => null,
                'approved_at' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'published_at' => null,
            ])->save();
            $work->refresh();

            $this->auditEventLogger->record([
                'event_type' => 'works.review.submitted',
                'category' => 'works',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $actor->getKey(),
                'actor_role' => $actor->roles->first()?->name,
                'target_type' => 'work',
                'target_id' => $work->getKey(),
                'action' => 'submit',
                'outcome' => 'success',
                'ip_address' => $requestContext['ip_address'] ?? null,
                'user_agent' => $requestContext['user_agent'] ?? null,
                'metadata' => [
                    'work_id' => $work->getKey(),
                    'old_status' => $oldStatus,
                    'new_status' => Work::STATUS_SUBMITTED,
                    'resubmission' => $resubmission,
                    'retained_reviewer' => $retainedReviewer,
                    'blockers_count' => $readiness['blockers_count'],
                    'warnings_count' => $readiness['warnings_count'],
                    'settings_version' => (int) ($settings['version'] ?? 1),
                    'submitted_at' => $submittedAt->toJSON(),
                ],
            ]);

            return [
                'action' => 'submit',
                'changed' => true,
                'resubmission' => $resubmission,
                'work' => [
                    'id' => $work->id,
                    'status' => $work->status,
                    'visibility_status' => $work->visibility_status,
                    'submitted_at' => $work->submitted_at?->toJSON(),
                    'updated_at' => $work->updated_at?->toJSON(),
                ],
                'readiness' => $readiness,
            ];
        });
    }
}
