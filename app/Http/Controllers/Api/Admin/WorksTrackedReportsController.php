<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksReportDetailRequest;
use App\Http\Requests\Admin\WorksTrackedReportsRequest;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkReport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class WorksTrackedReportsController extends Controller
{
    public function index(WorksTrackedReportsRequest $request, Work $work): JsonResponse
    {
        $validated = $request->validated();
        $sort = (string) ($validated['sort'] ?? 'created_at');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $perPage = (int) ($validated['per_page'] ?? 15);
        $from = filled($validated['from'] ?? null)
            ? Carbon::parse((string) $validated['from'])->startOfDay()
            : null;
        $to = filled($validated['to'] ?? null)
            ? Carbon::parse((string) $validated['to'])->endOfDay()
            : null;

        $reportsQuery = WorkReport::query()
            ->where('work_id', $work->id)
            ->when(filled($validated['status'] ?? null), fn (Builder $query) => $query->where('status', $validated['status']))
            ->when(filled($validated['reason_code'] ?? null), fn (Builder $query) => $query->where('reason_code', $validated['reason_code']))
            ->when(isset($validated['reporter_id']), fn (Builder $query) => $query->where('reporter_id', $validated['reporter_id']))
            ->when(isset($validated['reviewed_by']), fn (Builder $query) => $query->where('reviewed_by', $validated['reviewed_by']))
            ->when($from !== null, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->where('created_at', '<=', $to));

        $summary = $this->summary(clone $reportsQuery);
        $reports = (clone $reportsQuery)
            ->select([
                'id',
                'work_id',
                'reason_code',
                'status',
                'reporter_id',
                'reviewed_by',
                'reviewed_at',
                'dismissed_at',
                'archived_at',
                'created_at',
                'updated_at',
            ])
            ->with(['reporter:id,name', 'reviewer:id,name'])
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->through(fn (WorkReport $report): array => $this->reportListPayload($report));

        $work->loadCount(['reports as tracked_reports_count']);

        return response()->json([
            'success' => true,
            'data' => [
                'work' => $this->workContext($work),
                'items' => $reports->items(),
                'pagination' => [
                    'current_page' => $reports->currentPage(),
                    'per_page' => $reports->perPage(),
                    'total' => $reports->total(),
                    'last_page' => $reports->lastPage(),
                ],
                'summary' => $summary,
                'filters' => [
                    'status' => $validated['status'] ?? null,
                    'reason_code' => $validated['reason_code'] ?? null,
                    'reporter_id' => isset($validated['reporter_id']) ? (int) $validated['reporter_id'] : null,
                    'reviewed_by' => isset($validated['reviewed_by']) ? (int) $validated['reviewed_by'] : null,
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                    'sort' => $sort,
                    'direction' => $direction,
                    'statuses' => WorkReport::STATUSES,
                    'sorts' => ['created_at', 'updated_at', 'status', 'reviewed_at', 'dismissed_at', 'archived_at'],
                    'directions' => ['asc', 'desc'],
                    'per_page_options' => [15, 25, 50],
                ],
            ],
            'message' => 'تم جلب بلاغات العمل المتتبعة بنجاح',
            'errors' => null,
        ]);
    }

    public function show(WorksReportDetailRequest $request, WorkReport $report): JsonResponse
    {
        $report->load(['reporter:id,name', 'reviewer:id,name']);
        $work = Work::query()
            ->select(['id', 'title', 'slug', 'status', 'visibility_status', 'is_featured', 'is_pinned', 'reports_count'])
            ->withCount(['reports as tracked_reports_count'])
            ->findOrFail($report->work_id);

        return response()->json([
            'success' => true,
            'data' => [
                'report' => $this->reportDetailPayload($report),
                'work' => $this->workDetailContext($work),
                'field_access' => [
                    'can_view_report_details' => true,
                    'can_view_resolution_notes' => true,
                ],
            ],
            'message' => 'تم جلب تفاصيل بلاغ العمل بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function summary(Builder $query): array
    {
        $counts = $query
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending', [WorkReport::STATUS_PENDING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS under_review', [WorkReport::STATUS_UNDER_REVIEW])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS dismissed', [WorkReport::STATUS_DISMISSED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS archived', [WorkReport::STATUS_ARCHIVED])
            ->first();

        $pending = (int) ($counts?->pending ?? 0);
        $underReview = (int) ($counts?->under_review ?? 0);

        return [
            'total' => (int) ($counts?->total ?? 0),
            'pending' => $pending,
            'under_review' => $underReview,
            'dismissed' => (int) ($counts?->dismissed ?? 0),
            'archived' => (int) ($counts?->archived ?? 0),
            'open' => $pending + $underReview,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportListPayload(WorkReport $report): array
    {
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
            'report_flags' => $this->reportFlags($report),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportDetailPayload(WorkReport $report): array
    {
        return [
            'id' => $report->id,
            'reason_code' => $report->reason_code,
            'details' => $report->details,
            'status' => $report->status,
            'resolution_notes' => $report->resolution_notes,
            'reporter' => $this->userReference($report->reporter),
            'reviewer' => $this->userReference($report->reviewer),
            'reviewed_at' => $report->reviewed_at?->toJSON(),
            'dismissed_at' => $report->dismissed_at?->toJSON(),
            'archived_at' => $report->archived_at?->toJSON(),
            'created_at' => $report->created_at?->toJSON(),
            'updated_at' => $report->updated_at?->toJSON(),
            'report_flags' => $this->reportFlags($report),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function reportFlags(WorkReport $report): array
    {
        $isPending = $report->status === WorkReport::STATUS_PENDING;
        $isUnderReview = $report->status === WorkReport::STATUS_UNDER_REVIEW;

        return [
            'is_open' => $isPending || $isUnderReview,
            'is_pending' => $isPending,
            'is_under_review' => $isUnderReview,
            'is_dismissed' => $report->status === WorkReport::STATUS_DISMISSED,
            'is_archived' => $report->status === WorkReport::STATUS_ARCHIVED,
            'has_reviewer' => $report->reviewed_by !== null,
            'needs_attention' => $isPending || $isUnderReview,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function workContext(Work $work): array
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

    /**
     * @return array<string, mixed>
     */
    private function workDetailContext(Work $work): array
    {
        return [
            'id' => $work->id,
            'title' => $work->title,
            'slug' => $work->slug,
            'status' => $work->status,
            'visibility_status' => $work->visibility_status,
            'is_featured' => $work->is_featured,
            'is_pinned' => $work->is_pinned,
            'legacy_reports_count' => (int) $work->reports_count,
            'tracked_reports_count' => (int) $work->tracked_reports_count,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userReference(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return ['id' => $user->id, 'name' => $user->name];
    }
}
