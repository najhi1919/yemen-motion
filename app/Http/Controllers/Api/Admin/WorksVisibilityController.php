<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksVisibilityRequest;
use App\Models\User;
use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class WorksVisibilityController extends Controller
{
    public function index(WorksVisibilityRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $queryText = trim((string) ($validated['q'] ?? ''));
        $sort = (string) ($validated['sort'] ?? 'updated_at');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $perPage = (int) ($validated['per_page'] ?? 15);
        $isFeatured = $this->booleanFilter($request, $validated, 'is_featured');
        $isPinned = $this->booleanFilter($request, $validated, 'is_pinned');
        $reported = $this->booleanFilter($request, $validated, 'reported');
        $from = filled($validated['from'] ?? null)
            ? Carbon::parse((string) $validated['from'])->startOfDay()
            : null;
        $to = filled($validated['to'] ?? null)
            ? Carbon::parse((string) $validated['to'])->endOfDay()
            : null;

        // يُستنسخ نطاق الفلاتر نفسه للملخص والقائمة حتى تعكس الأرقام النتائج الحالية.
        $visibilityQuery = $this->visibilityQuery(
            $validated,
            $queryText,
            $isFeatured,
            $isPinned,
            $reported,
            $from,
            $to,
        );
        $summary = $this->summary(clone $visibilityQuery);

        $works = (clone $visibilityQuery)
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
                    'is_featured' => $isFeatured,
                    'is_pinned' => $isPinned,
                    'reported' => $reported,
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                    'sort' => $sort,
                    'direction' => $direction,
                ],
            ],
            'message' => 'تم جلب قائمة الظهور والتمييز بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function visibilityQuery(
        array $validated,
        string $queryText,
        ?bool $isFeatured,
        ?bool $isPinned,
        ?bool $reported,
        ?Carbon $from,
        ?Carbon $to,
    ): Builder {
        return Work::query()
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
            ->when($reported !== null, function (Builder $query) use ($reported): void {
                $query->where('reports_count', $reported ? '>' : '=', 0);
            })
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
            ->selectRaw(
                'SUM(CASE WHEN visibility_status = ? THEN 1 ELSE 0 END) AS public_count',
                [Work::VISIBILITY_PUBLIC],
            )
            ->selectRaw(
                'SUM(CASE WHEN visibility_status = ? THEN 1 ELSE 0 END) AS hidden_count',
                [Work::VISIBILITY_HIDDEN],
            )
            ->selectRaw('SUM(CASE WHEN is_featured THEN 1 ELSE 0 END) AS featured')
            ->selectRaw('SUM(CASE WHEN is_pinned THEN 1 ELSE 0 END) AS pinned')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS published',
                [Work::STATUS_PUBLISHED],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS hidden_status',
                [Work::STATUS_HIDDEN],
            )
            ->selectRaw('SUM(CASE WHEN reports_count > 0 THEN 1 ELSE 0 END) AS reported')
            ->selectRaw('SUM(CASE WHEN is_featured OR is_pinned THEN 1 ELSE 0 END) AS promoted')
            ->first();

        return [
            'total' => (int) ($counts?->total ?? 0),
            'public' => (int) ($counts?->public_count ?? 0),
            'hidden' => (int) ($counts?->hidden_count ?? 0),
            'featured' => (int) ($counts?->featured ?? 0),
            'pinned' => (int) ($counts?->pinned ?? 0),
            'published' => (int) ($counts?->published ?? 0),
            'hidden_status' => (int) ($counts?->hidden_status ?? 0),
            'reported' => (int) ($counts?->reported ?? 0),
            'promoted' => (int) ($counts?->promoted ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function booleanFilter(
        WorksVisibilityRequest $request,
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
            'published_at' => $work->published_at?->toJSON(),
            'hidden_at' => $work->hidden_at?->toJSON(),
            'updated_at' => $work->updated_at?->toJSON(),
            'created_at' => $work->created_at?->toJSON(),
            'visibility_flags' => [
                'is_public' => $work->visibility_status === Work::VISIBILITY_PUBLIC,
                'is_hidden' => $work->visibility_status === Work::VISIBILITY_HIDDEN
                    || $work->status === Work::STATUS_HIDDEN,
                'is_promoted' => $work->is_featured || $work->is_pinned,
                'has_reports' => $work->reports_count > 0,
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
