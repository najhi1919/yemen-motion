<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksTaxonomyRequest;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkTag;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WorksTaxonomyController extends Controller
{
    public function index(WorksTaxonomyRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $queryText = trim((string) ($validated['q'] ?? ''));
        $sort = (string) ($validated['sort'] ?? 'works_count');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $perPage = (int) ($validated['per_page'] ?? 15);
        $onlyUncategorized = $this->booleanFilter($request, $validated, 'only_uncategorized');
        $onlyReported = $this->booleanFilter($request, $validated, 'only_reported');
        $onlyPromoted = $this->booleanFilter($request, $validated, 'only_promoted');
        $from = filled($validated['from'] ?? null)
            ? Carbon::parse((string) $validated['from'])->startOfDay()
            : null;
        $to = filled($validated['to'] ?? null)
            ? Carbon::parse((string) $validated['to'])->endOfDay()
            : null;

        $worksQuery = $this->worksQuery(
            $validated,
            $onlyUncategorized,
            $onlyReported,
            $onlyPromoted,
            $from,
            $to,
        );

        // البحث يطابق تسمية bucket المحسوبة فقط، ولا يصل إلى محتوى الأعمال النصي.
        $this->applyTaxonomySearch($worksQuery, $queryText);

        $bucketsQuery = $this->bucketsQuery($worksQuery);
        $catalogSummary = $this->catalogSummary();
        $summary = [
            ...$this->summary(clone $bucketsQuery),
            ...$catalogSummary,
        ];
        $orderedBuckets = clone $bucketsQuery;

        if ($sort === 'category_id') {
            $orderedBuckets
                ->orderByRaw('CASE WHEN category_id IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('category_id', $direction);
        } else {
            $orderedBuckets
                ->orderBy($sort, $direction)
                ->orderByRaw('CASE WHEN category_id IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('category_id');
        }

        $buckets = $orderedBuckets->paginate($perPage);
        $categoryIds = collect($buckets->items())
            ->pluck('category_id')
            ->filter(fn (mixed $categoryId): bool => $categoryId !== null)
            ->unique()
            ->values();
        $categories = WorkCategory::query()
            ->select(['id', 'name_ar', 'name_en', 'slug', 'disabled_at', 'sort_order'])
            ->whereIn('id', $categoryIds)
            ->get()
            ->keyBy('id');
        $items = collect($buckets->items())
            ->map(fn (Work $bucket): array => $this->bucketPayload(
                $bucket,
                $bucket->category_id === null
                    ? null
                    : $categories->get((int) $bucket->category_id),
            ))
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'pagination' => [
                    'current_page' => $buckets->currentPage(),
                    'per_page' => $buckets->perPage(),
                    'total' => $buckets->total(),
                    'last_page' => $buckets->lastPage(),
                ],
                'summary' => $summary,
                'filters' => [
                    'q' => $queryText !== '' ? $queryText : null,
                    'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
                    'status' => $validated['status'] ?? null,
                    'visibility_status' => $validated['visibility_status'] ?? null,
                    'media_type' => $validated['media_type'] ?? null,
                    'only_uncategorized' => $onlyUncategorized,
                    'only_reported' => $onlyReported,
                    'only_promoted' => $onlyPromoted,
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                    'sort' => $sort,
                    'direction' => $direction,
                ],
                'category_support' => [
                    'available' => true,
                    'catalog_source' => 'work_categories',
                    'work_reference' => 'works.category_id',
                    'foreign_key_enforced' => false,
                    'legacy_unmapped_values_possible' => true,
                    'mapping_complete' => $catalogSummary['legacy_unmapped_category_ids'] === 0,
                    'reason' => 'قد توجد قيم category_id قديمة لا يقابلها سجل في work_categories.',
                ],
                'tag_support' => [
                    'available' => true,
                    'catalog_source' => 'work_tags',
                    'assignments_source' => 'work_tag_assignments',
                    'reason' => 'بنية الوسوم المستقلة متاحة للقراءة.',
                ],
            ],
            'message' => 'تم جلب تصنيفات الأعمال بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function worksQuery(
        array $validated,
        ?bool $onlyUncategorized,
        ?bool $onlyReported,
        ?bool $onlyPromoted,
        ?Carbon $from,
        ?Carbon $to,
    ): Builder {
        return Work::query()
            ->when(
                isset($validated['category_id']),
                fn (Builder $query) => $query->where('category_id', $validated['category_id']),
            )
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
                $onlyUncategorized === true,
                fn (Builder $query) => $query->whereNull('category_id'),
            )
            ->when(
                $onlyUncategorized === false,
                fn (Builder $query) => $query->whereNotNull('category_id'),
            )
            ->when(
                $onlyReported === true,
                fn (Builder $query) => $query->where('reports_count', '>', 0),
            )
            ->when(
                $onlyPromoted === true,
                fn (Builder $query) => $query->where(function (Builder $promotionQuery): void {
                    $promotionQuery
                        ->where('is_featured', true)
                        ->orWhere('is_pinned', true);
                }),
            )
            ->when($from !== null, fn (Builder $query) => $query->where('updated_at', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->where('updated_at', '<=', $to));
    }

    private function applyTaxonomySearch(Builder $query, string $queryText): void
    {
        if ($queryText === '') {
            return;
        }

        $matchesUncategorized = mb_stripos('غير مصنف', $queryText, 0, 'UTF-8') !== false;
        $matchesEveryCategorizedBucket = mb_stripos('تصنيف #', $queryText, 0, 'UTF-8') !== false;
        $matchingCategoryIds = [];

        if (! $matchesEveryCategorizedBucket) {
            $candidateCategoryIds = (clone $query)
                ->whereNotNull('category_id')
                ->distinct()
                ->pluck('category_id');

            foreach ($candidateCategoryIds as $categoryId) {
                $categoryId = (int) $categoryId;

                if (mb_stripos($this->categoryLabel($categoryId), $queryText, 0, 'UTF-8') !== false) {
                    $matchingCategoryIds[] = $categoryId;
                }
            }
        }

        $query->where(function (Builder $bucketQuery) use (
            $matchesUncategorized,
            $matchesEveryCategorizedBucket,
            $matchingCategoryIds,
        ): void {
            $hasCategorizedMatch = $matchesEveryCategorizedBucket || $matchingCategoryIds !== [];

            if ($matchesEveryCategorizedBucket) {
                $bucketQuery->whereNotNull('category_id');
            } elseif ($matchingCategoryIds !== []) {
                $bucketQuery->whereIn('category_id', $matchingCategoryIds);
            }

            if ($matchesUncategorized) {
                $hasCategorizedMatch
                    ? $bucketQuery->orWhereNull('category_id')
                    : $bucketQuery->whereNull('category_id');
            }

            if (! $hasCategorizedMatch && ! $matchesUncategorized) {
                $bucketQuery->whereRaw('1 = 0');
            }
        });
    }

    private function bucketsQuery(Builder $query): Builder
    {
        return $query
            ->select('category_id')
            ->selectRaw('COUNT(*) AS works_count')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS published_count',
                [Work::STATUS_PUBLISHED],
            )
            ->selectRaw(
                'SUM(CASE WHEN visibility_status = ? OR status = ? THEN 1 ELSE 0 END) AS hidden_count',
                [Work::VISIBILITY_HIDDEN, Work::STATUS_HIDDEN],
            )
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) AS review_queue_count',
                [
                    Work::STATUS_SUBMITTED,
                    Work::STATUS_IN_REVIEW,
                    Work::STATUS_CHANGES_REQUESTED,
                ],
            )
            ->selectRaw('SUM(CASE WHEN reports_count > 0 THEN 1 ELSE 0 END) AS reported_count')
            ->selectRaw('SUM(CASE WHEN is_featured THEN 1 ELSE 0 END) AS featured_count')
            ->selectRaw('SUM(CASE WHEN is_pinned THEN 1 ELSE 0 END) AS pinned_count')
            ->selectRaw('COALESCE(SUM(reports_count), 0) AS total_reports')
            ->selectRaw('COALESCE(SUM(views_count), 0) AS total_views')
            ->selectRaw('COALESCE(SUM(likes_count), 0) AS total_likes')
            ->selectRaw('MAX(updated_at) AS latest_work_at')
            ->groupBy('category_id');
    }

    /**
     * @return array<string, int>
     */
    private function summary(Builder $bucketsQuery): array
    {
        $counts = DB::query()
            ->fromSub($bucketsQuery->toBase(), 'taxonomy_buckets')
            ->selectRaw('COUNT(*) AS total_categories')
            ->selectRaw('COALESCE(SUM(CASE WHEN category_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS categorized_categories')
            ->selectRaw('COALESCE(SUM(CASE WHEN category_id IS NULL THEN 1 ELSE 0 END), 0) AS uncategorized_buckets')
            ->selectRaw('COALESCE(SUM(works_count), 0) AS total_works')
            ->selectRaw('COALESCE(SUM(CASE WHEN category_id IS NOT NULL THEN works_count ELSE 0 END), 0) AS categorized_works')
            ->selectRaw('COALESCE(SUM(CASE WHEN category_id IS NULL THEN works_count ELSE 0 END), 0) AS uncategorized_works')
            ->selectRaw('COALESCE(SUM(CASE WHEN reported_count > 0 THEN 1 ELSE 0 END), 0) AS reported_categories')
            ->selectRaw('COALESCE(SUM(CASE WHEN featured_count > 0 OR pinned_count > 0 THEN 1 ELSE 0 END), 0) AS promoted_categories')
            ->selectRaw('COALESCE(SUM(CASE WHEN published_count > 0 THEN 1 ELSE 0 END), 0) AS published_categories')
            ->selectRaw('COALESCE(SUM(CASE WHEN hidden_count > 0 THEN 1 ELSE 0 END), 0) AS hidden_categories')
            ->selectRaw('COALESCE(SUM(total_reports), 0) AS total_reports')
            ->selectRaw('COALESCE(SUM(total_views), 0) AS total_views')
            ->selectRaw('COALESCE(SUM(total_likes), 0) AS total_likes')
            ->first();

        return [
            'total_categories' => (int) ($counts?->total_categories ?? 0),
            'categorized_categories' => (int) ($counts?->categorized_categories ?? 0),
            'uncategorized_buckets' => (int) ($counts?->uncategorized_buckets ?? 0),
            'total_works' => (int) ($counts?->total_works ?? 0),
            'categorized_works' => (int) ($counts?->categorized_works ?? 0),
            'uncategorized_works' => (int) ($counts?->uncategorized_works ?? 0),
            'reported_categories' => (int) ($counts?->reported_categories ?? 0),
            'promoted_categories' => (int) ($counts?->promoted_categories ?? 0),
            'published_categories' => (int) ($counts?->published_categories ?? 0),
            'hidden_categories' => (int) ($counts?->hidden_categories ?? 0),
            'total_reports' => (int) ($counts?->total_reports ?? 0),
            'total_views' => (int) ($counts?->total_views ?? 0),
            'total_likes' => (int) ($counts?->total_likes ?? 0),
        ];
    }

    /** @return array<string, int> */
    private function catalogSummary(): array
    {
        $categoryCatalog = WorkCategory::query()
            ->select(['id', 'disabled_at'])
            ->withCount('works');
        $categoryCounts = DB::query()
            ->fromSub($categoryCatalog->toBase(), 'catalog_work_categories')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(CASE WHEN disabled_at IS NULL THEN 1 ELSE 0 END), 0) AS active')
            ->selectRaw('COALESCE(SUM(CASE WHEN disabled_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS disabled')
            ->selectRaw('COALESCE(SUM(CASE WHEN works_count > 0 THEN 1 ELSE 0 END), 0) AS used')
            ->first();
        $tagCatalog = WorkTag::query()
            ->select(['id', 'disabled_at'])
            ->withCount('works');
        $tagCounts = DB::query()
            ->fromSub($tagCatalog->toBase(), 'catalog_work_tags')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(CASE WHEN disabled_at IS NULL THEN 1 ELSE 0 END), 0) AS active')
            ->selectRaw('COALESCE(SUM(CASE WHEN disabled_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS disabled')
            ->selectRaw('COALESCE(SUM(CASE WHEN works_count > 0 THEN 1 ELSE 0 END), 0) AS used')
            ->first();
        $unmappedWorks = Work::query()
            ->whereNotNull('category_id')
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('work_categories')
                    ->whereColumn('work_categories.id', 'works.category_id');
            });
        $categoryTotal = (int) ($categoryCounts?->total ?? 0);
        $usedCategoryTotal = (int) ($categoryCounts?->used ?? 0);
        $tagTotal = (int) ($tagCounts?->total ?? 0);
        $usedTagTotal = (int) ($tagCounts?->used ?? 0);

        return [
            'catalog_categories_total' => $categoryTotal,
            'active_catalog_categories' => (int) ($categoryCounts?->active ?? 0),
            'disabled_catalog_categories' => (int) ($categoryCounts?->disabled ?? 0),
            'used_catalog_categories' => $usedCategoryTotal,
            'unused_catalog_categories' => $categoryTotal - $usedCategoryTotal,
            'legacy_unmapped_category_ids' => (clone $unmappedWorks)->distinct()->count('category_id'),
            'works_with_legacy_unmapped_category' => $unmappedWorks->count(),
            'tags_total' => $tagTotal,
            'active_tags' => (int) ($tagCounts?->active ?? 0),
            'disabled_tags' => (int) ($tagCounts?->disabled ?? 0),
            'used_tags' => $usedTagTotal,
            'unused_tags' => $tagTotal - $usedTagTotal,
            'tag_assignments_total' => DB::table('work_tag_assignments')->count(),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function booleanFilter(
        WorksTaxonomyRequest $request,
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
    private function bucketPayload(Work $bucket, ?WorkCategory $category): array
    {
        $categoryId = $bucket->category_id;
        $reportedCount = (int) $bucket->getAttribute('reported_count');
        $publishedCount = (int) $bucket->getAttribute('published_count');
        $hiddenCount = (int) $bucket->getAttribute('hidden_count');
        $featuredCount = (int) $bucket->getAttribute('featured_count');
        $pinnedCount = (int) $bucket->getAttribute('pinned_count');
        $uncategorized = $categoryId === null;
        $hasReports = $reportedCount > 0;
        $hasHidden = $hiddenCount > 0;
        $isPromoted = $featuredCount > 0 || $pinnedCount > 0;
        $latestWorkAt = $bucket->getAttribute('latest_work_at');

        return [
            'category_id' => $categoryId,
            'label' => $uncategorized
                ? 'غير مصنف'
                : ($category?->name_ar ?? $this->categoryLabel($categoryId)),
            'category' => $this->categoryPayload($category),
            'category_tracking' => [
                'catalog_record_exists' => $category !== null,
                'is_legacy_unmapped' => ! $uncategorized && $category === null,
                'is_uncategorized' => $uncategorized,
            ],
            'works_count' => (int) $bucket->getAttribute('works_count'),
            'published_count' => $publishedCount,
            'hidden_count' => $hiddenCount,
            'review_queue_count' => (int) $bucket->getAttribute('review_queue_count'),
            'reported_count' => $reportedCount,
            'featured_count' => $featuredCount,
            'pinned_count' => $pinnedCount,
            'total_reports' => (int) $bucket->getAttribute('total_reports'),
            'total_views' => (int) $bucket->getAttribute('total_views'),
            'total_likes' => (int) $bucket->getAttribute('total_likes'),
            'latest_work_at' => filled($latestWorkAt)
                ? Carbon::parse((string) $latestWorkAt)->toJSON()
                : null,
            'taxonomy_flags' => [
                'uncategorized' => $uncategorized,
                'has_reports' => $hasReports,
                'has_published' => $publishedCount > 0,
                'has_hidden' => $hasHidden,
                'is_promoted' => $isPromoted,
                'needs_attention' => $hasReports || $uncategorized || $hasHidden,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function categoryPayload(?WorkCategory $category): ?array
    {
        if (! $category) {
            return null;
        }

        return [
            'id' => $category->id,
            'name_ar' => $category->name_ar,
            'name_en' => $category->name_en,
            'slug' => $category->slug,
            'disabled_at' => $category->disabled_at?->toJSON(),
            'is_active' => $category->isActive(),
            'sort_order' => $category->sort_order,
        ];
    }

    private function categoryLabel(int $categoryId): string
    {
        return 'تصنيف #'.$categoryId;
    }
}
