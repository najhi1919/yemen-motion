<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksIndexRequest;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkTag;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class WorksIndexController extends Controller
{
    public function index(WorksIndexRequest $request): JsonResponse
    {
        $viewer = $request->user();
        $isSuperAdmin = (bool) $viewer?->hasRole('super-admin');
        $isInternal = (bool) $viewer?->hasAnyRole(['admin', 'staff']);
        $taxonomyAccess = [
            'can_view_category' => $isSuperAdmin || ($isInternal
                && (bool) $viewer?->can('admin.works.taxonomy.view')
                && (bool) $viewer?->can('admin.works.taxonomy.categories.view')),
            'can_view_tags' => $isSuperAdmin || ($isInternal
                && (bool) $viewer?->can('admin.works.taxonomy.view')
                && (bool) $viewer?->can('admin.works.taxonomy.tags.view')),
        ];
        $validated = $request->validated();
        $queryText = trim((string) ($validated['q'] ?? ''));
        $sort = (string) ($validated['sort'] ?? 'created_at');
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

        // نحدد الأعمدة والعلاقات صراحة حتى لا تتسرب الحقول الداخلية من النماذج.
        $relations = [
            'designer:id,name',
            'reviewer:id,name',
        ];

        if ($taxonomyAccess['can_view_category']) {
            $relations[] = 'category:id,name_ar,name_en,slug,disabled_at,sort_order';
        }

        if ($taxonomyAccess['can_view_tags']) {
            $relations['tags'] = fn (Builder $query) => $query
                ->select(['work_tags.id', 'name_ar', 'name_en', 'slug', 'disabled_at', 'sort_order'])
                ->orderBy('sort_order')
                ->orderBy('work_tags.id');
        }

        $works = Work::query()
            ->select([
                'id',
                'title',
                'slug',
                'summary',
                'status',
                'visibility_status',
                'media_type',
                'price_amount',
                'delivery_days',
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
                'updated_at',
                'created_at',
            ])
            ->with($relations)
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
            ->when($from !== null, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->where('created_at', '<=', $to))
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->through(fn (Work $work): array => $this->workPayload(
                $work,
                $taxonomyAccess['can_view_category'],
                $taxonomyAccess['can_view_tags'],
            ));

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
                'taxonomy_access' => $taxonomyAccess,
            ],
            'message' => 'تم جلب قائمة الأعمال بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function booleanFilter(
        WorksIndexRequest $request,
        array $validated,
        string $key,
    ): ?bool {
        if (! array_key_exists($key, $validated) || $validated[$key] === null) {
            return null;
        }

        return $request->boolean($key);
    }

    /**
     * يعيد عقد القائمة الآمن فقط دون model كامل أو حقول داخلية.
     *
     * @return array<string, mixed>
     */
    private function workPayload(Work $work, bool $canViewCategory, bool $canViewTags): array
    {
        return [
            'id' => $work->id,
            'title' => $work->title,
            'slug' => $work->slug,
            'summary' => $work->summary,
            'status' => $work->status,
            'visibility_status' => $work->visibility_status,
            'media_type' => $work->media_type,
            'price_amount' => $work->price_amount,
            'delivery_days' => $work->delivery_days,
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
            'updated_at' => $work->updated_at?->toJSON(),
            'created_at' => $work->created_at?->toJSON(),
            'taxonomy' => $this->taxonomyPayload($work, $canViewCategory, $canViewTags),
        ];
    }

    /**
     * @return array{category: array<string, mixed>|null, category_tracking: array<string, bool>|null, tags: list<array<string, mixed>>|null}
     */
    private function taxonomyPayload(Work $work, bool $canViewCategory, bool $canViewTags): array
    {
        /** @var WorkCategory|null $category */
        $category = $canViewCategory ? $work->getRelation('category') : null;
        $categoryId = $work->category_id !== null ? (int) $work->category_id : null;

        /** @var list<array<string, mixed>>|null $tags */
        $tags = $canViewTags
            ? $work->getRelation('tags')
                ->map(fn (WorkTag $tag): array => $this->safeTag($tag))
                ->values()
                ->all()
            : null;

        return [
            'category' => $canViewCategory && $category ? $this->safeCategory($category) : null,
            'category_tracking' => $canViewCategory
                ? [
                    'catalog_record_exists' => $category !== null,
                    'is_legacy_unmapped' => $categoryId !== null && $category === null,
                    'is_uncategorized' => $categoryId === null,
                ]
                : null,
            'tags' => $tags,
        ];
    }

    /** @return array{id: int, name_ar: string, name_en: string, slug: string, disabled_at: string|null, is_active: bool, sort_order: int} */
    private function safeCategory(WorkCategory $category): array
    {
        return [
            'id' => (int) $category->id,
            'name_ar' => $category->name_ar,
            'name_en' => $category->name_en,
            'slug' => $category->slug,
            'disabled_at' => $category->disabled_at?->toJSON(),
            'is_active' => $category->isActive(),
            'sort_order' => (int) $category->sort_order,
        ];
    }

    /** @return array{id: int, name_ar: string, name_en: string, slug: string, disabled_at: string|null, is_active: bool, sort_order: int} */
    private function safeTag(WorkTag $tag): array
    {
        return [
            'id' => (int) $tag->id,
            'name_ar' => $tag->name_ar,
            'name_en' => $tag->name_en,
            'slug' => $tag->slug,
            'disabled_at' => $tag->disabled_at?->toJSON(),
            'is_active' => $tag->isActive(),
            'sort_order' => (int) $tag->sort_order,
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
