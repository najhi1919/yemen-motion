<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateWorksTagRequest;
use App\Http\Requests\Admin\DisableWorksTagRequest;
use App\Http\Requests\Admin\UpdateWorksTagRequest;
use App\Models\WorkTag;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class WorksTaxonomyTagActionController extends Controller
{
    public function __construct(private readonly AuditEventLogger $auditEventLogger) {}

    public function store(CreateWorksTagRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $tag = DB::transaction(function () use ($request, $validated): WorkTag {
                $tag = WorkTag::query()->create([
                    'name_ar' => $validated['name_ar'],
                    'name_en' => $validated['name_en'],
                    'slug' => $validated['slug'],
                    'disabled_at' => null,
                    'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : 0,
                ]);

                $tag->refresh()->loadCount('works');
                $this->recordAuditEvent($request, $tag, 'create', [
                    'tag_id' => $tag->getKey(),
                    'slug' => $tag->slug,
                    'sort_order' => $tag->sort_order,
                    'is_active' => $tag->isActive(),
                ]);

                return $tag;
            });
        } catch (QueryException $exception) {
            if ($this->isSlugUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'slug' => ['قيمة slug مستخدمة لوسم أعمال آخر.'],
                ]);
            }

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'tag' => $this->tagPayload($tag),
                'changed' => true,
            ],
            'message' => 'تم إنشاء وسم الأعمال بنجاح',
            'errors' => null,
        ], 201);
    }

    public function update(UpdateWorksTagRequest $request, string $tag): JsonResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($request, $tag, $validated): array {
            $currentTag = WorkTag::query()
                ->whereKey((int) $tag)
                ->lockForUpdate()
                ->firstOrFail();
            $changes = $this->changedAttributes($currentTag, $validated);
            $changedFields = array_keys($changes);
            $previousSortOrder = $currentTag->sort_order;

            if ($changes !== []) {
                $currentTag->fill($changes);
                $currentTag->save();
                $currentTag->refresh();

                $metadata = [
                    'tag_id' => $currentTag->getKey(),
                    'changed_fields' => $changedFields,
                ];

                if (in_array('sort_order', $changedFields, true)) {
                    $metadata['previous_sort_order'] = $previousSortOrder;
                    $metadata['current_sort_order'] = $currentTag->sort_order;
                }

                $this->recordAuditEvent($request, $currentTag, 'update', $metadata);
            }

            $currentTag->loadCount('works');

            return [
                'tag' => $currentTag,
                'changed' => $changes !== [],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'tag' => $this->tagPayload($result['tag']),
                'changed' => $result['changed'],
            ],
            'message' => $result['changed']
                ? 'تم تحديث وسم الأعمال بنجاح'
                : 'لم تتغير بيانات وسم الأعمال',
            'errors' => null,
        ]);
    }

    public function disable(DisableWorksTagRequest $request, string $tag): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $tag): array {
            $currentTag = WorkTag::query()
                ->whereKey((int) $tag)
                ->lockForUpdate()
                ->firstOrFail();
            $changed = $currentTag->isActive();

            if ($changed) {
                $currentTag->disabled_at = now();
                $currentTag->save();
                $currentTag->refresh();
            }

            $currentTag->loadCount('works');

            if ($changed) {
                $this->recordAuditEvent($request, $currentTag, 'disable', [
                    'tag_id' => $currentTag->getKey(),
                    'works_count' => (int) $currentTag->getAttribute('works_count'),
                    'previous_is_active' => true,
                    'current_is_active' => false,
                ]);
            }

            return [
                'tag' => $currentTag,
                'changed' => $changed,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'tag' => $this->tagPayload($result['tag']),
                'changed' => $result['changed'],
            ],
            'message' => $result['changed']
                ? 'تم تعطيل وسم الأعمال بنجاح'
                : 'وسم الأعمال معطل بالفعل',
            'errors' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function changedAttributes(WorkTag $tag, array $validated): array
    {
        $changes = [];

        foreach (['name_ar', 'name_en', 'sort_order'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $value = $field === 'sort_order' ? (int) $validated[$field] : $validated[$field];

            if ($tag->getAttribute($field) !== $value) {
                $changes[$field] = $value;
            }
        }

        return $changes;
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

    /** @param array<string, mixed> $metadata */
    private function recordAuditEvent(
        CreateWorksTagRequest|UpdateWorksTagRequest|DisableWorksTagRequest $request,
        WorkTag $tag,
        string $action,
        array $metadata,
    ): void {
        $actor = $request->user();
        $eventType = match ($action) {
            'create' => 'works.taxonomy.tag.created',
            'update' => 'works.taxonomy.tag.updated',
            'disable' => 'works.taxonomy.tag.disabled',
        };

        try {
            $this->auditEventLogger->record([
                'event_type' => $eventType,
                'category' => 'works',
                'severity' => 'notice',
                'actor_type' => $actor ? 'user' : 'system',
                'actor_id' => $actor?->getKey(),
                'actor_role' => $actor?->roles->first()?->name,
                'target_type' => 'work_tag',
                'target_id' => $tag->getKey(),
                'action' => $action,
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => $metadata,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            if (app()->environment('testing')) {
                throw $exception;
            }
        }
    }

    private function isSlugUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $message = strtolower($exception->getMessage());
        $referencesSlugConstraint = str_contains($message, 'work_tags_slug_unique')
            || str_contains($message, 'work_tags.slug');

        return $referencesSlugConstraint && in_array($sqlState, ['23000', '23505'], true);
    }
}
