<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksCategoriesRequest;
use App\Http\Requests\Admin\WorksTagsRequest;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WorksTaxonomyCatalogController extends Controller
{
    public function categories(WorksCategoriesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $queryText = trim((string) ($validated['q'] ?? ''));
        $state = (string) ($validated['state'] ?? 'all');
        $sort = (string) ($validated['sort'] ?? 'sort_order');
        $direction = (string) ($validated['direction'] ?? 'asc');
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = WorkCategory::query()
            ->select([
                'id',
                'name_ar',
                'name_en',
                'slug',
                'disabled_at',
                'sort_order',
                'created_at',
                'updated_at',
            ])
            ->withCount('works');

        $this->applySearchAndState($query, $queryText, $state);
        $summary = $this->categorySummary(
            clone $query,
            $queryText === '' && $state === 'all',
        );
        $this->applyOrdering($query, $sort, $direction);

        $categories = $query
            ->paginate($perPage)
            ->through(fn (WorkCategory $category): array => $this->categoryPayload($category));

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $categories->items(),
                'pagination' => $this->paginationPayload($categories),
                'summary' => $summary,
                'filters' => [
                    'states' => WorksCategoriesRequest::STATES,
                    'sorts' => WorksCategoriesRequest::SORTS,
                    'directions' => ['asc', 'desc'],
                    'per_page_options' => WorksCategoriesRequest::PER_PAGE_OPTIONS,
                    'default_state' => 'all',
                ],
            ],
            'message' => 'تم جلب كتالوج تصنيفات الأعمال بنجاح',
            'errors' => null,
        ]);
    }

    public function tags(WorksTagsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $queryText = trim((string) ($validated['q'] ?? ''));
        $state = (string) ($validated['state'] ?? 'all');
        $sort = (string) ($validated['sort'] ?? 'sort_order');
        $direction = (string) ($validated['direction'] ?? 'asc');
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = WorkTag::query()
            ->select([
                'id',
                'name_ar',
                'name_en',
                'slug',
                'disabled_at',
                'sort_order',
                'created_at',
                'updated_at',
            ])
            ->withCount('works');

        $this->applySearchAndState($query, $queryText, $state);
        $summary = $this->tagSummary(clone $query);
        $this->applyOrdering($query, $sort, $direction);

        $tags = $query
            ->paginate($perPage)
            ->through(fn (WorkTag $tag): array => $this->tagPayload($tag));

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $tags->items(),
                'pagination' => $this->paginationPayload($tags),
                'summary' => $summary,
                'filters' => [
                    'states' => WorksTagsRequest::STATES,
                    'sorts' => WorksTagsRequest::SORTS,
                    'directions' => ['asc', 'desc'],
                    'per_page_options' => WorksTagsRequest::PER_PAGE_OPTIONS,
                    'default_state' => 'all',
                ],
            ],
            'message' => 'تم جلب كتالوج وسوم الأعمال بنجاح',
            'errors' => null,
        ]);
    }

    private function applySearchAndState(Builder $query, string $queryText, string $state): void
    {
        $query
            ->when($queryText !== '', function (Builder $query) use ($queryText): void {
                $query->where(function (Builder $searchQuery) use ($queryText): void {
                    $searchQuery
                        ->where('name_ar', 'like', "%{$queryText}%")
                        ->orWhere('name_en', 'like', "%{$queryText}%")
                        ->orWhere('slug', 'like', "%{$queryText}%");
                });
            })
            ->when($state === 'active', fn (Builder $query) => $query->whereNull('disabled_at'))
            ->when($state === 'disabled', fn (Builder $query) => $query->whereNotNull('disabled_at'));
    }

    private function applyOrdering(Builder $query, string $sort, string $direction): void
    {
        $query->orderBy($sort, $direction)->orderBy('id');
    }

    /** @return array<string, int> */
    private function categorySummary(Builder $filteredQuery, bool $includeLegacyUnmapped): array
    {
        $counts = DB::query()
            ->fromSub($filteredQuery->toBase(), 'filtered_work_categories')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(CASE WHEN disabled_at IS NULL THEN 1 ELSE 0 END), 0) AS active')
            ->selectRaw('COALESCE(SUM(CASE WHEN disabled_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS disabled')
            ->selectRaw('COALESCE(SUM(CASE WHEN works_count > 0 THEN 1 ELSE 0 END), 0) AS used')
            ->selectRaw('COALESCE(SUM(CASE WHEN works_count = 0 THEN 1 ELSE 0 END), 0) AS unused')
            ->first();
        $legacy = $includeLegacyUnmapped
            ? $this->legacyUnmappedCounts()
            : ['category_ids' => 0, 'works' => 0];

        return [
            'total' => (int) ($counts?->total ?? 0),
            'active' => (int) ($counts?->active ?? 0),
            'disabled' => (int) ($counts?->disabled ?? 0),
            'used' => (int) ($counts?->used ?? 0),
            'unused' => (int) ($counts?->unused ?? 0),
            'legacy_unmapped_category_ids' => $legacy['category_ids'],
            'works_with_legacy_unmapped_category' => $legacy['works'],
        ];
    }

    /** @return array<string, int> */
    private function tagSummary(Builder $filteredQuery): array
    {
        $counts = DB::query()
            ->fromSub($filteredQuery->toBase(), 'filtered_work_tags')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(CASE WHEN disabled_at IS NULL THEN 1 ELSE 0 END), 0) AS active')
            ->selectRaw('COALESCE(SUM(CASE WHEN disabled_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS disabled')
            ->selectRaw('COALESCE(SUM(CASE WHEN works_count > 0 THEN 1 ELSE 0 END), 0) AS used')
            ->selectRaw('COALESCE(SUM(CASE WHEN works_count = 0 THEN 1 ELSE 0 END), 0) AS unused')
            ->selectRaw('COALESCE(SUM(works_count), 0) AS assignments_total')
            ->first();

        return [
            'total' => (int) ($counts?->total ?? 0),
            'active' => (int) ($counts?->active ?? 0),
            'disabled' => (int) ($counts?->disabled ?? 0),
            'used' => (int) ($counts?->used ?? 0),
            'unused' => (int) ($counts?->unused ?? 0),
            'assignments_total' => (int) ($counts?->assignments_total ?? 0),
        ];
    }

    /** @return array{category_ids: int, works: int} */
    private function legacyUnmappedCounts(): array
    {
        $unmappedWorks = Work::query()
            ->whereNotNull('category_id')
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('work_categories')
                    ->whereColumn('work_categories.id', 'works.category_id');
            });

        return [
            'category_ids' => (clone $unmappedWorks)->distinct()->count('category_id'),
            'works' => $unmappedWorks->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function categoryPayload(WorkCategory $category): array
    {
        $worksCount = (int) $category->getAttribute('works_count');

        return [
            'id' => $category->id,
            'name_ar' => $category->name_ar,
            'name_en' => $category->name_en,
            'slug' => $category->slug,
            'disabled_at' => $category->disabled_at?->toJSON(),
            'is_active' => $category->isActive(),
            'sort_order' => $category->sort_order,
            'works_count' => $worksCount,
            'created_at' => $category->created_at?->toJSON(),
            'updated_at' => $category->updated_at?->toJSON(),
            'category_flags' => [
                'is_used' => $worksCount > 0,
                'is_unused' => $worksCount === 0,
                'is_disabled' => ! $category->isActive(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function tagPayload(WorkTag $tag): array
    {
        $worksCount = (int) $tag->getAttribute('works_count');

        return [
            'id' => $tag->id,
            'name_ar' => $tag->name_ar,
            'name_en' => $tag->name_en,
            'slug' => $tag->slug,
            'disabled_at' => $tag->disabled_at?->toJSON(),
            'is_active' => $tag->isActive(),
            'sort_order' => $tag->sort_order,
            'works_count' => $worksCount,
            'created_at' => $tag->created_at?->toJSON(),
            'updated_at' => $tag->updated_at?->toJSON(),
            'tag_flags' => [
                'is_used' => $worksCount > 0,
                'is_unused' => $worksCount === 0,
                'is_disabled' => ! $tag->isActive(),
            ],
        ];
    }

    /** @return array<string, int> */
    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
