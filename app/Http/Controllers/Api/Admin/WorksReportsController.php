<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksReportsRequest;
use App\Models\User;
use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class WorksReportsController extends Controller
{
    public function index(WorksReportsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $queryText = trim((string) ($validated['q'] ?? ''));
        $minimumReports = (int) ($validated['min_reports'] ?? 1);
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

        // يُستخدم نطاق الفلاتر نفسه للقائمة والملخص حتى تبقى الأرقام متطابقة مع النتائج.
        $reportsQuery = $this->reportsQuery(
            $validated,
            $queryText,
            $minimumReports,
            $isFeatured,
            $isPinned,
            $from,
            $to,
        );
        $summary = $this->summary(clone $reportsQuery);

        $works = (clone $reportsQuery)
            ->select([
                'id',
                'title',
                'slug',
                'summary',
                'status',
                'visibility_status',
                'media_type',
                'designer_id',
                'reviewer_id',
                'category_id',
                'is_featured',
                'is_pinned',
                'reports_count',
                'views_count',
                'likes_count',
                'submitted_at',
                'published_at',
                'hidden_at',
                'updated_at',
                'created_at',
            ])
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
                    'is_featured' => $isFeatured,
                    'is_pinned' => $isPinned,
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                    'sort' => $sort,
                    'direction' => $direction,
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
        ?bool $isFeatured,
        ?bool $isPinned,
        ?Carbon $from,
        ?Carbon $to,
    ): Builder {
        return Work::query()
            ->where('reports_count', '>=', $minimumReports)
            ->when($queryText !== '', function (Builder $query) use ($queryText): void {
                $query->where(function (Builder $searchQuery) use ($queryText): void {
                    $searchQuery
                        ->where('title', 'like', "%{$queryText}%")
                        ->orWhere('slug', 'like', "%{$queryText}%")
                        ->orWhere('summary', 'like', "%{$queryText}%");
                });
            })
            ->when(
                filled($validated['status'] ?? null),
                fn (Builder $query) => $query->where('status', $validated['status']),
            )
            ->when(
                filled($validated['visibility_status'] ?? null),
                fn (Builder $query) => $query->where('visibility_status', $validated['visibility_status']),
            )
            ->when(
                filled($validated['media_type'] ?? null),
                fn (Builder $query) => $query->where('media_type', $validated['media_type']),
            )
            ->when(
                isset($validated['designer_id']),
                fn (Builder $query) => $query->where('designer_id', $validated['designer_id']),
            )
            ->when(
                isset($validated['reviewer_id']),
                fn (Builder $query) => $query->where('reviewer_id', $validated['reviewer_id']),
            )
            ->when(
                isset($validated['category_id']),
                fn (Builder $query) => $query->where('category_id', $validated['category_id']),
            )
            ->when(
                $isFeatured !== null,
                fn (Builder $query) => $query->where('is_featured', $isFeatured),
            )
            ->when(
                $isPinned !== null,
                fn (Builder $query) => $query->where('is_pinned', $isPinned),
            )
            ->when($from !== null, fn (Builder $query) => $query->where('updated_at', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->where('updated_at', '<=', $to));
    }

    /**
     * @return array<string, int>
     */
    private function summary(Builder $query): array
    {
        $counts = $query
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN reports_count >= 5 THEN 1 ELSE 0 END) AS high_reports')
            ->selectRaw(
                'SUM(CASE WHEN visibility_status = ? THEN 1 ELSE 0 END) AS public_reported',
                [Work::VISIBILITY_PUBLIC],
            )
            ->selectRaw(
                'SUM(CASE WHEN visibility_status = ? OR status = ? THEN 1 ELSE 0 END) AS hidden_reported',
                [Work::VISIBILITY_HIDDEN, Work::STATUS_HIDDEN],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS published_reported',
                [Work::STATUS_PUBLISHED],
            )
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) AS review_queue_reported',
                [
                    Work::STATUS_SUBMITTED,
                    Work::STATUS_IN_REVIEW,
                    Work::STATUS_CHANGES_REQUESTED,
                ],
            )
            ->selectRaw('SUM(CASE WHEN is_featured THEN 1 ELSE 0 END) AS featured_reported')
            ->selectRaw('SUM(CASE WHEN is_pinned THEN 1 ELSE 0 END) AS pinned_reported')
            ->selectRaw('COALESCE(SUM(reports_count), 0) AS total_reports')
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
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function booleanFilter(
        WorksReportsRequest $request,
        array $validated,
        string $key,
    ): ?bool {
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
        $hasReports = $work->reports_count > 0;
        $visibilityRisk = $hasReports
            && $work->visibility_status === Work::VISIBILITY_PUBLIC;
        $needsAttention = $hasReports && (
            $visibilityRisk
            || $work->status === Work::STATUS_PUBLISHED
            || $work->is_featured
            || $work->is_pinned
        );

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
            'reports_count' => $work->reports_count,
            'views_count' => $work->views_count,
            'likes_count' => $work->likes_count,
            'submitted_at' => $work->submitted_at?->toJSON(),
            'published_at' => $work->published_at?->toJSON(),
            'hidden_at' => $work->hidden_at?->toJSON(),
            'updated_at' => $work->updated_at?->toJSON(),
            'created_at' => $work->created_at?->toJSON(),
            'report_flags' => [
                'has_reports' => $hasReports,
                'high_reports' => $work->reports_count >= 5,
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

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
