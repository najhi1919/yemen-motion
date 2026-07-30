<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designer\DesignerWorkMetadataShowRequest;
use App\Http\Requests\Designer\DesignerWorkMetadataUpdateRequest;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkTag;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DesignerWorksMetadataController extends Controller
{
    private const EDITABLE_STATUSES = [
        Work::STATUS_DRAFT,
        Work::STATUS_CHANGES_REQUESTED,
    ];

    public function __construct(
        private readonly AuditEventLogger $auditEventLogger,
    ) {
    }

    public function show(DesignerWorkMetadataShowRequest $request, int $work): JsonResponse
    {
        $ownedWork = $this->ownedWork($work, (int) $request->user()->getKey());
        $ownedWork->load(['category', 'tags']);

        return response()->json([
            'data' => $this->payload($ownedWork),
        ]);
    }

    public function update(DesignerWorkMetadataUpdateRequest $request, int $work): JsonResponse
    {
        $validated = $request->validated();
        $actor = $request->user();
        $requestContext = [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        $result = DB::transaction(function () use ($validated, $work, $actor, $requestContext): array {
            $locked = Work::query()
                ->whereKey($work)
                ->where('designer_id', $actor->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, self::EDITABLE_STATUSES, true)) {
                throw new HttpResponseException(response()->json([
                    'message' => 'لا يمكن تعديل بيانات التصنيف في حالة العمل الحالية.',
                    'data' => [
                        'code' => 'work_state_not_editable',
                        'current_status' => $locked->status,
                    ],
                ], 409));
            }

            $previousCategoryId = $locked->category_id;
            $previousTagIds = $locked->tags()
                ->orderBy('work_tags.id')
                ->pluck('work_tags.id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $categoryId = $validated['category_id'];
            $tagIds = array_values(array_unique(array_map('intval', $validated['tag_ids'])));
            sort($tagIds);

            if ($categoryId !== null && (int) $categoryId !== $previousCategoryId) {
                $category = WorkCategory::query()->whereKey($categoryId)->lockForUpdate()->first();

                if (! $category || $category->disabled_at !== null) {
                    throw ValidationException::withMessages([
                        'category_id' => ['يجب اختيار تصنيف فعال من الكتالوج.'],
                    ]);
                }
            }

            $tags = WorkTag::query()
                ->whereIn('id', $tagIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($tags->count() !== count($tagIds)) {
                throw ValidationException::withMessages([
                    'tag_ids' => ['أحد الوسوم المحددة غير موجود.'],
                ]);
            }

            foreach ($tagIds as $tagId) {
                if ($tags[$tagId]->disabled_at !== null && ! in_array($tagId, $previousTagIds, true)) {
                    throw ValidationException::withMessages([
                        'tag_ids' => ['لا يمكن إضافة وسم معطل.'],
                    ]);
                }
            }

            $addedTagIds = array_values(array_diff($tagIds, $previousTagIds));
            $removedTagIds = array_values(array_diff($previousTagIds, $tagIds));
            $changedKeys = [];

            if ($categoryId !== $previousCategoryId) {
                $changedKeys[] = 'category_id';
            }

            if ($addedTagIds !== [] || $removedTagIds !== []) {
                $changedKeys[] = 'tag_ids';
            }

            if ($changedKeys === []) {
                $locked->load(['category', 'tags']);

                return ['work' => $locked, 'changed' => false, 'changed_keys' => []];
            }

            if (in_array('category_id', $changedKeys, true)) {
                $locked->forceFill(['category_id' => $categoryId])->save();
            }

            if (in_array('tag_ids', $changedKeys, true)) {
                $locked->tags()->sync($tagIds);
                $locked->touch();
            }

            $this->auditEventLogger->record([
                'event_type' => 'works.designer.metadata.updated',
                'category' => 'works',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $actor->getKey(),
                'actor_role' => $actor->roles->first()?->name,
                'target_type' => 'work',
                'target_id' => $locked->getKey(),
                'action' => 'update_metadata',
                'outcome' => 'success',
                'ip_address' => $requestContext['ip_address'],
                'user_agent' => $requestContext['user_agent'],
                'metadata' => [
                    'work_id' => $locked->getKey(),
                    'changed_keys' => $changedKeys,
                    'previous_category_id' => $previousCategoryId,
                    'category_id' => $categoryId,
                    'added_tag_ids' => $addedTagIds,
                    'removed_tag_ids' => $removedTagIds,
                ],
            ]);

            $locked->load(['category', 'tags']);

            return ['work' => $locked, 'changed' => true, 'changed_keys' => $changedKeys];
        });

        return response()->json([
            'data' => [
                ...$this->payload($result['work']),
                'changed' => $result['changed'],
                'changed_keys' => $result['changed_keys'],
            ],
            'message' => $result['changed']
                ? 'تم حفظ التصنيف والوسوم.'
                : 'لا توجد تغييرات لحفظها.',
        ]);
    }

    private function ownedWork(int $workId, int $designerId): Work
    {
        return Work::query()
            ->whereKey($workId)
            ->where('designer_id', $designerId)
            ->firstOrFail();
    }

    private function payload(Work $work): array
    {
        $currentCategoryId = $work->category_id;
        $currentTagIds = $work->tags->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        $categoryOptions = WorkCategory::query()
            ->where(function ($query) use ($currentCategoryId): void {
                $query->whereNull('disabled_at');

                if ($currentCategoryId !== null) {
                    $query->orWhere('id', $currentCategoryId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $tagOptions = WorkTag::query()
            ->where(function ($query) use ($currentTagIds): void {
                $query->whereNull('disabled_at');

                if ($currentTagIds !== []) {
                    $query->orWhereIn('id', $currentTagIds);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $option = static fn ($item): array => [
            'id' => (int) $item->id,
            'name_ar' => $item->name_ar,
            'name_en' => $item->name_en,
            'slug' => $item->slug,
            'is_active' => $item->disabled_at === null,
        ];

        return [
            'work' => [
                'id' => (int) $work->id,
                'public_code' => $work->public_code,
                'status' => $work->status,
                'category_id' => $currentCategoryId,
                'tag_ids' => $currentTagIds,
                'category' => $work->category ? $option($work->category) : null,
                'tags' => $work->tags->map($option)->values()->all(),
            ],
            'options' => [
                'categories' => $categoryOptions->map($option)->values()->all(),
                'tags' => $tagOptions->map($option)->values()->all(),
            ],
            'metadata_state' => [
                'editable' => in_array($work->status, self::EDITABLE_STATUSES, true),
                'allowed_statuses' => self::EDITABLE_STATUSES,
                'max_tags' => 10,
                'category_tracking' => [
                    'catalog_record_exists' => $work->category !== null,
                    'is_legacy_unmapped' => $currentCategoryId !== null && $work->category === null,
                    'is_uncategorized' => $currentCategoryId === null,
                    'is_disabled' => $work->category !== null && $work->category->disabled_at !== null,
                ],
            ],
        ];
    }
}
