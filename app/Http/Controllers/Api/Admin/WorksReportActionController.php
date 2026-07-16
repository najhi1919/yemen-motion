<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksReportActionRequest;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkReport;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class WorksReportActionController extends Controller
{
    /** @var array<string, string> */
    private const AUDIT_EVENT_TYPES = [
        'review' => 'works.reports.review_started',
        'dismiss' => 'works.reports.dismissed',
        'archive' => 'works.reports.archived',
    ];

    public function __construct(private readonly AuditEventLogger $auditEventLogger) {}

    public function review(WorksReportActionRequest $request, WorkReport $report): JsonResponse
    {
        return $this->execute($request, $report, 'review');
    }

    public function dismiss(WorksReportActionRequest $request, WorkReport $report): JsonResponse
    {
        return $this->execute($request, $report, 'dismiss');
    }

    public function archive(WorksReportActionRequest $request, WorkReport $report): JsonResponse
    {
        return $this->execute($request, $report, 'archive');
    }

    private function execute(
        WorksReportActionRequest $request,
        WorkReport $report,
        string $action,
    ): JsonResponse {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($request, $report, $action, $validated): array {
            $currentReport = WorkReport::query()
                ->whereKey($report->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $oldState = $this->reportState($currentReport);
            $changes = $this->changesFor(
                $currentReport,
                $action,
                $validated,
                (int) $request->user()->getKey(),
            );
            $changed = $changes !== [];

            if ($changed) {
                $currentReport->fill($changes);
                $currentReport->save();
                $currentReport->refresh();

                $this->recordAuditEvent($request, $currentReport, $action, $oldState);
            }

            $currentReport->load(['reporter:id,name', 'reviewer:id,name']);
            $work = Work::query()
                ->select(['id', 'title', 'slug', 'status', 'visibility_status', 'reports_count'])
                ->withCount(['reports as tracked_reports_count'])
                ->findOrFail($currentReport->work_id);

            return [
                'changed' => $changed,
                'report' => $currentReport,
                'work' => $work,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'action' => $action,
                'changed' => $result['changed'],
                'report' => $this->reportPayload($result['report']),
                'work' => $this->workPayload($result['work']),
            ],
            'message' => 'تم تنفيذ إجراء البلاغ بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function changesFor(
        WorkReport $report,
        string $action,
        array $validated,
        int $actorId,
    ): array {
        return match ($action) {
            'review' => $this->reviewChanges($report, $actorId),
            'dismiss' => $this->dismissChanges(
                $report,
                $actorId,
                (string) $validated['resolution_notes'],
            ),
            'archive' => $this->archiveChanges($report, $actorId),
            default => throw new HttpException(422, 'إجراء البلاغ المطلوب غير مدعوم.'),
        };
    }

    /** @return array<string, mixed> */
    private function reviewChanges(WorkReport $report, int $actorId): array
    {
        if ($report->status === WorkReport::STATUS_PENDING) {
            return [
                'status' => WorkReport::STATUS_UNDER_REVIEW,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
            ];
        }

        if ($report->status !== WorkReport::STATUS_UNDER_REVIEW) {
            throw new HttpException(422, 'لا يمكن بدء مراجعة البلاغ من حالته الحالية.');
        }

        $changes = [];

        if ($report->reviewed_by === null) {
            $changes['reviewed_by'] = $actorId;
        }

        if ($report->reviewed_at === null) {
            $changes['reviewed_at'] = now();
        }

        return $changes;
    }

    /** @return array<string, mixed> */
    private function dismissChanges(WorkReport $report, int $actorId, string $notes): array
    {
        if ($report->status === WorkReport::STATUS_DISMISSED) {
            return $report->resolution_notes === $notes
                ? []
                : ['resolution_notes' => $notes];
        }

        if ($report->status === WorkReport::STATUS_ARCHIVED) {
            throw new HttpException(422, 'لا يمكن تعديل بلاغ مؤرشف.');
        }

        if (! in_array($report->status, [
            WorkReport::STATUS_PENDING,
            WorkReport::STATUS_UNDER_REVIEW,
        ], true)) {
            throw new HttpException(422, 'لا يمكن إغلاق البلاغ من حالته الحالية.');
        }

        return [
            'status' => WorkReport::STATUS_DISMISSED,
            'reviewed_by' => $report->reviewed_by ?? $actorId,
            'reviewed_at' => $report->reviewed_at ?? now(),
            'dismissed_at' => now(),
            'archived_at' => null,
            'resolution_notes' => $notes,
        ];
    }

    /** @return array<string, mixed> */
    private function archiveChanges(WorkReport $report, int $actorId): array
    {
        if ($report->status === WorkReport::STATUS_ARCHIVED) {
            return [];
        }

        if ($report->status !== WorkReport::STATUS_DISMISSED) {
            throw new HttpException(422, 'يجب إغلاق البلاغ أولًا قبل أرشفته.');
        }

        return [
            'status' => WorkReport::STATUS_ARCHIVED,
            'reviewed_by' => $report->reviewed_by ?? $actorId,
            'reviewed_at' => $report->reviewed_at ?? now(),
            'archived_at' => now(),
        ];
    }

    /** @return array<string, mixed> */
    private function reportPayload(WorkReport $report): array
    {
        $isPending = $report->status === WorkReport::STATUS_PENDING;
        $isUnderReview = $report->status === WorkReport::STATUS_UNDER_REVIEW;

        return [
            'id' => $report->id,
            'work_id' => $report->work_id,
            'reason_code' => $report->reason_code,
            'status' => $report->status,
            'reporter' => $this->userReference($report->reporter),
            'reviewer' => $this->userReference($report->reviewer),
            'reviewed_at' => $report->reviewed_at?->toJSON(),
            'dismissed_at' => $report->dismissed_at?->toJSON(),
            'archived_at' => $report->archived_at?->toJSON(),
            'created_at' => $report->created_at?->toJSON(),
            'updated_at' => $report->updated_at?->toJSON(),
            'report_flags' => [
                'is_open' => $isPending || $isUnderReview,
                'is_pending' => $isPending,
                'is_under_review' => $isUnderReview,
                'is_dismissed' => $report->status === WorkReport::STATUS_DISMISSED,
                'is_archived' => $report->status === WorkReport::STATUS_ARCHIVED,
                'has_reviewer' => $report->reviewed_by !== null,
                'needs_attention' => $isPending || $isUnderReview,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function workPayload(Work $work): array
    {
        return [
            'id' => $work->id,
            'title' => $work->title,
            'slug' => $work->slug,
            'status' => $work->status,
            'visibility_status' => $work->visibility_status,
            'legacy_reports_count' => (int) $work->reports_count,
            'tracked_reports_count' => (int) $work->tracked_reports_count,
        ];
    }

    /** @return array{id: int, name: string}|null */
    private function userReference(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return ['id' => $user->id, 'name' => $user->name];
    }

    /** @return array<string, mixed> */
    private function reportState(WorkReport $report): array
    {
        return [
            'status' => $report->status,
            'reviewer_id' => $report->reviewed_by,
            'has_resolution_notes' => filled($report->resolution_notes),
            'reviewed_at_present' => $report->reviewed_at !== null,
            'dismissed_at_present' => $report->dismissed_at !== null,
            'archived_at_present' => $report->archived_at !== null,
        ];
    }

    /** @param array<string, mixed> $oldState */
    private function recordAuditEvent(
        WorksReportActionRequest $request,
        WorkReport $report,
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
                'target_type' => 'work_report',
                'target_id' => $report->getKey(),
                'action' => $action,
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'report_id' => $report->getKey(),
                    'work_id' => $report->work_id,
                    'action' => $action,
                    'old_status' => $oldState['status'],
                    'new_status' => $report->status,
                    'old_reviewer_id' => $oldState['reviewer_id'],
                    'new_reviewer_id' => $report->reviewed_by,
                    'old_has_resolution_notes' => $oldState['has_resolution_notes'],
                    'new_has_resolution_notes' => filled($report->resolution_notes),
                    'old_reviewed_at_present' => $oldState['reviewed_at_present'],
                    'new_reviewed_at_present' => $report->reviewed_at !== null,
                    'old_dismissed_at_present' => $oldState['dismissed_at_present'],
                    'new_dismissed_at_present' => $report->dismissed_at !== null,
                    'old_archived_at_present' => $oldState['archived_at_present'],
                    'new_archived_at_present' => $report->archived_at !== null,
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
