<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksShowRequest;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkTag;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;

class WorksShowController extends Controller
{
    public function show(WorksShowRequest $request, Work $work): JsonResponse
    {
        $viewer = $request->user();
        $isSuperAdmin = (bool) $viewer?->hasRole('super-admin');
        $fieldAccess = [
            'can_view_designer' => $isSuperAdmin || (bool) $viewer?->can('admin.works.designer.view'),
            'can_view_media' => $isSuperAdmin || (bool) $viewer?->can('admin.works.media.view'),
            'can_view_metadata' => $isSuperAdmin || (bool) $viewer?->can('admin.works.metadata.view'),
            'can_view_private_notes' => $isSuperAdmin || (bool) $viewer?->can('admin.works.private_notes.view'),
        ];
        $isInternal = (bool) $viewer?->hasAnyRole(['admin', 'staff']);
        $taxonomyAccess = [
            'can_view_category' => $isSuperAdmin || ($isInternal
                && (bool) $viewer?->can('admin.works.taxonomy.view')
                && (bool) $viewer?->can('admin.works.taxonomy.categories.view')),
            'can_view_tags' => $isSuperAdmin || ($isInternal
                && (bool) $viewer?->can('admin.works.taxonomy.view')
                && (bool) $viewer?->can('admin.works.taxonomy.tags.view')),
        ];

        // لا نحمّل مراجع المستخدمين إلا إذا سمحت الصلاحية بعرضها.
        if ($fieldAccess['can_view_designer']) {
            $work->load([
                'designer:id,name',
                'reviewer:id,name',
            ]);
        }

        if ($taxonomyAccess['can_view_category']) {
            $work->load('category:id,name_ar,name_en,slug,disabled_at,sort_order');
        }

        if ($taxonomyAccess['can_view_tags']) {
            $work->load(['tags' => fn (BelongsToMany $query) => $query
                ->select(['work_tags.id', 'name_ar', 'name_en', 'slug', 'disabled_at', 'sort_order'])
                ->orderBy('sort_order')
                ->orderBy('work_tags.id')]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'work' => $this->workPayload($work),
                'taxonomy' => $this->taxonomyPayload(
                    $work,
                    $taxonomyAccess['can_view_category'],
                    $taxonomyAccess['can_view_tags'],
                ),
                'relations' => [
                    'designer' => $fieldAccess['can_view_designer']
                        ? $this->userReference($work->designer)
                        : null,
                    'reviewer' => $fieldAccess['can_view_designer']
                        ? $this->userReference($work->reviewer)
                        : null,
                ],
                'media' => $fieldAccess['can_view_media']
                    ? [
                        'media_type' => $work->media_type,
                        'has_media' => filled($work->media_type),
                    ]
                    : null,
                'private_notes' => $fieldAccess['can_view_private_notes']
                    ? [
                        'internal_notes' => $work->internal_notes,
                        'rejection_reason' => $work->rejection_reason,
                        'change_request_notes' => $work->change_request_notes,
                    ]
                    : null,
                'field_access' => $fieldAccess,
                'taxonomy_access' => $taxonomyAccess,
            ],
            'message' => 'تم جلب تفاصيل العمل بنجاح',
            'errors' => null,
        ]);
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
            'price_amount' => $work->price_amount,
            'delivery_days' => $work->delivery_days,
            'category_id' => $work->category_id,
            'is_featured' => $work->is_featured,
            'is_pinned' => $work->is_pinned,
            'reports_count' => $work->reports_count,
            'views_count' => $work->views_count,
            'likes_count' => $work->likes_count,
            'submitted_at' => $work->submitted_at?->toJSON(),
            'reviewed_at' => $work->reviewed_at?->toJSON(),
            'approved_at' => $work->approved_at?->toJSON(),
            'published_at' => $work->published_at?->toJSON(),
            'rejected_at' => $work->rejected_at?->toJSON(),
            'hidden_at' => $work->hidden_at?->toJSON(),
            'archived_at' => $work->archived_at?->toJSON(),
            'updated_at' => $work->updated_at?->toJSON(),
            'created_at' => $work->created_at?->toJSON(),
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
