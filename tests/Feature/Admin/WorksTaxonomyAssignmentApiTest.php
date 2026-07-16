<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksTaxonomyAssignmentController;
use App\Http\Controllers\Api\Admin\WorksTaxonomyCategoryActionController;
use App\Http\Controllers\Api\Admin\WorksTaxonomyTagActionController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkTag;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksTaxonomyAssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    private const BULK_CATEGORY = '/api/admin/works/taxonomy/assign/category';

    private const BULK_TAGS = '/api/admin/works/taxonomy/assign/tags';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_requests_are_rejected_for_all_four_routes(): void
    {
        $this->patchJson($this->categoryEndpoint(1), ['category_id' => null])->assertUnauthorized();
        $this->patchJson($this->tagsEndpoint(1), ['tag_ids' => []])->assertUnauthorized();
        $this->patchJson(self::BULK_CATEGORY, ['work_ids' => [1], 'category_id' => null])->assertUnauthorized();
        $this->patchJson(self::BULK_TAGS, ['work_ids' => [1], 'tag_ids' => []])->assertUnauthorized();
    }

    public function test_super_admin_can_use_all_four_routes(): void
    {
        $this->actingAsRole('super-admin');
        $works = Work::factory()->count(2)->create();

        $this->patchJson($this->categoryEndpoint($works[0]->id), ['category_id' => null])->assertOk();
        $this->patchJson($this->tagsEndpoint($works[0]->id), ['tag_ids' => []])->assertOk();
        $this->patchJson(self::BULK_CATEGORY, ['work_ids' => $works->modelKeys(), 'category_id' => null])->assertOk();
        $this->patchJson(self::BULK_TAGS, ['work_ids' => $works->modelKeys(), 'tag_ids' => []])->assertOk();
    }

    public function test_admin_and_staff_require_every_permission_for_each_operation(): void
    {
        foreach (['admin', 'staff'] as $role) {
            foreach (array_keys($this->operationPermissions()) as $operation) {
                $required = $this->operationPermissions()[$operation];

                foreach ($required as $missing) {
                    $this->actingAsRole($role, array_values(array_diff($required, [$missing])));
                    $this->performOperation($operation)->assertForbidden();
                }

                $this->actingAsRole($role, $required);
                $this->performOperation($operation)->assertOk();
            }
        }
    }

    public function test_individual_and_bulk_permissions_never_cross_authorize(): void
    {
        foreach ($this->operationPermissions() as $grantedOperation => $permissions) {
            $this->actingAsRole('admin', $permissions);

            foreach (array_keys($this->operationPermissions()) as $requestedOperation) {
                $response = $this->performOperation($requestedOperation);
                $grantedOperation === $requestedOperation
                    ? $response->assertOk()
                    : $response->assertForbidden();
            }
        }
    }

    public function test_client_designer_and_external_roles_are_always_forbidden(): void
    {
        $permissions = array_values(array_unique(array_merge(...array_values($this->operationPermissions()))));

        foreach (['client', 'designer', 'external'] as $role) {
            if ($role === 'external') {
                Role::findOrCreate($role, 'web');
            }

            $this->actingAsRole($role, $permissions);

            foreach (array_keys($this->operationPermissions()) as $operation) {
                $this->performOperation($operation)->assertForbidden();
            }
        }
    }

    public function test_individual_category_replaces_legacy_value_returns_safe_payload_and_audits(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create(['sort_order' => 4]);
        $legacyCategoryId = $category->id + 100_000;
        $work = Work::factory()->create(['category_id' => $legacyCategoryId]);
        $tag = WorkTag::factory()->create();
        $work->tags()->attach($tag);

        $response = $this->patchJson($this->categoryEndpoint($work->id), ['category_id' => $category->id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.work.id', $work->id)
            ->assertJsonPath('data.work.previous_category_id', $legacyCategoryId)
            ->assertJsonPath('data.work.category_id', $category->id)
            ->assertJsonPath('data.work.category.id', $category->id)
            ->assertJsonPath('message', 'تم تحديث تصنيف العمل بنجاح')
            ->assertJsonPath('errors', null);

        $this->assertSame(
            ['category', 'category_id', 'id', 'previous_category_id'],
            $this->sortedKeys($response->json('data.work')),
        );
        $this->assertSafeCategory($response->json('data.work.category'));
        $this->assertSame($category->id, $work->fresh()->category_id);
        $this->assertSame([$tag->id], $work->fresh()->tags()->pluck('work_tags.id')->all());
        $this->assertAudit('work.category.changed', $work->id, 'category_change', [
            'work_id' => $work->id,
            'previous_category_id' => $legacyCategoryId,
            'current_category_id' => $category->id,
            'mode' => 'individual',
        ]);
    }

    public function test_individual_category_null_removes_assignment_and_no_op_preserves_timestamp_and_audit_count(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();
        $work = Work::factory()->create(['category_id' => $category->id]);

        $this->patchJson($this->categoryEndpoint($work->id), ['category_id' => null])
            ->assertOk()
            ->assertJsonPath('data.work.category', null)
            ->assertJsonPath('data.changed', true);

        $updatedAt = $work->fresh()->updated_at->toJSON();
        $auditCount = AuditEvent::query()->count();
        $this->travel(1)->minute();

        $this->patchJson($this->categoryEndpoint($work->id), ['category_id' => null])
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('message', 'لم يتغير تصنيف العمل');

        $this->assertSame($updatedAt, $work->fresh()->updated_at->toJSON());
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    public function test_individual_category_rejects_disabled_missing_and_unknown_inputs_without_audit(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create(['category_id' => 1]);
        $disabled = WorkCategory::factory()->disabled()->create();

        $this->patchJson($this->categoryEndpoint($work->id), ['category_id' => $disabled->id])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->patchJson($this->categoryEndpoint($work->id), ['category_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->patchJson($this->categoryEndpoint($work->id), [])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->patchJson($this->categoryEndpoint($work->id).'?token=secret', ['category_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->patchJson($this->categoryEndpoint($work->id), ['category_id' => null, 'password' => 'secret'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertSame(1, $work->fresh()->category_id);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_missing_individual_work_returns_404_without_audit(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson($this->categoryEndpoint(999999), ['category_id' => null])->assertNotFound();
        $this->patchJson($this->tagsEndpoint(999999), ['tag_ids' => []])->assertNotFound();
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_individual_tags_are_a_sorted_full_replacement_with_safe_payload_and_audit(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();
        $work = Work::factory()->create(['category_id' => $category->id]);
        $removed = WorkTag::factory()->create();
        $first = WorkTag::factory()->create(['sort_order' => 20]);
        $second = WorkTag::factory()->create(['sort_order' => 10]);
        $work->tags()->attach($removed);
        $workUpdatedAt = $work->updated_at->toJSON();
        $tagUpdatedAt = $first->updated_at->toJSON();

        $response = $this->patchJson($this->tagsEndpoint($work->id), ['tag_ids' => [$first->id, $second->id]])
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.work.previous_tag_ids', [$removed->id])
            ->assertJsonPath('data.work.tag_ids', [$first->id, $second->id])
            ->assertJsonPath('data.work.added_tag_ids', [$first->id, $second->id])
            ->assertJsonPath('data.work.removed_tag_ids', [$removed->id])
            ->assertJsonPath('data.work.tags.0.id', $second->id)
            ->assertJsonPath('data.work.tags.1.id', $first->id)
            ->assertJsonPath('message', 'تم تحديث وسوم العمل بنجاح');

        foreach ($response->json('data.work.tags') as $tagPayload) {
            $this->assertSafeTag($tagPayload);
        }

        $this->assertSame($category->id, $work->fresh()->category_id);
        $this->assertSame($workUpdatedAt, $work->fresh()->updated_at->toJSON());
        $this->assertSame($tagUpdatedAt, $first->fresh()->updated_at->toJSON());
        $this->assertAudit('work.tags.updated', $work->id, 'tags_update', [
            'work_id' => $work->id,
            'previous_tag_ids' => [$removed->id],
            'current_tag_ids' => [$first->id, $second->id],
            'added_tag_ids' => [$first->id, $second->id],
            'removed_tag_ids' => [$removed->id],
            'previous_count' => 1,
            'current_count' => 2,
            'mode' => 'individual',
        ]);
    }

    public function test_individual_empty_tags_clear_assignments_and_repeated_request_is_no_op(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create();
        $tags = WorkTag::factory()->count(2)->create();
        $work->tags()->attach($tags);

        $this->patchJson($this->tagsEndpoint($work->id), ['tag_ids' => []])
            ->assertOk()->assertJsonPath('data.work.tag_ids', [])->assertJsonPath('data.changed', true);
        $updatedAt = $work->fresh()->updated_at->toJSON();
        $auditCount = AuditEvent::query()->count();

        $this->patchJson($this->tagsEndpoint($work->id), ['tag_ids' => []])
            ->assertOk()->assertJsonPath('data.changed', false)->assertJsonPath('message', 'لم تتغير وسوم العمل');

        $this->assertSame($updatedAt, $work->fresh()->updated_at->toJSON());
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    public function test_disabled_tag_can_be_kept_or_removed_but_cannot_be_newly_assigned(): void
    {
        $this->actingAsRole('super-admin');
        $existingWork = Work::factory()->create();
        $newWork = Work::factory()->create();
        $disabled = WorkTag::factory()->disabled()->create();
        $existingWork->tags()->attach($disabled);

        $this->patchJson($this->tagsEndpoint($existingWork->id), ['tag_ids' => [$disabled->id]])
            ->assertOk()->assertJsonPath('data.changed', false);
        $this->patchJson($this->tagsEndpoint($newWork->id), ['tag_ids' => [$disabled->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('tag_ids');
        $this->patchJson($this->tagsEndpoint($existingWork->id), ['tag_ids' => []])
            ->assertOk()->assertJsonPath('data.changed', true);

        $this->assertFalse($disabled->fresh()->isActive());
    }

    public function test_tag_validation_rejects_duplicates_missing_too_many_query_and_sensitive_fields(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create();
        $tag = WorkTag::factory()->create();

        $this->patchJson($this->tagsEndpoint($work->id), ['tag_ids' => [$tag->id, $tag->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('tag_ids.1');
        $this->patchJson($this->tagsEndpoint($work->id), ['tag_ids' => range(1, 51)])
            ->assertUnprocessable()->assertJsonValidationErrors('tag_ids');
        $this->patchJson($this->tagsEndpoint($work->id), ['tag_ids' => [999999]])
            ->assertUnprocessable()->assertJsonValidationErrors('tag_ids.0');
        $this->patchJson($this->tagsEndpoint($work->id).'?user_id=1', ['tag_ids' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->patchJson($this->tagsEndpoint($work->id), ['tag_ids' => [], 'payload' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('payload');
        $this->patchJson($this->tagsEndpoint($work->id), [])
            ->assertUnprocessable()->assertJsonValidationErrors('tag_ids');
    }

    public function test_bulk_category_is_ordered_atomic_counts_changes_and_audits_only_changes(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();
        $unchanged = Work::factory()->create(['category_id' => $category->id]);
        $legacy = Work::factory()->create(['category_id' => 1]);
        $empty = Work::factory()->create(['category_id' => null]);

        $response = $this->patchJson(self::BULK_CATEGORY, [
            'work_ids' => [$empty->id, $unchanged->id, $legacy->id],
            'category_id' => $category->id,
        ])->assertOk()
            ->assertJsonPath('data.summary', ['requested' => 3, 'changed' => 2, 'unchanged' => 1])
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('message', 'تم تحديث تصنيف الأعمال المحددة بنجاح');

        $this->assertSame(collect([$empty->id, $unchanged->id, $legacy->id])->sort()->values()->all(),
            collect($response->json('data.items'))->pluck('work_id')->all());
        $this->assertSame(2, AuditEvent::query()->where('event_type', 'work.category.changed')->count());
        $this->assertSame(0, AuditEvent::query()->where('target_id', $unchanged->id)->count());

        foreach (AuditEvent::query()->get() as $audit) {
            $this->assertSame(3, $audit->metadata['requested_work_count']);
            $this->assertSame('bulk', $audit->metadata['mode']);
        }
    }

    public function test_bulk_category_null_removes_all_and_full_no_op_has_no_audit(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();
        $works = Work::factory()->count(2)->create(['category_id' => $category->id]);

        $this->patchJson(self::BULK_CATEGORY, ['work_ids' => $works->modelKeys(), 'category_id' => null])
            ->assertOk()->assertJsonPath('data.summary.changed', 2)->assertJsonPath('data.category', null);
        AuditEvent::query()->delete();

        $this->patchJson(self::BULK_CATEGORY, ['work_ids' => $works->modelKeys(), 'category_id' => null])
            ->assertOk()->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.summary', ['requested' => 2, 'changed' => 0, 'unchanged' => 2])
            ->assertJsonPath('message', 'لم تتغير تصنيفات الأعمال المحددة');
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_bulk_category_validation_prevents_partial_updates(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();
        $disabled = WorkCategory::factory()->disabled()->create();
        $work = Work::factory()->create(['category_id' => null]);

        foreach ([
            ['work_ids' => [$work->id, 999999], 'category_id' => $category->id],
            ['work_ids' => [$work->id, $work->id], 'category_id' => $category->id],
            ['work_ids' => range(1, 101), 'category_id' => $category->id],
            ['work_ids' => [$work->id], 'category_id' => $disabled->id],
        ] as $payload) {
            $this->patchJson(self::BULK_CATEGORY, $payload)->assertUnprocessable();
        }

        $this->assertNull($work->fresh()->category_id);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_bulk_tags_replaces_atomically_with_ordered_items_summary_and_per_work_audit(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();
        $firstWork = Work::factory()->create(['category_id' => $category->id]);
        $secondWork = Work::factory()->create(['category_id' => $category->id]);
        $old = WorkTag::factory()->create();
        $new = WorkTag::factory()->create();
        $firstWork->tags()->attach($old);
        $secondWork->tags()->attach($new);
        $workTimestamp = $firstWork->updated_at->toJSON();
        $tagTimestamp = $new->updated_at->toJSON();

        $response = $this->patchJson(self::BULK_TAGS, [
            'work_ids' => [$secondWork->id, $firstWork->id],
            'tag_ids' => [$new->id],
        ])->assertOk()
            ->assertJsonPath('data.summary', ['requested' => 2, 'changed' => 1, 'unchanged' => 1])
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('message', 'تم تحديث وسوم الأعمال المحددة بنجاح');

        $this->assertSame(collect([$firstWork->id, $secondWork->id])->sort()->values()->all(),
            collect($response->json('data.items'))->pluck('work_id')->all());
        $this->assertSame(1, AuditEvent::query()->where('event_type', 'work.tags.updated')->count());
        $this->assertSame($firstWork->id, AuditEvent::query()->sole()->target_id);
        $this->assertSame($category->id, $firstWork->fresh()->category_id);
        $this->assertSame($workTimestamp, $firstWork->fresh()->updated_at->toJSON());
        $this->assertSame($tagTimestamp, $new->fresh()->updated_at->toJSON());
    }

    public function test_bulk_empty_tags_clear_all_and_full_no_op_has_no_audit(): void
    {
        $this->actingAsRole('super-admin');
        $works = Work::factory()->count(2)->create();
        $tag = WorkTag::factory()->create();
        foreach ($works as $work) {
            $work->tags()->attach($tag);
        }

        $this->patchJson(self::BULK_TAGS, ['work_ids' => $works->modelKeys(), 'tag_ids' => []])
            ->assertOk()->assertJsonPath('data.summary.changed', 2)->assertJsonPath('data.tags', []);
        AuditEvent::query()->delete();

        $this->patchJson(self::BULK_TAGS, ['work_ids' => $works->modelKeys(), 'tag_ids' => []])
            ->assertOk()->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.summary', ['requested' => 2, 'changed' => 0, 'unchanged' => 2]);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_bulk_disabled_tag_must_already_belong_to_every_selected_work(): void
    {
        $this->actingAsRole('super-admin');
        $works = Work::factory()->count(2)->create();
        $disabled = WorkTag::factory()->disabled()->create();
        $works[0]->tags()->attach($disabled);

        $this->patchJson(self::BULK_TAGS, [
            'work_ids' => $works->modelKeys(),
            'tag_ids' => [$disabled->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('tag_ids');

        $this->assertDatabaseHas('work_tag_assignments', ['work_id' => $works[0]->id, 'work_tag_id' => $disabled->id]);
        $this->assertDatabaseMissing('work_tag_assignments', ['work_id' => $works[1]->id, 'work_tag_id' => $disabled->id]);
        $this->assertDatabaseCount('audit_events', 0);

        $works[1]->tags()->attach($disabled);
        $this->patchJson(self::BULK_TAGS, [
            'work_ids' => $works->modelKeys(),
            'tag_ids' => [$disabled->id],
        ])->assertOk()->assertJsonPath('data.changed', false);
    }

    public function test_bulk_tag_validation_prevents_partial_updates_and_rejects_unknown_inputs(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create();
        $tag = WorkTag::factory()->create();

        foreach ([
            ['work_ids' => [$work->id, 999999], 'tag_ids' => [$tag->id]],
            ['work_ids' => [$work->id, $work->id], 'tag_ids' => [$tag->id]],
            ['work_ids' => range(1, 101), 'tag_ids' => []],
            ['work_ids' => [$work->id], 'tag_ids' => [$tag->id, $tag->id]],
            ['work_ids' => [$work->id], 'tag_ids' => range(1, 51)],
            ['work_ids' => [$work->id], 'tag_ids' => [], 'metadata' => []],
        ] as $payload) {
            $this->patchJson(self::BULK_TAGS, $payload)->assertUnprocessable();
        }

        $this->patchJson(self::BULK_TAGS.'?cookie=secret', ['work_ids' => [$work->id], 'tag_ids' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('cookie');
        $this->assertDatabaseCount('work_tag_assignments', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_forbidden_and_validation_failures_never_create_assignment_audit_events(): void
    {
        $work = Work::factory()->create();
        $this->actingAsRole('client', array_values(array_unique(array_merge(...array_values($this->operationPermissions())))));
        $this->patchJson($this->categoryEndpoint($work->id), ['category_id' => null])->assertForbidden();
        $this->assertSame(
            0,
            AuditEvent::query()
                ->whereIn('event_type', [
                    'work.category.changed',
                    'work.tags.updated',
                ])
                ->count(),
        );

        $this->actingAsRole('super-admin');
        $this->patchJson($this->tagsEndpoint($work->id), ['tag_ids' => [999999]])->assertUnprocessable();
        $this->assertSame(
            0,
            AuditEvent::query()
                ->whereIn('event_type', [
                    'work.category.changed',
                    'work.tags.updated',
                ])
                ->count(),
        );
    }

    public function test_assignment_routes_use_expected_controller_order_constraints_and_methods(): void
    {
        $routes = [
            ['PATCH', self::BULK_CATEGORY, 'bulkUpdateCategory'],
            ['PATCH', self::BULK_TAGS, 'bulkUpdateTags'],
            ['PATCH', '/api/admin/works/123/taxonomy/category', 'updateCategory'],
            ['PATCH', '/api/admin/works/123/taxonomy/tags', 'updateTags'],
        ];

        foreach ($routes as [$method, $uri, $action]) {
            $route = Route::getRoutes()->match(Request::create($uri, $method));
            $this->assertSame(WorksTaxonomyAssignmentController::class.'@'.$action, $route->getActionName());
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        }

        $categoryRoute = Route::getRoutes()->match(Request::create('/api/admin/works/123/taxonomy/category', 'PATCH'));
        $tagsRoute = Route::getRoutes()->match(Request::create('/api/admin/works/123/taxonomy/tags', 'PATCH'));
        $this->assertSame('[0-9]+', $categoryRoute->wheres['work']);
        $this->assertSame('[0-9]+', $tagsRoute->wheres['work']);

        $routeList = collect(Route::getRoutes()->getRoutes());
        $bulkCategoryPosition = $routeList->search(fn ($route): bool => $route->uri() === 'api/admin/works/taxonomy/assign/category');
        $firstDynamicWorkPosition = $routeList->search(fn ($route): bool => str_contains($route->uri(), '{work}'));
        $this->assertIsInt($bulkCategoryPosition);
        $this->assertIsInt($firstDynamicWorkPosition);
        $this->assertLessThan($firstDynamicWorkPosition, $bulkCategoryPosition);

        foreach (['POST', 'PUT', 'DELETE'] as $method) {
            $this->json($method, self::BULK_CATEGORY)->assertMethodNotAllowed();
            $this->json($method, self::BULK_TAGS)->assertMethodNotAllowed();
        }

        $this->actingAsRole('super-admin');
        $this->getJson('/api/admin/works/taxonomy')->assertOk();
        $this->getJson('/api/admin/works/999999')->assertNotFound();
        $this->patchJson('/api/admin/works/1/taxonomy/tags/attach', [])->assertNotFound();
        $this->patchJson('/api/admin/works/taxonomy/merge', [])->assertNotFound();
    }

    public function test_catalog_reads_reflect_assignments_without_changing_taxonomy_records(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create(['category_id' => 1]);
        $category = WorkCategory::factory()->create();
        $tag = WorkTag::factory()->create();
        $categoryUpdatedAt = $category->updated_at->toJSON();
        $tagUpdatedAt = $tag->updated_at->toJSON();

        $this->patchJson($this->categoryEndpoint($work->id), ['category_id' => $category->id])->assertOk();
        $this->patchJson($this->tagsEndpoint($work->id), ['tag_ids' => [$tag->id]])->assertOk();

        $this->getJson('/api/admin/works/taxonomy')->assertOk()->assertJsonFragment(['category_id' => $category->id]);
        $this->getJson('/api/admin/works/taxonomy/categories')->assertOk()
            ->assertJsonFragment(['id' => $category->id, 'works_count' => 1]);
        $this->getJson('/api/admin/works/taxonomy/tags')->assertOk()
            ->assertJsonFragment(['id' => $tag->id, 'works_count' => 1])
            ->assertJsonPath('data.summary.assignments_total', 1);
        $this->assertSame($categoryUpdatedAt, $category->fresh()->updated_at->toJSON());
        $this->assertSame($tagUpdatedAt, $tag->fresh()->updated_at->toJSON());
    }

    public function test_existing_taxonomy_actions_schema_and_legacy_id_reservation_remain_intact(): void
    {
        $categoryStore = Route::getRoutes()->match(Request::create('/api/admin/works/taxonomy/categories', 'POST'));
        $tagStore = Route::getRoutes()->match(Request::create('/api/admin/works/taxonomy/tags', 'POST'));
        $this->assertSame(WorksTaxonomyCategoryActionController::class.'@store', $categoryStore->getActionName());
        $this->assertSame(WorksTaxonomyTagActionController::class.'@store', $tagStore->getActionName());

        $this->assertTrue(Schema::hasColumn('works', 'category_id'));
        $this->assertFalse(Schema::hasColumn('works', 'work_category_id'));
        $this->assertTrue(Schema::hasTable('work_categories'));
        $this->assertTrue(Schema::hasTable('work_tags'));
        $this->assertTrue(Schema::hasTable('work_tag_assignments'));

        Work::factory()->create(['category_id' => 1]);
        $category = WorkCategory::factory()->create();
        $this->assertGreaterThan(1, $category->id);
    }

    /** @return array<string, list<string>> */
    private function operationPermissions(): array
    {
        $shared = ['admin.works.access', 'admin.works.taxonomy.view'];

        return [
            'individual_category' => [...$shared, 'admin.works.taxonomy.categories.view', 'admin.works.update.category'],
            'individual_tags' => [...$shared, 'admin.works.taxonomy.tags.view', 'admin.works.update.tags'],
            'bulk_category' => [...$shared, 'admin.works.taxonomy.categories.view', 'admin.works.taxonomy.bulk_assign', 'admin.works.bulk.category_update'],
            'bulk_tags' => [...$shared, 'admin.works.taxonomy.tags.view', 'admin.works.taxonomy.bulk_assign', 'admin.works.bulk.tags_update'],
        ];
    }

    private function performOperation(string $operation)
    {
        $work = Work::factory()->create();

        return match ($operation) {
            'individual_category' => $this->patchJson($this->categoryEndpoint($work->id), ['category_id' => null]),
            'individual_tags' => $this->patchJson($this->tagsEndpoint($work->id), ['tag_ids' => []]),
            'bulk_category' => $this->patchJson(self::BULK_CATEGORY, ['work_ids' => [$work->id], 'category_id' => null]),
            'bulk_tags' => $this->patchJson(self::BULK_TAGS, ['work_ids' => [$work->id], 'tag_ids' => []]),
        };
    }

    /** @param list<string> $permissions */
    private function actingAsRole(string $role, array $permissions = []): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /** @param array<string, mixed> $expectedMetadata */
    private function assertAudit(string $eventType, int $workId, string $action, array $expectedMetadata): void
    {
        $audit = AuditEvent::query()->sole();
        $this->assertSame($eventType, $audit->event_type);
        $this->assertSame('works', $audit->category);
        $this->assertSame('work', $audit->target_type);
        $this->assertSame($workId, $audit->target_id);
        $this->assertSame($action, $audit->action);
        $this->assertSame($expectedMetadata, $audit->metadata);
    }

    /** @param array<string, mixed> $payload */
    private function assertSafeCategory(array $payload): void
    {
        $this->assertSame(
            ['disabled_at', 'id', 'is_active', 'name_ar', 'name_en', 'slug', 'sort_order'],
            $this->sortedKeys($payload),
        );
    }

    /** @param array<string, mixed> $payload */
    private function assertSafeTag(array $payload): void
    {
        $this->assertSame(
            ['disabled_at', 'id', 'is_active', 'name_ar', 'name_en', 'slug', 'sort_order'],
            $this->sortedKeys($payload),
        );
    }

    /** @param array<string, mixed> $payload @return list<string> */
    private function sortedKeys(array $payload): array
    {
        $keys = array_keys($payload);
        sort($keys);

        return $keys;
    }

    private function categoryEndpoint(int $workId): string
    {
        return '/api/admin/works/'.$workId.'/taxonomy/category';
    }

    private function tagsEndpoint(int $workId): string
    {
        return '/api/admin/works/'.$workId.'/taxonomy/tags';
    }
}
