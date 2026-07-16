<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksTaxonomyCategoryActionController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkCategory;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksTaxonomyCategoryActionsApiTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/admin/works/taxonomy/categories';

    private const ACTION_PERMISSIONS = [
        'create' => 'admin.works.taxonomy.categories.create',
        'update' => 'admin.works.taxonomy.categories.update',
        'disable' => 'admin.works.taxonomy.categories.disable',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_requests_are_rejected_for_all_actions(): void
    {
        $this->postJson(self::ENDPOINT, $this->validCreatePayload())->assertUnauthorized();
        $this->patchJson(self::ENDPOINT.'/1', ['name_ar' => 'اسم جديد'])->assertUnauthorized();
        $this->patchJson(self::ENDPOINT.'/1/disable')->assertUnauthorized();
    }

    public function test_super_admin_can_create_update_and_disable_categories(): void
    {
        $this->actingAsRole('super-admin');

        $categoryId = $this->postJson(self::ENDPOINT, $this->validCreatePayload())
            ->assertCreated()
            ->json('data.category.id');

        $this->patchJson(self::ENDPOINT.'/'.$categoryId, ['name_en' => 'Updated Category'])
            ->assertOk()
            ->assertJsonPath('data.changed', true);
        $this->patchJson(self::ENDPOINT.'/'.$categoryId.'/disable')
            ->assertOk()
            ->assertJsonPath('data.changed', true);
    }

    public function test_admin_and_staff_require_every_shared_permission_for_each_action(): void
    {
        foreach (['admin', 'staff'] as $role) {
            foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
                $required = $this->permissionsFor($action);

                foreach ($required as $missingPermission) {
                    $permissions = array_values(array_diff($required, [$missingPermission]));
                    $this->actingAsRole($role, $permissions);
                    $category = WorkCategory::factory()->create();

                    $this->performAction($action, $category)->assertForbidden();
                }

                $this->actingAsRole($role, $required);
                $category = WorkCategory::factory()->create();
                $this->performAction($action, $category)->assertSuccessful();
            }
        }
    }

    public function test_action_permissions_do_not_cross_authorize(): void
    {
        foreach (array_keys(self::ACTION_PERMISSIONS) as $grantedAction) {
            $this->actingAsRole('admin', $this->permissionsFor($grantedAction));

            foreach (array_keys(self::ACTION_PERMISSIONS) as $requestedAction) {
                $category = WorkCategory::factory()->create();
                $response = $this->performAction($requestedAction, $category);

                $grantedAction === $requestedAction
                    ? $response->assertSuccessful()
                    : $response->assertForbidden();
            }
        }
    }

    public function test_client_designer_and_external_roles_are_always_forbidden(): void
    {
        $allPermissions = array_values(array_unique(array_merge(
            ...array_map(fn (string $action): array => $this->permissionsFor($action), array_keys(self::ACTION_PERMISSIONS)),
        )));

        foreach (['client', 'designer', 'external'] as $role) {
            if ($role === 'external') {
                Role::findOrCreate($role, 'web');
            }

            $this->actingAsRole($role, $allPermissions);
            $category = WorkCategory::factory()->create();

            foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
                $this->performAction($action, $category)->assertForbidden();
            }
        }
    }

    public function test_create_saves_trimmed_values_default_order_and_returns_only_safe_payload(): void
    {
        $this->actingAsRole('super-admin');
        $payload = [
            'name_ar' => '  رسوم متحركة  ',
            'name_en' => '  Motion Graphics  ',
            'slug' => '  motion-graphics  ',
        ];

        $response = $this->postJson(self::ENDPOINT, $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.category.name_ar', 'رسوم متحركة')
            ->assertJsonPath('data.category.name_en', 'Motion Graphics')
            ->assertJsonPath('data.category.slug', 'motion-graphics')
            ->assertJsonPath('data.category.sort_order', 0)
            ->assertJsonPath('data.category.disabled_at', null)
            ->assertJsonPath('data.category.is_active', true)
            ->assertJsonPath('data.category.works_count', 0)
            ->assertJsonPath('data.category.category_flags', [
                'is_used' => false,
                'is_unused' => true,
                'is_disabled' => false,
            ])
            ->assertJsonPath('message', 'تم إنشاء تصنيف الأعمال بنجاح')
            ->assertJsonPath('errors', null);

        $this->assertSafeCategoryKeys($response->json('data.category'));
        $this->assertDatabaseHas('work_categories', [
            'name_ar' => 'رسوم متحركة',
            'name_en' => 'Motion Graphics',
            'slug' => 'motion-graphics',
            'sort_order' => 0,
            'disabled_at' => null,
        ]);
    }

    public function test_duplicate_names_are_allowed_when_slug_is_distinct(): void
    {
        $this->actingAsRole('super-admin');
        $first = $this->validCreatePayload(['slug' => 'shared-name-one']);
        $second = $this->validCreatePayload(['slug' => 'shared-name-two']);

        $this->postJson(self::ENDPOINT, $first)->assertCreated();
        $this->postJson(self::ENDPOINT, $second)->assertCreated();

        $this->assertDatabaseCount('work_categories', 2);
    }

    public function test_duplicate_or_unsafe_slug_is_rejected_and_never_generated(): void
    {
        $this->actingAsRole('super-admin');
        WorkCategory::factory()->create(['slug' => 'existing-slug']);

        $this->postJson(self::ENDPOINT, $this->validCreatePayload(['slug' => 'existing-slug']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        foreach (['Uppercase-Slug', '-leading', 'trailing-', 'double--dash', 'unsafe_slug', 'مسار'] as $slug) {
            $this->postJson(self::ENDPOINT, $this->validCreatePayload(['slug' => $slug]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('slug');
        }

        $withoutSlug = $this->validCreatePayload();
        unset($withoutSlug['slug']);
        $this->postJson(self::ENDPOINT, $withoutSlug)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_create_field_boundaries_are_enforced(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([
            ['name_ar' => 'ا'],
            ['name_en' => 'A'],
            ['slug' => 'a'],
            ['name_ar' => str_repeat('ا', 121)],
            ['name_en' => str_repeat('a', 121)],
            ['slug' => str_repeat('a', 161)],
            ['sort_order' => -1],
            ['sort_order' => 2147483648],
        ] as $invalid) {
            $field = array_key_first($invalid);
            $this->postJson(self::ENDPOINT, $this->validCreatePayload($invalid))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        $this->postJson(self::ENDPOINT, $this->validCreatePayload([
            'name_ar' => str_repeat('ا', 120),
            'name_en' => str_repeat('a', 120),
            'slug' => str_repeat('a', 160),
            'sort_order' => 2147483647,
        ]))->assertCreated();
    }

    public function test_create_rejects_unknown_sensitive_fields_and_query_parameters(): void
    {
        $this->actingAsRole('super-admin');
        $fields = [
            'id', 'disabled_at', 'is_active', 'created_at', 'updated_at',
            'description', 'metadata', 'payload', 'category_id', 'work_ids',
            'works', 'tags', 'tag_ids', 'user_id', 'email', 'password',
            'token', 'cookie',
        ];
        $payload = $this->validCreatePayload();

        foreach ($fields as $field) {
            $payload[$field] = 'forbidden';
        }

        $this->postJson(self::ENDPOINT, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($fields);
        $this->postJson(self::ENDPOINT.'?token=secret&include=works', $this->validCreatePayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'include']);
    }

    public function test_create_records_one_safe_audit_event(): void
    {
        $this->actingAsRole('super-admin');
        $payload = $this->validCreatePayload([
            'name_ar' => 'اسم عربي خاص',
            'name_en' => 'Private English Name',
            'slug' => 'safe-audit-slug',
            'sort_order' => 9,
        ]);

        $categoryId = $this->postJson(self::ENDPOINT, $payload)
            ->assertCreated()
            ->json('data.category.id');
        $event = AuditEvent::query()->where('target_type', 'work_category')->sole();

        $this->assertSame('works.taxonomy.category.created', $event->event_type);
        $this->assertSame('works', $event->category);
        $this->assertSame($categoryId, $event->target_id);
        $this->assertSame(['category_id', 'is_active', 'slug', 'sort_order'], $this->sortedKeys($event->metadata));
        $this->assertSame('safe-audit-slug', $event->metadata['slug']);
        $this->assertMetadataExcludes($event, ['اسم عربي خاص', 'Private English Name', 'password', 'token', 'payload']);
    }

    public function test_reserved_allocator_keeps_new_category_separate_from_high_legacy_value(): void
    {
        $this->actingAsRole('super-admin');
        $probe = WorkCategory::factory()->create();
        $legacyMax = $probe->id + 100_000;
        $probe->delete();
        $work = Work::factory()->create(['category_id' => $legacyMax]);
        $originalUpdatedAt = $work->updated_at?->toJSON();

        $this->reservationMigration()->up();

        $categoryId = $this->postJson(self::ENDPOINT, $this->validCreatePayload())
            ->assertCreated()
            ->json('data.category.id');

        $this->assertGreaterThan($legacyMax, $categoryId);
        $this->assertSame($legacyMax, $work->refresh()->category_id);
        $this->assertSame($originalUpdatedAt, $work->updated_at?->toJSON());
        $this->assertNull($work->category()->first());
        $this->assertDatabaseCount('work_categories', 1);
        $this->assertDatabaseCount('work_tags', 0);
        $this->assertDatabaseCount('work_tag_assignments', 0);

        $this->getJson('/api/admin/works/taxonomy')
            ->assertOk()
            ->assertJsonPath('data.items.0.category', null)
            ->assertJsonPath('data.items.0.category_tracking.is_legacy_unmapped', true);
        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $categoryId);
    }

    public function test_update_changes_names_and_order_without_touching_slug_or_disabled_state(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create([
            'slug' => 'stable-slug',
            'disabled_at' => null,
            'sort_order' => 2,
        ]);

        $response = $this->patchJson(self::ENDPOINT.'/'.$category->id, [
            'name_ar' => 'اسم عربي محدث',
            'name_en' => 'Updated English Name',
            'sort_order' => 7,
        ])
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.category.slug', 'stable-slug')
            ->assertJsonPath('data.category.is_active', true)
            ->assertJsonPath('data.category.sort_order', 7);

        $this->assertSafeCategoryKeys($response->json('data.category'));
        $category->refresh();
        $this->assertSame('اسم عربي محدث', $category->name_ar);
        $this->assertSame('Updated English Name', $category->name_en);
        $this->assertSame('stable-slug', $category->slug);
        $this->assertNull($category->disabled_at);
    }

    public function test_update_accepts_one_field_but_rejects_slug_empty_body_and_invalid_bounds(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create(['slug' => 'immutable-slug']);

        $this->patchJson(self::ENDPOINT.'/'.$category->id, ['name_ar' => 'اسم منفرد'])
            ->assertOk()
            ->assertJsonPath('data.changed', true);
        $this->patchJson(self::ENDPOINT.'/'.$category->id, ['slug' => 'changed-slug'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug', 'category']);
        $this->patchJson(self::ENDPOINT.'/'.$category->id, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        foreach ([['name_en' => 'A'], ['sort_order' => -1], ['sort_order' => 2147483648]] as $invalid) {
            $this->patchJson(self::ENDPOINT.'/'.$category->id, $invalid)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(array_key_first($invalid));
        }

        $this->assertSame('immutable-slug', $category->refresh()->slug);
    }

    public function test_update_no_op_preserves_timestamp_and_creates_no_audit_event(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create([
            'name_ar' => 'اسم ثابت',
            'name_en' => 'Stable Name',
            'sort_order' => 4,
        ]);
        $updatedAt = $category->updated_at?->toJSON();

        $this->patchJson(self::ENDPOINT.'/'.$category->id, [
            'name_ar' => ' اسم ثابت ',
            'name_en' => ' Stable Name ',
            'sort_order' => 4,
        ])
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('message', 'لم تتغير بيانات تصنيف الأعمال');

        $this->assertSame($updatedAt, $category->refresh()->updated_at?->toJSON());
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_disabled_category_can_be_updated_without_being_reenabled(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->disabled()->create();
        $disabledAt = $category->disabled_at?->toJSON();

        $this->patchJson(self::ENDPOINT.'/'.$category->id, ['name_en' => 'Maintained Name'])
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.category.is_active', false);

        $this->assertSame($disabledAt, $category->refresh()->disabled_at?->toJSON());
    }

    public function test_update_missing_category_returns_404_without_audit(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson(self::ENDPOINT.'/999999', ['name_ar' => 'اسم صالح'])
            ->assertNotFound();

        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_update_audit_contains_only_changed_field_names_and_order_transition(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create([
            'name_ar' => 'اسم سري قديم',
            'sort_order' => 3,
        ]);

        $this->patchJson(self::ENDPOINT.'/'.$category->id, [
            'name_ar' => 'اسم سري جديد',
            'sort_order' => 8,
        ])->assertOk();

        $event = AuditEvent::query()->where('target_id', $category->id)->sole();
        $this->assertSame('works.taxonomy.category.updated', $event->event_type);
        $this->assertSame(
            ['category_id', 'changed_fields', 'current_sort_order', 'previous_sort_order'],
            $this->sortedKeys($event->metadata),
        );
        $this->assertSame(['name_ar', 'sort_order'], $event->metadata['changed_fields']);
        $this->assertSame(3, $event->metadata['previous_sort_order']);
        $this->assertSame(8, $event->metadata['current_sort_order']);
        $this->assertMetadataExcludes($event, ['اسم سري قديم', 'اسم سري جديد']);
    }

    public function test_disable_sets_timestamp_and_keeps_assigned_works_linked(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();
        $work = Work::factory()->create(['category_id' => $category->id]);

        $response = $this->patchJson(self::ENDPOINT.'/'.$category->id.'/disable')
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.category.is_active', false)
            ->assertJsonPath('data.category.works_count', 1)
            ->assertJsonPath('data.category.category_flags', [
                'is_used' => true,
                'is_unused' => false,
                'is_disabled' => true,
            ]);

        $this->assertSafeCategoryKeys($response->json('data.category'));
        $this->assertNotNull($category->refresh()->disabled_at);
        $this->assertSame($category->id, $work->refresh()->category_id);
        $this->assertTrue($work->category()->is($category));
    }

    public function test_repeated_disable_is_no_op_and_preserves_timestamps_and_audit_count(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();

        $this->patchJson(self::ENDPOINT.'/'.$category->id.'/disable')
            ->assertOk()
            ->assertJsonPath('data.changed', true);
        $category->refresh();
        $disabledAt = $category->disabled_at?->toJSON();
        $updatedAt = $category->updated_at?->toJSON();

        $this->patchJson(self::ENDPOINT.'/'.$category->id.'/disable')
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('message', 'تصنيف الأعمال معطل بالفعل');

        $category->refresh();
        $this->assertSame($disabledAt, $category->disabled_at?->toJSON());
        $this->assertSame($updatedAt, $category->updated_at?->toJSON());
        $this->assertSame(1, AuditEvent::query()->where('target_id', $category->id)->count());
    }

    public function test_disable_rejects_every_body_or_query_parameter_and_missing_category_is_404(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();

        $this->patchJson(self::ENDPOINT.'/'.$category->id.'/disable', ['disabled_at' => now()->toJSON()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('disabled_at');
        $this->patchJson(self::ENDPOINT.'/'.$category->id.'/disable?reason=test')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
        $this->patchJson(self::ENDPOINT.'/999999/disable')->assertNotFound();

        $this->assertTrue($category->refresh()->isActive());
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_disable_audit_uses_exact_safe_metadata(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create([
            'name_ar' => 'اسم تعطيل خاص',
            'name_en' => 'Private Disable Name',
        ]);
        Work::factory()->count(2)->create(['category_id' => $category->id]);

        $this->patchJson(self::ENDPOINT.'/'.$category->id.'/disable')->assertOk();

        $event = AuditEvent::query()->where('target_id', $category->id)->sole();
        $this->assertSame('works.taxonomy.category.disabled', $event->event_type);
        $this->assertSame(
            ['category_id', 'current_is_active', 'previous_is_active', 'works_count'],
            $this->sortedKeys($event->metadata),
        );
        $this->assertSame(2, $event->metadata['works_count']);
        $this->assertTrue($event->metadata['previous_is_active']);
        $this->assertFalse($event->metadata['current_is_active']);
        $this->assertMetadataExcludes($event, ['اسم تعطيل خاص', 'Private Disable Name']);
    }

    public function test_validation_forbidden_missing_and_no_op_do_not_record_category_action_audits(): void
    {
        $category = WorkCategory::factory()->create();

        $this->actingAsRole('super-admin');
        $this->postJson(self::ENDPOINT, [])->assertUnprocessable();
        $this->patchJson(self::ENDPOINT.'/999999', ['name_ar' => 'اسم صالح'])->assertNotFound();
        $this->patchJson(self::ENDPOINT.'/'.$category->id, ['name_ar' => $category->name_ar])
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->actingAsRole('admin', $this->sharedPermissions());
        $this->patchJson(self::ENDPOINT.'/'.$category->id.'/disable')->assertForbidden();

        $this->assertSame(0, AuditEvent::query()
            ->where('target_type', 'work_category')
            ->whereIn('event_type', [
                'works.taxonomy.category.created',
                'works.taxonomy.category.updated',
                'works.taxonomy.category.disabled',
            ])
            ->count());
    }

    public function test_routes_use_only_the_contract_methods_constraints_and_controller(): void
    {
        $store = Route::getRoutes()->match(Request::create(self::ENDPOINT, 'POST'));
        $update = Route::getRoutes()->match(Request::create(self::ENDPOINT.'/123', 'PATCH'));
        $disable = Route::getRoutes()->match(Request::create(self::ENDPOINT.'/123/disable', 'PATCH'));

        $this->assertSame(WorksTaxonomyCategoryActionController::class.'@store', $store->getActionName());
        $this->assertSame(WorksTaxonomyCategoryActionController::class.'@update', $update->getActionName());
        $this->assertSame(WorksTaxonomyCategoryActionController::class.'@disable', $disable->getActionName());
        $this->assertSame(['POST'], $store->methods());
        $this->assertSame(['PATCH'], $update->methods());
        $this->assertSame(['PATCH'], $disable->methods());
        $this->assertSame('[0-9]+', $update->wheres['category'] ?? null);
        $this->assertSame('[0-9]+', $disable->wheres['category'] ?? null);

        $routes = collect(Route::getRoutes()->getRoutes())->values();
        $showPosition = $routes->search(fn ($route): bool => $route->uri() === 'api/admin/works/{work}');

        foreach ([
            WorksTaxonomyCategoryActionController::class.'@store',
            WorksTaxonomyCategoryActionController::class.'@update',
            WorksTaxonomyCategoryActionController::class.'@disable',
        ] as $actionName) {
            $position = $routes->search(fn ($route): bool => $route->getActionName() === $actionName);
            $this->assertIsInt($position);
            $this->assertLessThan($showPosition, $position);
        }
    }

    public function test_no_enable_restore_delete_put_or_assignment_action_exists(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();

        $this->putJson(self::ENDPOINT.'/'.$category->id, ['name_ar' => 'اسم جديد'])
            ->assertMethodNotAllowed();
        $this->deleteJson(self::ENDPOINT.'/'.$category->id)->assertMethodNotAllowed();
        $this->patchJson(self::ENDPOINT.'/'.$category->id.'/enable')->assertNotFound();
        $this->patchJson(self::ENDPOINT.'/'.$category->id.'/restore')->assertNotFound();
        $this->patchJson(self::ENDPOINT.'/'.$category->id.'/assign')->assertNotFound();

        $this->assertDatabaseHas('work_categories', ['id' => $category->id]);
    }

    public function test_existing_taxonomy_and_catalog_get_routes_remain_available(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/taxonomy')->assertOk();
        $this->getJson(self::ENDPOINT)->assertOk();
        $this->getJson('/api/admin/works/taxonomy/tags')->assertOk();
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function validCreatePayload(array $overrides = []): array
    {
        return array_merge([
            'name_ar' => 'تصنيف أعمال جديد',
            'name_en' => 'New Work Category',
            'slug' => 'new-work-category',
            'sort_order' => 3,
        ], $overrides);
    }

    /** @return list<string> */
    private function sharedPermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.taxonomy.view',
            'admin.works.taxonomy.categories.view',
        ];
    }

    /** @return list<string> */
    private function permissionsFor(string $action): array
    {
        return [...$this->sharedPermissions(), self::ACTION_PERMISSIONS[$action]];
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

    private function performAction(string $action, WorkCategory $category): TestResponse
    {
        return match ($action) {
            'create' => $this->postJson(self::ENDPOINT, $this->validCreatePayload([
                'slug' => 'permission-category-'.$category->id,
            ])),
            'update' => $this->patchJson(self::ENDPOINT.'/'.$category->id, [
                'name_en' => 'Permission Updated '.$category->id,
            ]),
            'disable' => $this->patchJson(self::ENDPOINT.'/'.$category->id.'/disable'),
        };
    }

    /** @param array<string, mixed> $payload */
    private function assertSafeCategoryKeys(array $payload): void
    {
        $this->assertSame([
            'category_flags',
            'created_at',
            'disabled_at',
            'id',
            'is_active',
            'name_ar',
            'name_en',
            'slug',
            'sort_order',
            'updated_at',
            'works_count',
        ], $this->sortedKeys($payload));
    }

    /** @param array<string, mixed> $values @return list<string> */
    private function sortedKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return $keys;
    }

    /** @param list<string> $forbiddenValues */
    private function assertMetadataExcludes(AuditEvent $event, array $forbiddenValues): void
    {
        $metadata = json_encode($event->metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        foreach ($forbiddenValues as $value) {
            $this->assertStringNotContainsString($value, $metadata);
        }
    }

    private function reservationMigration(): Migration
    {
        return require database_path(
            'migrations/2026_07_17_000003_reserve_work_category_ids_above_legacy_values.php'
        );
    }
}
