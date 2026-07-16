<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateWorksCategoryRequest;
use App\Http\Requests\Admin\DisableWorksCategoryRequest;
use App\Http\Requests\Admin\UpdateWorksCategoryRequest;
use App\Models\WorkCategory;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class WorksTaxonomyCategoryActionController extends Controller
{
    public function __construct(private readonly AuditEventLogger $auditEventLogger) {}

    public function store(CreateWorksCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $category = DB::transaction(function () use ($request, $validated): WorkCategory {
                $category = WorkCategory::query()->create([
                    'name_ar' => $validated['name_ar'],
                    'name_en' => $validated['name_en'],
                    'slug' => $validated['slug'],
                    'disabled_at' => null,
                    'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : 0,
                ]);

                $category->refresh()->loadCount('works');
                $this->recordAuditEvent($request, $category, 'create', [
                    'category_id' => $category->getKey(),
                    'slug' => $category->slug,
                    'sort_order' => $category->sort_order,
                    'is_active' => $category->isActive(),
                ]);

                return $category;
            });
        } catch (QueryException $exception) {
            if ($this->isSlugUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'slug' => ['قيمة slug مستخدمة لتصنيف أعمال آخر.'],
                ]);
            }

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $this->categoryPayload($category),
                'changed' => true,
            ],
            'message' => 'تم إنشاء تصنيف الأعمال بنجاح',
            'errors' => null,
        ], 201);
    }

    public function update(UpdateWorksCategoryRequest $request, string $category): JsonResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($request, $category, $validated): array {
            $currentCategory = WorkCategory::query()
                ->whereKey((int) $category)
                ->lockForUpdate()
                ->firstOrFail();
            $changes = $this->changedAttributes($currentCategory, $validated);
            $changedFields = array_keys($changes);
            $previousSortOrder = $currentCategory->sort_order;

            if ($changes !== []) {
                $currentCategory->fill($changes);
                $currentCategory->save();
                $currentCategory->refresh();

                $metadata = [
                    'category_id' => $currentCategory->getKey(),
                    'changed_fields' => $changedFields,
                ];

                if (in_array('sort_order', $changedFields, true)) {
                    $metadata['previous_sort_order'] = $previousSortOrder;
                    $metadata['current_sort_order'] = $currentCategory->sort_order;
                }

                $this->recordAuditEvent($request, $currentCategory, 'update', $metadata);
            }

            $currentCategory->loadCount('works');

            return [
                'category' => $currentCategory,
                'changed' => $changes !== [],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $this->categoryPayload($result['category']),
                'changed' => $result['changed'],
            ],
            'message' => $result['changed']
                ? 'تم تحديث تصنيف الأعمال بنجاح'
                : 'لم تتغير بيانات تصنيف الأعمال',
            'errors' => null,
        ]);
    }

    public function disable(DisableWorksCategoryRequest $request, string $category): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $category): array {
            $currentCategory = WorkCategory::query()
                ->whereKey((int) $category)
                ->lockForUpdate()
                ->firstOrFail();
            $changed = $currentCategory->isActive();

            if ($changed) {
                $currentCategory->disabled_at = now();
                $currentCategory->save();
                $currentCategory->refresh();
            }

            $currentCategory->loadCount('works');

            if ($changed) {
                $this->recordAuditEvent($request, $currentCategory, 'disable', [
                    'category_id' => $currentCategory->getKey(),
                    'works_count' => (int) $currentCategory->getAttribute('works_count'),
                    'previous_is_active' => true,
                    'current_is_active' => false,
                ]);
            }

            return [
                'category' => $currentCategory,
                'changed' => $changed,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $this->categoryPayload($result['category']),
                'changed' => $result['changed'],
            ],
            'message' => $result['changed']
                ? 'تم تعطيل تصنيف الأعمال بنجاح'
                : 'تصنيف الأعمال معطل بالفعل',
            'errors' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function changedAttributes(WorkCategory $category, array $validated): array
    {
        $changes = [];

        foreach (['name_ar', 'name_en', 'sort_order'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $value = $field === 'sort_order' ? (int) $validated[$field] : $validated[$field];

            if ($category->getAttribute($field) !== $value) {
                $changes[$field] = $value;
            }
        }

        return $changes;
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

    /** @param array<string, mixed> $metadata */
    private function recordAuditEvent(
        CreateWorksCategoryRequest|UpdateWorksCategoryRequest|DisableWorksCategoryRequest $request,
        WorkCategory $category,
        string $action,
        array $metadata,
    ): void {
        $actor = $request->user();
        $eventType = match ($action) {
            'create' => 'works.taxonomy.category.created',
            'update' => 'works.taxonomy.category.updated',
            'disable' => 'works.taxonomy.category.disabled',
        };

        try {
            $this->auditEventLogger->record([
                'event_type' => $eventType,
                'category' => 'works',
                'severity' => 'notice',
                'actor_type' => $actor ? 'user' : 'system',
                'actor_id' => $actor?->getKey(),
                'actor_role' => $actor?->roles->first()?->name,
                'target_type' => 'work_category',
                'target_id' => $category->getKey(),
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
        $referencesSlugConstraint = str_contains($message, 'work_categories_slug_unique')
            || str_contains($message, 'work_categories.slug');

        return $referencesSlugConstraint && in_array($sqlState, ['23000', '23505'], true);
    }
}
