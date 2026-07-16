<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUpdateWorkCategoryAssignmentRequest;
use App\Http\Requests\Admin\BulkUpdateWorkTagsAssignmentRequest;
use App\Http\Requests\Admin\UpdateWorkCategoryAssignmentRequest;
use App\Http\Requests\Admin\UpdateWorkTagsAssignmentRequest;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkTag;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorksTaxonomyAssignmentController extends Controller
{
    public function __construct(private readonly AuditEventLogger $auditEventLogger) {}

    public function updateCategory(UpdateWorkCategoryAssignmentRequest $request, string $work): JsonResponse
    {
        $categoryId = $this->nullableInteger($request->validated('category_id'));

        $result = DB::transaction(function () use ($request, $work, $categoryId): array {
            $currentWork = Work::query()->whereKey((int) $work)->lockForUpdate()->firstOrFail();
            $category = $this->lockActiveCategory($categoryId);
            $previousCategoryId = $this->nullableInteger($currentWork->category_id);
            $changed = $previousCategoryId !== $categoryId;

            if ($changed) {
                $currentWork->category_id = $categoryId;
                $currentWork->save();
                $this->recordCategoryAudit($request, $currentWork, $previousCategoryId, $categoryId, 'individual');
            }

            return [
                'work' => $currentWork,
                'category' => $category,
                'previous_category_id' => $previousCategoryId,
                'changed' => $changed,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'work' => [
                    'id' => $result['work']->getKey(),
                    'previous_category_id' => $result['previous_category_id'],
                    'category_id' => $categoryId,
                    'category' => $result['category'] ? $this->categoryPayload($result['category']) : null,
                ],
                'changed' => $result['changed'],
            ],
            'message' => $result['changed'] ? 'تم تحديث تصنيف العمل بنجاح' : 'لم يتغير تصنيف العمل',
            'errors' => null,
        ]);
    }

    public function updateTags(UpdateWorkTagsAssignmentRequest $request, string $work): JsonResponse
    {
        $requestedTagIds = $this->sortedIntegers($request->validated('tag_ids'));

        $result = DB::transaction(function () use ($request, $work, $requestedTagIds): array {
            $currentWork = Work::query()->whereKey((int) $work)->lockForUpdate()->firstOrFail();
            $previousTagIds = $this->currentTagIds($currentWork);
            $tags = $this->lockRequestedTags($requestedTagIds);
            $this->ensureDisabledTagsWereAlreadyAssigned($tags, [$currentWork->getKey() => $previousTagIds]);

            $addedTagIds = array_values(array_diff($requestedTagIds, $previousTagIds));
            $removedTagIds = array_values(array_diff($previousTagIds, $requestedTagIds));
            $changed = $addedTagIds !== [] || $removedTagIds !== [];

            if ($changed) {
                $currentWork->tags()->sync($requestedTagIds);
                $this->recordTagsAudit(
                    $request,
                    $currentWork,
                    $previousTagIds,
                    $requestedTagIds,
                    $addedTagIds,
                    $removedTagIds,
                    'individual',
                );
            }

            return compact('currentWork', 'previousTagIds', 'addedTagIds', 'removedTagIds', 'changed', 'tags');
        });

        return response()->json([
            'success' => true,
            'data' => [
                'work' => [
                    'id' => $result['currentWork']->getKey(),
                    'previous_tag_ids' => $result['previousTagIds'],
                    'tag_ids' => $requestedTagIds,
                    'added_tag_ids' => $result['addedTagIds'],
                    'removed_tag_ids' => $result['removedTagIds'],
                    'tags' => $result['tags']->map(fn (WorkTag $tag): array => $this->tagPayload($tag))->values()->all(),
                ],
                'changed' => $result['changed'],
            ],
            'message' => $result['changed'] ? 'تم تحديث وسوم العمل بنجاح' : 'لم تتغير وسوم العمل',
            'errors' => null,
        ]);
    }

    public function bulkUpdateCategory(BulkUpdateWorkCategoryAssignmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $workIds = $this->sortedIntegers($validated['work_ids']);
        $categoryId = $this->nullableInteger($validated['category_id']);

        $result = DB::transaction(function () use ($request, $workIds, $categoryId): array {
            $works = Work::query()->whereKey($workIds)->orderBy('id')->lockForUpdate()->get();
            $this->ensureAllWorksWereLocked($works, $workIds);
            $category = $this->lockActiveCategory($categoryId);
            $items = [];
            $changedCount = 0;

            foreach ($works as $work) {
                $previousCategoryId = $this->nullableInteger($work->category_id);
                $changed = $previousCategoryId !== $categoryId;

                if ($changed) {
                    $work->category_id = $categoryId;
                    $work->save();
                    $changedCount++;
                    $this->recordCategoryAudit(
                        $request,
                        $work,
                        $previousCategoryId,
                        $categoryId,
                        'bulk',
                        count($workIds),
                    );
                }

                $items[] = [
                    'work_id' => $work->getKey(),
                    'previous_category_id' => $previousCategoryId,
                    'category_id' => $categoryId,
                    'changed' => $changed,
                ];
            }

            return compact('items', 'category', 'changedCount');
        });

        $summary = $this->summary(count($workIds), $result['changedCount']);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $result['items'],
                'category' => $result['category'] ? $this->categoryPayload($result['category']) : null,
                'summary' => $summary,
                'changed' => $result['changedCount'] > 0,
            ],
            'message' => $result['changedCount'] > 0
                ? 'تم تحديث تصنيف الأعمال المحددة بنجاح'
                : 'لم تتغير تصنيفات الأعمال المحددة',
            'errors' => null,
        ]);
    }

    public function bulkUpdateTags(BulkUpdateWorkTagsAssignmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $workIds = $this->sortedIntegers($validated['work_ids']);
        $requestedTagIds = $this->sortedIntegers($validated['tag_ids']);

        $result = DB::transaction(function () use ($request, $workIds, $requestedTagIds): array {
            $works = Work::query()->whereKey($workIds)->orderBy('id')->lockForUpdate()->get();
            $this->ensureAllWorksWereLocked($works, $workIds);
            $currentAssignments = $this->currentTagIdsForWorks($workIds);
            $tags = $this->lockRequestedTags($requestedTagIds);
            $this->ensureDisabledTagsWereAlreadyAssigned($tags, $currentAssignments);

            $items = [];
            $changedCount = 0;

            foreach ($works as $work) {
                $previousTagIds = $currentAssignments[$work->getKey()];
                $addedTagIds = array_values(array_diff($requestedTagIds, $previousTagIds));
                $removedTagIds = array_values(array_diff($previousTagIds, $requestedTagIds));
                $changed = $addedTagIds !== [] || $removedTagIds !== [];

                if ($changed) {
                    $work->tags()->sync($requestedTagIds);
                    $changedCount++;
                    $this->recordTagsAudit(
                        $request,
                        $work,
                        $previousTagIds,
                        $requestedTagIds,
                        $addedTagIds,
                        $removedTagIds,
                        'bulk',
                        count($workIds),
                    );
                }

                $items[] = [
                    'work_id' => $work->getKey(),
                    'previous_tag_ids' => $previousTagIds,
                    'tag_ids' => $requestedTagIds,
                    'added_tag_ids' => $addedTagIds,
                    'removed_tag_ids' => $removedTagIds,
                    'changed' => $changed,
                ];
            }

            return compact('items', 'tags', 'changedCount');
        });

        $summary = $this->summary(count($workIds), $result['changedCount']);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $result['items'],
                'tags' => $result['tags']->map(fn (WorkTag $tag): array => $this->tagPayload($tag))->values()->all(),
                'summary' => $summary,
                'changed' => $result['changedCount'] > 0,
            ],
            'message' => $result['changedCount'] > 0
                ? 'تم تحديث وسوم الأعمال المحددة بنجاح'
                : 'لم تتغير وسوم الأعمال المحددة',
            'errors' => null,
        ]);
    }

    private function lockActiveCategory(?int $categoryId): ?WorkCategory
    {
        if ($categoryId === null) {
            return null;
        }

        $category = WorkCategory::query()->whereKey($categoryId)->lockForUpdate()->first();

        if (! $category || ! $category->isActive()) {
            throw ValidationException::withMessages([
                'category_id' => ['يجب اختيار تصنيف أعمال موجود وفعال.'],
            ]);
        }

        return $category;
    }

    /** @param list<int> $tagIds @return Collection<int, WorkTag> */
    private function lockRequestedTags(array $tagIds): Collection
    {
        if ($tagIds === []) {
            return collect();
        }

        $tags = WorkTag::query()
            ->whereKey($tagIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($tags->count() !== count($tagIds)) {
            throw ValidationException::withMessages([
                'tag_ids' => ['يجب أن تشير جميع الوسوم إلى سجلات موجودة.'],
            ]);
        }

        return $tags;
    }

    /** @param array<int, list<int>> $currentAssignments */
    private function ensureDisabledTagsWereAlreadyAssigned(Collection $tags, array $currentAssignments): void
    {
        $disabledTagIds = $tags->reject(fn (WorkTag $tag): bool => $tag->isActive())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        foreach ($disabledTagIds as $tagId) {
            foreach ($currentAssignments as $currentTagIds) {
                if (! in_array($tagId, $currentTagIds, true)) {
                    throw ValidationException::withMessages([
                        'tag_ids' => ['لا يمكن إنشاء إسناد جديد لوسم أعمال معطل.'],
                    ]);
                }
            }
        }
    }

    /** @return list<int> */
    private function currentTagIds(Work $work): array
    {
        return $work->tags()->orderBy('work_tags.id')->pluck('work_tags.id')
            ->map(fn (mixed $id): int => (int) $id)->all();
    }

    /** @param list<int> $workIds @return array<int, list<int>> */
    private function currentTagIdsForWorks(array $workIds): array
    {
        $assignments = array_fill_keys($workIds, []);
        $rows = DB::table('work_tag_assignments')
            ->whereIn('work_id', $workIds)
            ->orderBy('work_id')
            ->orderBy('work_tag_id')
            ->get(['work_id', 'work_tag_id']);

        foreach ($rows as $row) {
            $assignments[(int) $row->work_id][] = (int) $row->work_tag_id;
        }

        return $assignments;
    }

    /** @param Collection<int, Work> $works @param list<int> $workIds */
    private function ensureAllWorksWereLocked(Collection $works, array $workIds): void
    {
        if ($works->count() !== count($workIds)) {
            throw ValidationException::withMessages([
                'work_ids' => ['يجب أن تشير جميع معرفات الأعمال إلى سجلات موجودة.'],
            ]);
        }
    }

    private function recordCategoryAudit(
        FormRequest $request,
        Work $work,
        ?int $previousCategoryId,
        ?int $currentCategoryId,
        string $mode,
        ?int $requestedWorkCount = null,
    ): void {
        $metadata = [
            'work_id' => $work->getKey(),
            'previous_category_id' => $previousCategoryId,
            'current_category_id' => $currentCategoryId,
            'mode' => $mode,
        ];

        if ($requestedWorkCount !== null) {
            $metadata['requested_work_count'] = $requestedWorkCount;
        }

        $this->recordAudit($request, $work, 'work.category.changed', 'category_change', $metadata);
    }

    /** @param list<int> $previousTagIds @param list<int> $currentTagIds @param list<int> $addedTagIds @param list<int> $removedTagIds */
    private function recordTagsAudit(
        FormRequest $request,
        Work $work,
        array $previousTagIds,
        array $currentTagIds,
        array $addedTagIds,
        array $removedTagIds,
        string $mode,
        ?int $requestedWorkCount = null,
    ): void {
        $metadata = [
            'work_id' => $work->getKey(),
            'previous_tag_ids' => $previousTagIds,
            'current_tag_ids' => $currentTagIds,
            'added_tag_ids' => $addedTagIds,
            'removed_tag_ids' => $removedTagIds,
            'previous_count' => count($previousTagIds),
            'current_count' => count($currentTagIds),
            'mode' => $mode,
        ];

        if ($requestedWorkCount !== null) {
            $metadata['requested_work_count'] = $requestedWorkCount;
        }

        $this->recordAudit($request, $work, 'work.tags.updated', 'tags_update', $metadata);
    }

    /** @param array<string, mixed> $metadata */
    private function recordAudit(FormRequest $request, Work $work, string $eventType, string $action, array $metadata): void
    {
        $actor = $request->user();

        $this->auditEventLogger->record([
            'event_type' => $eventType,
            'category' => 'works',
            'severity' => 'notice',
            'actor_type' => $actor ? 'user' : 'system',
            'actor_id' => $actor?->getKey(),
            'actor_role' => $actor?->roles->first()?->name,
            'target_type' => 'work',
            'target_id' => $work->getKey(),
            'action' => $action,
            'outcome' => 'success',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    /** @return array<string, mixed> */
    private function categoryPayload(WorkCategory $category): array
    {
        return [
            'id' => $category->getKey(),
            'name_ar' => $category->name_ar,
            'name_en' => $category->name_en,
            'slug' => $category->slug,
            'disabled_at' => $category->disabled_at?->toJSON(),
            'is_active' => $category->isActive(),
            'sort_order' => $category->sort_order,
        ];
    }

    /** @return array<string, mixed> */
    private function tagPayload(WorkTag $tag): array
    {
        return [
            'id' => $tag->getKey(),
            'name_ar' => $tag->name_ar,
            'name_en' => $tag->name_en,
            'slug' => $tag->slug,
            'disabled_at' => $tag->disabled_at?->toJSON(),
            'is_active' => $tag->isActive(),
            'sort_order' => $tag->sort_order,
        ];
    }

    /** @param array<mixed> $values @return list<int> */
    private function sortedIntegers(array $values): array
    {
        $integers = array_map(static fn (mixed $value): int => (int) $value, $values);
        sort($integers, SORT_NUMERIC);

        return array_values($integers);
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    /** @return array{requested: int, changed: int, unchanged: int} */
    private function summary(int $requested, int $changed): array
    {
        return [
            'requested' => $requested,
            'changed' => $changed,
            'unchanged' => $requested - $changed,
        ];
    }
}
