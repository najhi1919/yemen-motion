<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksReviewActionRequest;
use App\Models\User;
use App\Models\Work;
use App\Services\Audit\AuditEventLogger;
use App\Services\Works\WorksSettingsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class WorksReviewActionController extends Controller
{
    /** @var array<string, string> */
    private const AUDIT_EVENT_TYPES = [
        'start' => 'works.review.started',
        'assignReviewer' => 'works.review.reviewer_assigned',
        'approve' => 'works.review.approved',
        'requestChanges' => 'works.review.changes_requested',
        'reject' => 'works.review.rejected',
        'publishAfterApproval' => 'works.review.published',
        'reopen' => 'works.review.reopened',
    ];

    /** @var array<string, string> */
    private const RESPONSE_ACTIONS = [
        'start' => 'start',
        'assignReviewer' => 'assign_reviewer',
        'approve' => 'approve',
        'requestChanges' => 'request_changes',
        'reject' => 'reject',
        'publishAfterApproval' => 'publish',
        'reopen' => 'reopen',
    ];

    public function __construct(
        private readonly AuditEventLogger $auditEventLogger,
        private readonly WorksSettingsStore $settingsStore,
    ) {}

    public function start(WorksReviewActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'start');
    }

    public function assignReviewer(WorksReviewActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'assignReviewer');
    }

    public function approve(WorksReviewActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'approve');
    }

    public function requestChanges(WorksReviewActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'requestChanges');
    }

    public function reject(WorksReviewActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'reject');
    }

    public function publishAfterApproval(WorksReviewActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'publishAfterApproval');
    }

    public function reopen(WorksReviewActionRequest $request, Work $work): JsonResponse
    {
        return $this->execute($request, $work, 'reopen');
    }

    private function execute(
        WorksReviewActionRequest $request,
        Work $work,
        string $action,
    ): JsonResponse {
        $validated = $request->validated();
        $storedSettings = $this->settingsStore->getGlobalSettings();
        $publicationPolicy = $this->publicationPolicy($storedSettings);

        $result = DB::transaction(function () use (
            $request,
            $work,
            $action,
            $validated,
            $publicationPolicy,
        ): array {
            $currentWork = Work::query()
                ->whereKey($work->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $oldState = $this->reviewState($currentWork);
            $changes = $this->changesFor(
                $currentWork,
                $action,
                $validated,
                (int) $request->user()->getKey(),
                $publicationPolicy['direct_publish_trust_enabled'],
            );
            $changed = $changes !== [];
            $autoPublished = $changed
                && $action === 'approve'
                && $publicationPolicy['direct_publish_trust_enabled'];

            if ($changed) {
                $currentWork->fill($changes);
                $currentWork->save();
                $currentWork->refresh();

                if ($autoPublished) {
                    $approvedState = [
                        ...$this->reviewState($currentWork),
                        'status' => Work::STATUS_APPROVED,
                        'visibility_status' => Work::VISIBILITY_HIDDEN,
                    ];

                    $this->recordAuditEvent(
                        $request,
                        $currentWork,
                        self::AUDIT_EVENT_TYPES['approve'],
                        self::RESPONSE_ACTIONS['approve'],
                        $oldState,
                        $approvedState,
                    );
                    $this->recordAuditEvent(
                        $request,
                        $currentWork,
                        self::AUDIT_EVENT_TYPES['publishAfterApproval'],
                        self::RESPONSE_ACTIONS['publishAfterApproval'],
                        $approvedState,
                        $this->reviewState($currentWork),
                        [
                            'automatic' => true,
                            'trigger_action' => 'approve',
                            'settings_version' => $publicationPolicy['settings_version'],
                        ],
                    );
                } else {
                    $this->recordAuditEvent(
                        $request,
                        $currentWork,
                        self::AUDIT_EVENT_TYPES[$action],
                        self::RESPONSE_ACTIONS[$action],
                        $oldState,
                        $this->reviewState($currentWork),
                    );
                }
            }

            $currentWork->load([
                'designer:id,name',
                'reviewer:id,name',
            ]);

            return [
                'changed' => $changed,
                'auto_published' => $autoPublished,
                'work' => $currentWork,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'action' => self::RESPONSE_ACTIONS[$action],
                'changed' => $result['changed'],
                'auto_published' => $result['auto_published'],
                'publication_policy' => $publicationPolicy,
                'work' => $this->workPayload($result['work']),
            ],
            'message' => 'تم تنفيذ إجراء المراجعة بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function changesFor(
        Work $work,
        string $action,
        array $validated,
        int $actorId,
        bool $directPublishTrustEnabled,
    ): array {
        return match ($action) {
            'start' => $this->startChanges($work, $actorId),
            'assignReviewer' => $this->assignReviewerChanges($work, (int) $validated['reviewer_id']),
            'approve' => $this->approveChanges($work, $actorId, $directPublishTrustEnabled),
            'requestChanges' => $this->requestChangesChanges(
                $work,
                $actorId,
                (string) $validated['change_request_notes'],
            ),
            'reject' => $this->rejectChanges(
                $work,
                $actorId,
                (string) $validated['rejection_reason'],
            ),
            'publishAfterApproval' => $this->publishAfterApprovalChanges($work),
            'reopen' => $this->reopenChanges($work, $actorId),
            default => throw new HttpException(422, 'إجراء المراجعة المطلوب غير مدعوم.'),
        };
    }

    /** @return array<string, mixed> */
    private function startChanges(Work $work, int $actorId): array
    {
        if (! in_array($work->status, [Work::STATUS_SUBMITTED, Work::STATUS_IN_REVIEW], true)) {
            throw new HttpException(422, 'لا يمكن بدء مراجعة العمل من حالته الحالية.');
        }

        if ($work->status === Work::STATUS_IN_REVIEW) {
            return $work->reviewer_id === null ? ['reviewer_id' => $actorId] : [];
        }

        $changes = [
            'status' => Work::STATUS_IN_REVIEW,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
        ];

        if ($work->reviewer_id === null) {
            $changes['reviewer_id'] = $actorId;
        }

        return $changes;
    }

    /** @return array<string, int> */
    private function assignReviewerChanges(Work $work, int $reviewerId): array
    {
        if (! in_array($work->status, [
            Work::STATUS_SUBMITTED,
            Work::STATUS_IN_REVIEW,
            Work::STATUS_CHANGES_REQUESTED,
        ], true)) {
            throw new HttpException(422, 'لا يمكن تعيين مراجع للعمل من حالته الحالية.');
        }

        return $work->reviewer_id === $reviewerId ? [] : ['reviewer_id' => $reviewerId];
    }

    /** @return array<string, mixed> */
    private function approveChanges(
        Work $work,
        int $actorId,
        bool $directPublishTrustEnabled,
    ): array
    {
        if ($work->status === Work::STATUS_APPROVED) {
            return [];
        }

        if ($work->status !== Work::STATUS_IN_REVIEW) {
            throw new HttpException(422, 'لا يمكن اعتماد العمل من حالته الحالية.');
        }

        $decisionTime = now();

        return [
            'status' => $directPublishTrustEnabled
                ? Work::STATUS_PUBLISHED
                : Work::STATUS_APPROVED,
            'visibility_status' => $directPublishTrustEnabled
                ? Work::VISIBILITY_PUBLIC
                : Work::VISIBILITY_HIDDEN,
            'reviewer_id' => $work->reviewer_id ?? $actorId,
            'reviewed_at' => $decisionTime,
            'approved_at' => $decisionTime,
            ...($directPublishTrustEnabled ? ['published_at' => $decisionTime] : []),
            'rejected_at' => null,
            'rejection_reason' => null,
            'change_request_notes' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function requestChangesChanges(Work $work, int $actorId, string $notes): array
    {
        if ($work->status === Work::STATUS_CHANGES_REQUESTED) {
            return $work->change_request_notes === $notes
                ? []
                : ['change_request_notes' => $notes];
        }

        if ($work->status !== Work::STATUS_IN_REVIEW) {
            throw new HttpException(422, 'لا يمكن طلب تعديلات على العمل من حالته الحالية.');
        }

        return [
            'status' => Work::STATUS_CHANGES_REQUESTED,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'reviewer_id' => $work->reviewer_id ?? $actorId,
            'reviewed_at' => now(),
            'change_request_notes' => $notes,
            'rejection_reason' => null,
            'approved_at' => null,
            'rejected_at' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function rejectChanges(Work $work, int $actorId, string $reason): array
    {
        if ($work->status === Work::STATUS_REJECTED) {
            return $work->rejection_reason === $reason
                ? []
                : ['rejection_reason' => $reason];
        }

        if ($work->status !== Work::STATUS_IN_REVIEW) {
            throw new HttpException(422, 'لا يمكن رفض العمل من حالته الحالية.');
        }

        $decisionTime = now();

        return [
            'status' => Work::STATUS_REJECTED,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'reviewer_id' => $work->reviewer_id ?? $actorId,
            'reviewed_at' => $decisionTime,
            'rejected_at' => $decisionTime,
            'rejection_reason' => $reason,
            'change_request_notes' => null,
            'approved_at' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function publishAfterApprovalChanges(Work $work): array
    {
        if ($work->status === Work::STATUS_PUBLISHED) {
            if ($work->visibility_status === Work::VISIBILITY_PUBLIC) {
                return [];
            }

            throw new HttpException(422, 'لا يمكن اعتماد نشر عمل منشور لكنه غير عام.');
        }

        if ($work->status !== Work::STATUS_APPROVED) {
            throw new HttpException(422, 'لا يمكن نشر العمل قبل اعتماده.');
        }

        return [
            'status' => Work::STATUS_PUBLISHED,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'published_at' => $work->published_at ?? now(),
        ];
    }

    /** @return array<string, mixed> */
    private function reopenChanges(Work $work, int $actorId): array
    {
        if ($work->status === Work::STATUS_IN_REVIEW) {
            return $work->reviewer_id === null ? ['reviewer_id' => $actorId] : [];
        }

        if (! in_array($work->status, [
            Work::STATUS_CHANGES_REQUESTED,
            Work::STATUS_REJECTED,
            Work::STATUS_APPROVED,
        ], true)) {
            throw new HttpException(422, 'لا يمكن إعادة فتح مراجعة العمل من حالته الحالية.');
        }

        return [
            'status' => Work::STATUS_IN_REVIEW,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'reviewer_id' => $work->reviewer_id ?? $actorId,
        ];
    }

    /** @return array<string, mixed> */
    private function workPayload(Work $work): array
    {
        return [
            'id' => $work->id,
            'title' => $work->title,
            'slug' => $work->slug,
            'summary' => $work->summary,
            'status' => $work->status,
            'visibility_status' => $work->visibility_status,
            'media_type' => $work->media_type,
            'designer' => $this->userReference($work->designer),
            'reviewer' => $this->userReference($work->reviewer),
            'category_id' => $work->category_id,
            'is_featured' => (bool) $work->is_featured,
            'is_pinned' => (bool) $work->is_pinned,
            'reports_count' => (int) $work->reports_count,
            'views_count' => (int) $work->views_count,
            'likes_count' => (int) $work->likes_count,
            'submitted_at' => $work->submitted_at?->toISOString(),
            'reviewed_at' => $work->reviewed_at?->toISOString(),
            'approved_at' => $work->approved_at?->toISOString(),
            'published_at' => $work->published_at?->toISOString(),
            'rejected_at' => $work->rejected_at?->toISOString(),
            'updated_at' => $work->updated_at?->toISOString(),
            'created_at' => $work->created_at?->toISOString(),
            'review_flags' => [
                'assigned' => $work->reviewer_id !== null,
                'in_queue' => in_array($work->status, [
                    Work::STATUS_SUBMITTED,
                    Work::STATUS_IN_REVIEW,
                    Work::STATUS_CHANGES_REQUESTED,
                ], true),
                'decision_made' => in_array($work->status, [
                    Work::STATUS_APPROVED,
                    Work::STATUS_REJECTED,
                ], true),
                'is_published' => $work->status === Work::STATUS_PUBLISHED
                    && $work->visibility_status === Work::VISIBILITY_PUBLIC,
                'has_reports' => (int) $work->reports_count > 0,
                'needs_attention' => (int) $work->reports_count > 0
                    || in_array($work->status, [
                        Work::STATUS_CHANGES_REQUESTED,
                        Work::STATUS_REJECTED,
                    ], true),
            ],
        ];
    }

    /** @return array{id: int, name: string}|null */
    private function userReference(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }

    /** @return array<string, mixed> */
    private function reviewState(Work $work): array
    {
        return [
            'status' => $work->status,
            'visibility_status' => $work->visibility_status,
            'reviewer_id' => $work->reviewer_id,
            'has_change_request_notes' => filled($work->change_request_notes),
            'has_rejection_reason' => filled($work->rejection_reason),
        ];
    }

    /**
     * @param array<string, mixed> $oldState
     * @param array<string, mixed> $newState
     * @param array<string, mixed> $additionalMetadata
     */
    private function recordAuditEvent(
        WorksReviewActionRequest $request,
        Work $work,
        string $eventType,
        string $responseAction,
        array $oldState,
        array $newState,
        array $additionalMetadata = [],
    ): void {
        $actor = $request->user();

        try {
            $this->auditEventLogger->record([
                'event_type' => $eventType,
                'category' => 'works',
                'severity' => 'notice',
                'actor_type' => $actor ? 'user' : 'system',
                'actor_id' => $actor?->getKey(),
                'actor_role' => $actor?->roles->first()?->name,
                'target_type' => 'work',
                'target_id' => $work->getKey(),
                'action' => $responseAction,
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'work_id' => $work->getKey(),
                    'action' => $responseAction,
                    'old_status' => $oldState['status'],
                    'new_status' => $newState['status'],
                    'old_visibility_status' => $oldState['visibility_status'],
                    'new_visibility_status' => $newState['visibility_status'],
                    'old_reviewer_id' => $oldState['reviewer_id'],
                    'new_reviewer_id' => $newState['reviewer_id'],
                    'old_has_change_request_notes' => $oldState['has_change_request_notes'],
                    'new_has_change_request_notes' => $newState['has_change_request_notes'],
                    'old_has_rejection_reason' => $oldState['has_rejection_reason'],
                    'new_has_rejection_reason' => $newState['has_rejection_reason'],
                    ...$additionalMetadata,
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            if (app()->environment('testing')) {
                throw $exception;
            }
        }
    }

    /**
     * @param array{
     *     version: int,
     *     values: array{direct_publish_trust_enabled: bool}
     * } $storedSettings
     * @return array{
     *     source: string,
     *     direct_publish_trust_enabled: bool,
     *     approval_behavior: string,
     *     settings_version: int
     * }
     */
    private function publicationPolicy(array $storedSettings): array
    {
        $directPublishTrustEnabled = $storedSettings['values']['direct_publish_trust_enabled'];

        return [
            'source' => 'work_settings',
            'direct_publish_trust_enabled' => $directPublishTrustEnabled,
            'approval_behavior' => $directPublishTrustEnabled
                ? 'approve_and_publish'
                : 'approve_only',
            'settings_version' => (int) $storedSettings['version'],
        ];
    }
}
