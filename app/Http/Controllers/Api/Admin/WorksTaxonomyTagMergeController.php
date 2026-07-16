<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MergeWorksTagsRequest;
use App\Models\WorkTag;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorksTaxonomyTagMergeController extends Controller
{
    private const INSERT_CHUNK_SIZE = 500;

    public function __construct(private readonly AuditEventLogger $auditEventLogger) {}

    public function merge(MergeWorksTagsRequest $request): JsonResponse
    {
        $targetTagId = (int) $request->validated('target_tag_id');
        $sourceTagIds = $this->sortedIntegers($request->validated('source_tag_ids'));

        $result = DB::transaction(function () use ($request, $targetTagId, $sourceTagIds): array {
            if (in_array($targetTagId, $sourceTagIds, true)) {
                throw ValidationException::withMessages([
                    'source_tag_ids' => ['لا يجوز تضمين الوسم الهدف ضمن الوسوم المصدر.'],
                ]);
            }

            $allTagIds = $sourceTagIds;
            $allTagIds[] = $targetTagId;
            sort($allTagIds, SORT_NUMERIC);

            $lockedTags = WorkTag::query()
                ->whereKey($allTagIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (WorkTag $tag): int => (int) $tag->getKey());

            $targetTag = $lockedTags->get($targetTagId);

            if (! $targetTag || ! $targetTag->isActive()) {
                throw ValidationException::withMessages([
                    'target_tag_id' => ['يجب اختيار وسم أعمال هدف موجود وفعال.'],
                ]);
            }

            $sourceTags = $this->sourceTagsFromLockedCollection($lockedTags, $sourceTagIds);

            if ($sourceTags->count() !== count($sourceTagIds)) {
                throw ValidationException::withMessages([
                    'source_tag_ids' => ['يجب أن تشير جميع الوسوم المصدر إلى سجلات موجودة.'],
                ]);
            }

            $sourceWasActive = $sourceTags->mapWithKeys(
                fn (WorkTag $sourceTag): array => [$sourceTag->getKey() => $sourceTag->isActive()],
            )->all();

            $sourceAssignments = DB::table('work_tag_assignments')
                ->whereIn('work_tag_id', $sourceTagIds)
                ->orderBy('work_id')
                ->orderBy('work_tag_id')
                ->get(['work_id', 'work_tag_id']);
            $affectedWorkIds = $sourceAssignments
                ->pluck('work_id')
                ->map(fn (mixed $workId): int => (int) $workId)
                ->unique()
                ->sort()
                ->values()
                ->all();
            $assignmentsRemovedBySource = array_fill_keys($sourceTagIds, 0);

            foreach ($sourceAssignments as $assignment) {
                $assignmentsRemovedBySource[(int) $assignment->work_tag_id]++;
            }

            $targetAssignmentsAdded = $this->insertTargetAssignments($targetTagId, $affectedWorkIds);
            $sourceAssignmentsRemoved = DB::table('work_tag_assignments')
                ->whereIn('work_tag_id', $sourceTagIds)
                ->delete();
            $activeSourceTagIds = $sourceTags
                ->filter(fn (WorkTag $tag): bool => $tag->isActive())
                ->modelKeys();
            $sourceTagsDisabled = count($activeSourceTagIds);

            if ($activeSourceTagIds !== []) {
                $disabledAt = now();
                DB::table('work_tags')
                    ->whereIn('id', $activeSourceTagIds)
                    ->whereNull('disabled_at')
                    ->update([
                        'disabled_at' => $disabledAt,
                        'updated_at' => $disabledAt,
                    ]);

                foreach ($sourceTags as $sourceTag) {
                    if (in_array($sourceTag->getKey(), $activeSourceTagIds, true)) {
                        $sourceTag->disabled_at = $disabledAt;
                    }
                }
            }

            $finalWorksCounts = DB::table('work_tag_assignments')
                ->whereIn('work_tag_id', $allTagIds)
                ->selectRaw('work_tag_id, COUNT(*) AS works_count')
                ->groupBy('work_tag_id')
                ->pluck('works_count', 'work_tag_id')
                ->mapWithKeys(fn (mixed $count, mixed $tagId): array => [(int) $tagId => (int) $count])
                ->all();
            $duplicateAssignmentsCollapsed = max(0, $sourceAssignmentsRemoved - $targetAssignmentsAdded);
            $changed = $targetAssignmentsAdded > 0
                || $sourceAssignmentsRemoved > 0
                || $sourceTagsDisabled > 0;
            $summary = [
                'source_tags_requested' => count($sourceTagIds),
                'source_tags_disabled' => $sourceTagsDisabled,
                'affected_works' => count($affectedWorkIds),
                'source_assignments_removed' => $sourceAssignmentsRemoved,
                'target_assignments_added' => $targetAssignmentsAdded,
                'duplicate_assignments_collapsed' => $duplicateAssignmentsCollapsed,
            ];

            if ($changed) {
                $this->recordAuditEvent($request, $targetTagId, $sourceTagIds, $summary);
            }

            return compact(
                'targetTag',
                'sourceTags',
                'sourceWasActive',
                'assignmentsRemovedBySource',
                'finalWorksCounts',
                'summary',
                'changed',
            );
        });

        return response()->json([
            'success' => true,
            'data' => [
                'target_tag' => $this->tagPayload(
                    $result['targetTag'],
                    (int) ($result['finalWorksCounts'][$result['targetTag']->getKey()] ?? 0),
                ),
                'source_tags' => $result['sourceTags']
                    ->map(fn (WorkTag $sourceTag): array => [
                        ...$this->tagPayload(
                            $sourceTag,
                            (int) ($result['finalWorksCounts'][$sourceTag->getKey()] ?? 0),
                        ),
                        'merge_state' => [
                            'was_active' => $result['sourceWasActive'][$sourceTag->getKey()],
                            'is_disabled' => ! $sourceTag->isActive(),
                            'assignments_removed' => $result['assignmentsRemovedBySource'][$sourceTag->getKey()],
                        ],
                    ])
                    ->values()
                    ->all(),
                'summary' => $result['summary'],
                'changed' => $result['changed'],
            ],
            'message' => $result['changed']
                ? 'تم دمج وسوم الأعمال بنجاح'
                : 'لم تتغير وسوم الأعمال المدمجة',
            'errors' => null,
        ]);
    }

    /** @param Collection<int, WorkTag> $lockedTags @param list<int> $sourceTagIds @return Collection<int, WorkTag> */
    private function sourceTagsFromLockedCollection(Collection $lockedTags, array $sourceTagIds): Collection
    {
        return collect($sourceTagIds)
            ->map(fn (int $sourceTagId): ?WorkTag => $lockedTags->get($sourceTagId))
            ->filter()
            ->values();
    }

    /** @param list<int> $affectedWorkIds */
    private function insertTargetAssignments(int $targetTagId, array $affectedWorkIds): int
    {
        $inserted = 0;

        foreach (array_chunk($affectedWorkIds, self::INSERT_CHUNK_SIZE) as $workIdChunk) {
            $rows = array_map(
                static fn (int $workId): array => [
                    'work_id' => $workId,
                    'work_tag_id' => $targetTagId,
                ],
                $workIdChunk,
            );

            $inserted += DB::table('work_tag_assignments')->insertOrIgnore($rows);
        }

        return $inserted;
    }

    /** @param array<string, int> $summary @param list<int> $sourceTagIds */
    private function recordAuditEvent(
        MergeWorksTagsRequest $request,
        int $targetTagId,
        array $sourceTagIds,
        array $summary,
    ): void {
        $actor = $request->user();

        $this->auditEventLogger->record([
            'event_type' => 'works.taxonomy.tags.merged',
            'category' => 'works',
            'severity' => 'notice',
            'actor_type' => $actor ? 'user' : 'system',
            'actor_id' => $actor?->getKey(),
            'target_type' => 'work_tag',
            'target_id' => $targetTagId,
            'action' => 'merge',
            'outcome' => 'success',
            'ip_address' => $request->ip(),
            'metadata' => [
                'target_tag_id' => $targetTagId,
                'source_tag_ids' => $sourceTagIds,
                'source_tag_count' => $summary['source_tags_requested'],
                'source_tags_disabled' => $summary['source_tags_disabled'],
                'affected_work_count' => $summary['affected_works'],
                'source_assignments_removed' => $summary['source_assignments_removed'],
                'target_assignments_added' => $summary['target_assignments_added'],
                'duplicate_assignments_collapsed' => $summary['duplicate_assignments_collapsed'],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function tagPayload(WorkTag $tag, int $worksCount): array
    {
        return [
            'id' => $tag->getKey(),
            'name_ar' => $tag->name_ar,
            'name_en' => $tag->name_en,
            'slug' => $tag->slug,
            'disabled_at' => $tag->disabled_at?->toJSON(),
            'is_active' => $tag->isActive(),
            'sort_order' => $tag->sort_order,
            'works_count' => $worksCount,
        ];
    }

    /** @param array<mixed> $values @return list<int> */
    private function sortedIntegers(array $values): array
    {
        $integers = array_map(static fn (mixed $value): int => (int) $value, $values);
        sort($integers, SORT_NUMERIC);

        return array_values($integers);
    }
}
