<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksReportsRequest;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkReport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WorksReportsController extends Controller
{
    public function index(WorksReportsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $queryText = trim((string) ($validated['q'] ?? ''));
        $minimumReports = (int) ($validated['min_reports'] ?? 1);
        $reportSource = (string) ($validated['report_source'] ?? 'all');
        $trackedStatus = isset($validated['tracked_status'])
            ? (string) $validated['tracked_status']
            : null;
        $sort = (string) ($validated['sort'] ?? 'reports_count');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $perPage = (int) ($validated['per_page'] ?? 15);
        $isFeatured = $this->booleanFilter($request, $validated, 'is_featured');
        $isPinned = $this->booleanFilter($request, $validated, 'is_pinned');
        $from = filled($validated['from'] ?? null)
            ? Carbon::parse((string) $validated['from'])->startOfDay()
            : null;
        $to = filled($validated['to'] ?? null)
            ? Carbon::parse((string) $validated['to'])->endOfDay()
            : null;

        $reportsQuery = $this->reportsQuery(
            $validated,
            $queryText,
            $minimumReports,
            $reportSource,
            $trackedStatus,
            $isFeatured,
            $isPinned,
            $from,
            $to,
        );
        $summary = $this->summary(clone $reportsQuery);

        $works = (clone $reportsQuery)
            ->with([
                'designer:id,name',
                'reviewer:id,name',
            ])
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->through(fn (Work $work): array => $this->workPayload($work));

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $works->items(),
                'pagination' => [
                    'current_page' => $works->currentPage(),
                    'per_page' => $works->perPage(),
                    'total' => $works->total(),
                    'last_page' => $works->lastPage(),
                ],
                'summary' => $summary,
                'filters' => [
                    'q' => $queryText !== '' ? $queryText : null,
                    'status' => $validated['status'] ?? null,
                    'visibility_status' => $validated['visibility_status'] ?? null,
                    'media_type' => $validated['media_type'] ?? null,
                    'designer_id' => isset($validated['designer_id']) ? (int) $validated['designer_id'] : null,
                    'reviewer_id' => isset($validated['reviewer_id']) ? (int) $validated['reviewer_id'] : null,
                    'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
                    'min_reports' => $minimumReports,
                    'report_source' => $reportSource,
                    'tracked_status' => $trackedStatus,
                    'is_featured' => $isFeatured,
                    'is_pinned' => $isPinned,
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                    'sort' => $sort,
                    'direction' => $direction,
                    'report_sources' => ['all', 'legacy', 'tracked', 'both'],
                    'tracked_statuses' => WorkReport::STATUSES,
                    'default_report_source' => 'all',
                    'counts_synchronized' => false,
                ],
                'tracking_support' => [
                    'tracked_reports_available' => true,
                    'legacy_counter_available' => true,
                    'tracked_source' => 'work_reports',
                    'legacy_source' => 'works.reports_count',
                    'counts_are_synchronized' => false,
                    'combined_count_is_signal_only' => true,
                    'reason' => 'عداد works.reports_count تاريخي وغير مرتبط تلقائيًا بسجلات work_reports.',
                ],
            ],
            'message' => 'تم جلب قائمة بلاغات الأعمال بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function reportsQuery(
        array $validated,
        string $queryText,
        int $minimumReports,
        string $reportSource,
        ?string $trackedStatus,
        ?bool $isFeatured,
        ?bool $isPinned,
        ?Carbon $from,
        ?Carbon $to,
    ): Builder {
        $query = Work::query()
            ->select([
                'works.id',
                'works.title',
                'works.slug',
                'works.summary',
                'works.status',
                'works.visibility_status',
                'works.media_type',
                'works.designer_id',
                'works.reviewer_id',
                'works.category_id',
                'works.is_featured',
                'works.is_pinned',
                'works.reports_count',
                'works.views_count',
                'works.likes_count',
                'works.submitted_at',
                'works.published_at',
                'works.hidden_at',
                'works.updated_at',
                'works.created_at',
            ])
            ->selectRaw(
                'works.reports_count + (SELECT COUNT(*) FROM work_reports WHERE work_reports.work_id = works.id) AS combined_reports_count',
            )
            ->withCount([
                'reports as tracked_reports_count',
                'reports as pending_tracked_reports_count' => fn (Builder $query) => $query
                    ->where('status', WorkReport::STATUS_PENDING),
                'reports as under_review_tracked_reports_count' => fn (Builder $query) => $query
                    ->where('status', WorkReport::STATUS_UNDER_REVIEW),
                'reports as dismissed_tracked_reports_count' => fn (Builder $query) => $query
                    ->where('status', WorkReport::STATUS_DISMISSED),
                'reports as archived_tracked_reports_count' => fn (Builder $query) => $query
                    ->where('status', WorkReport::STATUS_ARCHIVED),
                'reports as open_tracked_reports_count' => fn (Builder $query) => $query
                    ->whereIn('status', [
                        WorkReport::STATUS_PENDING,
                        WorkReport::STATUS_UNDER_REVIEW,
                    ]),
                'reports as summary_tracked_reports_count' => fn (Builder $query) => $query
                    ->when($trackedStatus !== null, fn (Builder $query) => $query->where('status', $trackedStatus)),
                'reports as summary_pending_tracked_reports_count' => fn (Builder $query) => $query
                    ->where('status', WorkReport::STATUS_PENDING)
                    ->when($trackedStatus !== null, fn (Builder $query) => $query->where('status', $trackedStatus)),
                'reports as summary_under_review_tracked_reports_count' => fn (Builder $query) => $query
                    ->where('status', WorkReport::STATUS_UNDER_REVIEW)
                    ->when($trackedStatus !== null, fn (Builder $query) => $query->where('status', $trackedStatus)),
                'reports as summary_dismissed_tracked_reports_count' => fn (Builder $query) => $query
                    ->where('status', WorkReport::STATUS_DISMISSED)
                    ->when($trackedStatus !== null, fn (Builder $query) => $query->where('status', $trackedStatus)),
                'reports as summary_archived_tracked_reports_count' => fn (Builder $query) => $query
                    ->where('status', WorkReport::STATUS_ARCHIVED)
                    ->when($trackedStatus !== null, fn (Builder $query) => $query->where('status', $trackedStatus)),
            ]);

        $query
            ->when($reportSource === 'all', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('works.reports_count', '>', 0)->orWhereHas('reports');
                });
            })
            ->when($reportSource === 'legacy', fn (Builder $query) => $query->where('works.reports_count', '>', 0))
            ->when($reportSource === 'tracked', fn (Builder $query) => $query->whereHas('reports'))
            ->when($reportSource === 'both', fn (Builder $query) => $query
                ->where('works.reports_count', '>', 0)
                ->whereHas('reports'))
            ->when($trackedStatus !== null, fn (Builder $query) => $query->whereHas(
                'reports',
                fn (Builder $query) => $query->where('status', $trackedStatus),
            ))
            ->whereRaw(
                '(works.reports_count + (SELECT COUNT(*) FROM work_reports WHERE work_reports.work_id = works.id)) >= ?',
                [$minimumReports],
            )
            ->when($queryText !== '', function (Builder $query) use ($queryText): void {
                $query->where(function (Builder $searchQuery) use ($queryText): void {
                    $searchQuery
                        ->where('works.title', 'like', "%{$queryText}%")
                        ->orWhere('works.slug', 'like', "%{$queryText}%")
                        ->orWhere('works.summary', 'like', "%{$queryText}%");
                });
            })
            ->when(
                filled($validated['status'] ?? null),
                fn (Builder $query) => $query->where('works.status', $validated['status']),
            )
            ->when(
                filled($validated['visibility_status'] ?? null),
                fn (Builder $query) => $query->where('works.visibility_status', $validated['visibility_status']),
            )
            ->when(
                filled($validated['media_type'] ?? null),
                fn (Builder $query) => $query->where('works.media_type', $validated['media_type']),
            )
            ->when(
                isset($validated['designer_id']),
                fn (Builder $query) => $query->where('works.designer_id', $validated['designer_id']),
            )
            ->when(
                isset($validated['reviewer_id']),
                fn (Builder $query) => $query->where('works.reviewer_id', $validated['reviewer_id']),
            )
            ->when(
                isset($validated['category_id']),
                fn (Builder $query) => $query->where('works.category_id', $validated['category_id']),
            )
            ->when($isFeatured !== null, fn (Builder $query) => $query->where('works.is_featured', $isFeatured))
            ->when($isPinned !== null, fn (Builder $query) => $query->where('works.is_pinned', $isPinned))
            ->when($from !== null, fn (Builder $query) => $query->where('works.updated_at', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->where('works.updated_at', '<=', $to));

        return $query;
    }

    /**
     * @return array<string, int>
     */
    private function summary(Builder $query): array
    {
        $counts = DB::query()
            ->fromSub($query->reorder()->toBase(), 'filtered_works')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN combined_reports_count >= 5 THEN 1 ELSE 0 END) AS high_reports')
            ->selectRaw('SUM(CASE WHEN visibility_status = ? THEN 1 ELSE 0 END) AS public_reported', [Work::VISIBILITY_PUBLIC])
            ->selectRaw('SUM(CASE WHEN visibility_status = ? OR status = ? THEN 1 ELSE 0 END) AS hidden_reported', [Work::VISIBILITY_HIDDEN, Work::STATUS_HIDDEN])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS published_reported', [Work::STATUS_PUBLISHED])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) AS review_queue_reported', [Work::STATUS_SUBMITTED, Work::STATUS_IN_REVIEW, Work::STATUS_CHANGES_REQUESTED])
            ->selectRaw('SUM(CASE WHEN is_featured THEN 1 ELSE 0 END) AS featured_reported')
            ->selectRaw('SUM(CASE WHEN is_pinned THEN 1 ELSE 0 END) AS pinned_reported')
            ->selectRaw('COALESCE(SUM(reports_count), 0) AS total_reports')
            ->selectRaw('COALESCE(SUM(reports_count), 0) AS legacy_reports_total')
            ->selectRaw('COALESCE(SUM(summary_tracked_reports_count), 0) AS tracked_reports_total')
            ->selectRaw('COALESCE(SUM(reports_count + summary_tracked_reports_count), 0) AS combined_report_signal_total')
            ->selectRaw('COALESCE(SUM(summary_pending_tracked_reports_count), 0) AS pending_tracked_reports')
            ->selectRaw('COALESCE(SUM(summary_under_review_tracked_reports_count), 0) AS under_review_tracked_reports')
            ->selectRaw('COALESCE(SUM(summary_dismissed_tracked_reports_count), 0) AS dismissed_tracked_reports')
            ->selectRaw('COALESCE(SUM(summary_archived_tracked_reports_count), 0) AS archived_tracked_reports')
            ->selectRaw('COALESCE(SUM(summary_pending_tracked_reports_count + summary_under_review_tracked_reports_count), 0) AS open_tracked_reports')
            ->selectRaw('SUM(CASE WHEN reports_count > 0 THEN 1 ELSE 0 END) AS works_with_legacy_reports')
            ->selectRaw('SUM(CASE WHEN tracked_reports_count > 0 THEN 1 ELSE 0 END) AS works_with_tracked_reports')
            ->selectRaw('SUM(CASE WHEN open_tracked_reports_count > 0 THEN 1 ELSE 0 END) AS works_with_open_tracked_reports')
            ->selectRaw('SUM(CASE WHEN reports_count > 0 AND tracked_reports_count > 0 THEN 1 ELSE 0 END) AS works_with_both_sources')
            ->first();

        $total = (int) ($counts?->total ?? 0);

        return [
            'total' => $total,
            'reported' => $total,
            'high_reports' => (int) ($counts?->high_reports ?? 0),
            'public_reported' => (int) ($counts?->public_reported ?? 0),
            'hidden_reported' => (int) ($counts?->hidden_reported ?? 0),
            'published_reported' => (int) ($counts?->published_reported ?? 0),
            'review_queue_reported' => (int) ($counts?->review_queue_reported ?? 0),
            'featured_reported' => (int) ($counts?->featured_reported ?? 0),
            'pinned_reported' => (int) ($counts?->pinned_reported ?? 0),
            'total_reports' => (int) ($counts?->total_reports ?? 0),
            'legacy_reports_total' => (int) ($counts?->legacy_reports_total ?? 0),
            'tracked_reports_total' => (int) ($counts?->tracked_reports_total ?? 0),
            'combined_report_signal_total' => (int) ($counts?->combined_report_signal_total ?? 0),
            'pending_tracked_reports' => (int) ($counts?->pending_tracked_reports ?? 0),
            'under_review_tracked_reports' => (int) ($counts?->under_review_tracked_reports ?? 0),
            'dismissed_tracked_reports' => (int) ($counts?->dismissed_tracked_reports ?? 0),
            'archived_tracked_reports' => (int) ($counts?->archived_tracked_reports ?? 0),
            'open_tracked_reports' => (int) ($counts?->open_tracked_reports ?? 0),
            'works_with_legacy_reports' => (int) ($counts?->works_with_legacy_reports ?? 0),
            'works_with_tracked_reports' => (int) ($counts?->works_with_tracked_reports ?? 0),
            'works_with_open_tracked_reports' => (int) ($counts?->works_with_open_tracked_reports ?? 0),
            'works_with_both_sources' => (int) ($counts?->works_with_both_sources ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function booleanFilter(WorksReportsRequest $request, array $validated, string $key): ?bool
    {
        if (! array_key_exists($key, $validated) || $validated[$key] === null) {
            return null;
        }

        return $request->boolean($key);
    }

    /**
     * @return array<string, mixed>
     */
    private function workPayload(Work $work): array
    {
        $legacyCount = (int) $work->reports_count;
        $trackedCount = (int) $work->tracked_reports_count;
        $pendingCount = (int) $work->pending_tracked_reports_count;
        $underReviewCount = (int) $work->under_review_tracked_reports_count;
        $dismissedCount = (int) $work->dismissed_tracked_reports_count;
        $archivedCount = (int) $work->archived_tracked_reports_count;
        $openCount = $pendingCount + $underReviewCount;
        $combinedSignalCount = $legacyCount + $trackedCount;
        $hasReports = $combinedSignalCount > 0;
        $visibilityRisk = $hasReports && $work->visibility_status === Work::VISIBILITY_PUBLIC;
        $needsAttention = $visibilityRisk
            || $openCount > 0
            || $work->status === Work::STATUS_PUBLISHED
            || $work->is_featured
            || $work->is_pinned;

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
            'is_featured' => $work->is_featured,
            'is_pinned' => $work->is_pinned,
            'reports_count' => $legacyCount,
            'views_count' => $work->views_count,
            'likes_count' => $work->likes_count,
            'submitted_at' => $work->submitted_at?->toJSON(),
            'published_at' => $work->published_at?->toJSON(),
            'hidden_at' => $work->hidden_at?->toJSON(),
            'updated_at' => $work->updated_at?->toJSON(),
            'created_at' => $work->created_at?->toJSON(),
            'report_tracking' => [
                'legacy_count' => $legacyCount,
                'tracked_count' => $trackedCount,
                'combined_signal_count' => $combinedSignalCount,
                'pending_count' => $pendingCount,
                'under_review_count' => $underReviewCount,
                'dismissed_count' => $dismissedCount,
                'archived_count' => $archivedCount,
                'open_count' => $openCount,
                'has_legacy_untracked' => $legacyCount > 0,
                'has_tracked' => $trackedCount > 0,
                'has_open_tracked' => $openCount > 0,
            ],
            'report_flags' => [
                'has_reports' => $hasReports,
                'high_reports' => $combinedSignalCount >= 5,
                'visibility_risk' => $visibilityRisk,
                'needs_attention' => $needsAttention,
            ],
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
