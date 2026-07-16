<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksTaxonomyTagActionController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkTag;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksTaxonomyTagActionsApiTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/admin/works/taxonomy/tags';

    private const ACTION_PERMISSIONS = [
        'create' => 'admin.works.taxonomy.tags.create',
        'update' => 'admin.works.taxonomy.tags.update',
        'disable' => 'admin.works.taxonomy.tags.disable',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_requests_are_rejected_for_all_actions(): void
    {
        $this->postJson(self::ENDPOINT, $this->validCreatePayload())->assertUnauthorized();
        $this->patchJson(self::ENDPOINT.'/1', ['name_ar' => 'وسم جديد'])->assertUnauthorized();
        $this->patchJson(self::ENDPOINT.'/1/disable')->assertUnauthorized();
    }

    public function test_super_admin_can_create_update_and_disable_tags(): void
    {
        $this->actingAsRole('super-admin');

        $tagId = $this->postJson(self::ENDPOINT, $this->validCreatePayload())
            ->assertCreated()
            ->json('data.tag.id');
        $this->patchJson(self::ENDPOINT.'/'.$tagId, ['name_en' => 'Updated Tag'])
            ->assertOk()
            ->assertJsonPath('data.changed', true);
        $this->patchJson(self::ENDPOINT.'/'.$tagId.'/disable')
            ->assertOk()
            ->assertJsonPath('data.changed', true);
    }

    public function test_admin_and_staff_require_all_shared_and_action_permissions(): void
    {
        foreach (['admin', 'staff'] as $role) {
            foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
                $required = $this->permissionsFor($action);

                foreach ($required as $missingPermission) {
                    $this->actingAsRole($role, array_values(array_diff($required, [$missingPermission])));
                    $this->performAction($action, WorkTag::factory()->create())->assertForbidden();
                }

                $this->actingAsRole($role, $required);
                $this->performAction($action, WorkTag::factory()->create())->assertSuccessful();
            }
        }
    }

    public function test_action_permissions_do_not_cross_authorize(): void
    {
        foreach (array_keys(self::ACTION_PERMISSIONS) as $grantedAction) {
            $this->actingAsRole('admin', $this->permissionsFor($grantedAction));

            foreach (array_keys(self::ACTION_PERMISSIONS) as $requestedAction) {
                $response = $this->performAction($requestedAction, WorkTag::factory()->create());

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

            foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
                $this->performAction($action, WorkTag::factory()->create())->assertForbidden();
            }
        }
    }

    public function test_create_trims_values_defaults_order_and_returns_safe_payload(): void
    {
        $this->actingAsRole('super-admin');

        $response = $this->postJson(self::ENDPOINT, [
            'name_ar' => '  تصميم شعار  ',
            'name_en' => '  Logo Design  ',
            'slug' => '  logo-design  ',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.tag.name_ar', 'تصميم شعار')
            ->assertJsonPath('data.tag.name_en', 'Logo Design')
            ->assertJsonPath('data.tag.slug', 'logo-design')
            ->assertJsonPath('data.tag.sort_order', 0)
            ->assertJsonPath('data.tag.disabled_at', null)
            ->assertJsonPath('data.tag.is_active', true)
            ->assertJsonPath('data.tag.works_count', 0)
            ->assertJsonPath('data.tag.tag_flags', [
                'is_used' => false,
                'is_unused' => true,
                'is_disabled' => false,
            ])
            ->assertJsonPath('message', 'تم إنشاء وسم الأعمال بنجاح')
            ->assertJsonPath('errors', null);

        $this->assertSafeTagKeys($response->json('data.tag'));
        $this->assertDatabaseHas('work_tags', [
            'name_ar' => 'تصميم شعار',
            'name_en' => 'Logo Design',
            'slug' => 'logo-design',
            'sort_order' => 0,
            'disabled_at' => null,
        ]);
        $this->assertDatabaseCount('work_tag_assignments', 0);
    }

    public function test_duplicate_names_are_allowed_but_duplicate_slug_is_rejected(): void
    {
        $this->actingAsRole('super-admin');

        $this->postJson(self::ENDPOINT, $this->validCreatePayload(['slug' => 'shared-tag-one']))
            ->assertCreated();
        $this->postJson(self::ENDPOINT, $this->validCreatePayload(['slug' => 'shared-tag-two']))
            ->assertCreated();
        $this->postJson(self::ENDPOINT, $this->validCreatePayload(['slug' => 'shared-tag-one']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->assertDatabaseCount('work_tags', 2);
    }

    public function test_slug_is_required_not_generated_and_must_use_safe_lowercase_format(): void
    {
        $this->actingAsRole('super-admin');
        $withoutSlug = $this->validCreatePayload();
        unset($withoutSlug['slug']);

        $this->postJson(self::ENDPOINT, $withoutSlug)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        foreach (['Uppercase-Tag', '-leading', 'trailing-', 'double--dash', 'unsafe_tag', 'وسم'] as $slug) {
            $this->postJson(self::ENDPOINT, $this->validCreatePayload(['slug' => $slug]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('slug');
        }
    }

    public function test_create_field_boundaries_are_enforced(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([
            ['name_ar' => 'و'],
            ['name_en' => 'T'],
            ['slug' => 'a'],
            ['name_ar' => str_repeat('و', 121)],
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
            'name_ar' => str_repeat('و', 120),
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
            'description', 'metadata', 'payload', 'work_id', 'work_ids', 'works',
            'category_id', 'category_ids', 'tag_id', 'tag_ids', 'merge_into',
            'assigned_by', 'email', 'password', 'token', 'cookie',
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

    public function test_create_records_one_safe_audit_event_without_assignment(): void
    {
        $this->actingAsRole('super-admin');
        $tagId = $this->postJson(self::ENDPOINT, $this->validCreatePayload([
            'name_ar' => 'اسم وسم خاص',
            'name_en' => 'Private Tag Name',
            'slug' => 'safe-tag-audit',
            'sort_order' => 9,
        ]))->assertCreated()->json('data.tag.id');
        $event = AuditEvent::query()->where('target_type', 'work_tag')->sole();

        $this->assertSame('works.taxonomy.tag.created', $event->event_type);
        $this->assertSame('works', $event->category);
        $this->assertSame($tagId, $event->target_id);
        $this->assertSame(['is_active', 'slug', 'sort_order', 'tag_id'], $this->sortedKeys($event->metadata));
        $this->assertMetadataExcludes($event, ['اسم وسم خاص', 'Private Tag Name', 'payload', 'token']);
        $this->assertDatabaseCount('work_tag_assignments', 0);
    }

    public function test_update_changes_names_and_order_without_touching_slug_disabled_state_or_pivot(): void
    {
        $this->actingAsRole('super-admin');
        $tag = WorkTag::factory()->create([
            'slug' => 'stable-tag-slug',
            'sort_order' => 2,
        ]);
        $work = Work::factory()->create(['category_id' => 77]);
        $tag->works()->attach($work->id);
        $workUpdatedAt = $work->updated_at?->toJSON();

        $response = $this->patchJson(self::ENDPOINT.'/'.$tag->id, [
            'name_ar' => 'وسم عربي محدث',
            'name_en' => 'Updated English Tag',
            'sort_order' => 7,
        ])
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.tag.slug', 'stable-tag-slug')
            ->assertJsonPath('data.tag.is_active', true)
            ->assertJsonPath('data.tag.works_count', 1);

        $this->assertSafeTagKeys($response->json('data.tag'));
        $tag->refresh();
        $this->assertSame('stable-tag-slug', $tag->slug);
        $this->assertNull($tag->disabled_at);
        $this->assertDatabaseHas('work_tag_assignments', [
            'work_id' => $work->id,
            'work_tag_id' => $tag->id,
        ]);
        $this->assertSame(77, $work->refresh()->category_id);
        $this->assertSame($workUpdatedAt, $work->updated_at?->toJSON());
    }

    public function test_update_accepts_one_field_but_rejects_slug_empty_body_and_invalid_values(): void
    {
        $this->actingAsRole('super-admin');
        $tag = WorkTag::factory()->create(['slug' => 'immutable-tag']);

        $this->patchJson(self::ENDPOINT.'/'.$tag->id, ['name_ar' => 'وسم منفرد'])
            ->assertOk()
            ->assertJsonPath('data.changed', true);
        $this->patchJson(self::ENDPOINT.'/'.$tag->id, ['slug' => 'changed-tag'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug', 'tag']);
        $this->patchJson(self::ENDPOINT.'/'.$tag->id, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag');

        foreach ([['name_en' => 'T'], ['sort_order' => -1], ['sort_order' => 2147483648]] as $invalid) {
            $this->patchJson(self::ENDPOINT.'/'.$tag->id, $invalid)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(array_key_first($invalid));
        }

        $this->assertSame('immutable-tag', $tag->refresh()->slug);
    }

    public function test_update_no_op_preserves_timestamp_and_creates_no_audit_event(): void
    {
        $this->actingAsRole('super-admin');
        $tag = WorkTag::factory()->create([
            'name_ar' => 'وسم ثابت',
            'name_en' => 'Stable Tag',
            'sort_order' => 4,
        ]);
        $updatedAt = $tag->updated_at?->toJSON();

        $this->patchJson(self::ENDPOINT.'/'.$tag->id, [
            'name_ar' => ' وسم ثابت ',
            'name_en' => ' Stable Tag ',
            'sort_order' => 4,
        ])
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('message', 'لم تتغير بيانات وسم الأعمال');

        $this->assertSame($updatedAt, $tag->refresh()->updated_at?->toJSON());
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_disabled_tag_can_be_updated_without_reactivation(): void
    {
        $this->actingAsRole('super-admin');
        $tag = WorkTag::factory()->disabled()->create();
        $disabledAt = $tag->disabled_at?->toJSON();

        $this->patchJson(self::ENDPOINT.'/'.$tag->id, ['name_en' => 'Maintained Tag'])
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.tag.is_active', false);

        $this->assertSame($disabledAt, $tag->refresh()->disabled_at?->toJSON());
    }

    public function test_update_missing_tag_returns_404_without_audit(): void
    {
        $this->actingAsRole('super-admin');
        $this->patchJson(self::ENDPOINT.'/999999', ['name_ar' => 'وسم صالح'])->assertNotFound();
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_update_audit_contains_only_field_names_and_sort_transition(): void
    {
        $this->actingAsRole('super-admin');
        $tag = WorkTag::factory()->create(['name_ar' => 'وسم خاص قديم', 'sort_order' => 3]);

        $this->patchJson(self::ENDPOINT.'/'.$tag->id, [
            'name_ar' => 'وسم خاص جديد',
            'sort_order' => 8,
        ])->assertOk();

        $event = AuditEvent::query()->where('target_id', $tag->id)->sole();
        $this->assertSame('works.taxonomy.tag.updated', $event->event_type);
        $this->assertSame(
            ['changed_fields', 'current_sort_order', 'previous_sort_order', 'tag_id'],
            $this->sortedKeys($event->metadata),
        );
        $this->assertSame(['name_ar', 'sort_order'], $event->metadata['changed_fields']);
        $this->assertSame(3, $event->metadata['previous_sort_order']);
        $this->assertSame(8, $event->metadata['current_sort_order']);
        $this->assertMetadataExcludes($event, ['وسم خاص قديم', 'وسم خاص جديد']);
    }

    public function test_disable_used_tag_sets_timestamp_and_preserves_all_assignments(): void
    {
        $this->actingAsRole('super-admin');
        $tag = WorkTag::factory()->create();
        $works = Work::factory()->count(2)->create(['category_id' => 91]);
        $tag->works()->attach($works->pluck('id'));
        $originalTagState = $tag->only(['name_ar', 'name_en', 'slug', 'sort_order']);
        $originalWorkTimestamps = $works->mapWithKeys(
            fn (Work $work): array => [$work->id => $work->updated_at?->toJSON()],
        );

        $response = $this->patchJson(self::ENDPOINT.'/'.$tag->id.'/disable')
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.tag.is_active', false)
            ->assertJsonPath('data.tag.works_count', 2)
            ->assertJsonPath('data.tag.tag_flags', [
                'is_used' => true,
                'is_unused' => false,
                'is_disabled' => true,
            ]);

        $this->assertSafeTagKeys($response->json('data.tag'));
        $this->assertNotNull($tag->refresh()->disabled_at);
        $this->assertSame($originalTagState, $tag->only(['name_ar', 'name_en', 'slug', 'sort_order']));
        $this->assertSame(2, $tag->works()->count());
        $this->assertDatabaseCount('work_tag_assignments', 2);
        $this->assertSame([91], Work::query()->distinct()->pluck('category_id')->all());

        foreach ($works as $work) {
            $this->assertSame($originalWorkTimestamps[$work->id], $work->refresh()->updated_at?->toJSON());
        }
    }

    public function test_repeated_disable_is_no_op_and_preserves_timestamps_and_audit_count(): void
    {
        $this->actingAsRole('super-admin');
        $tag = WorkTag::factory()->create();

        $this->patchJson(self::ENDPOINT.'/'.$tag->id.'/disable')
            ->assertOk()
            ->assertJsonPath('data.changed', true);
        $tag->refresh();
        $disabledAt = $tag->disabled_at?->toJSON();
        $updatedAt = $tag->updated_at?->toJSON();

        $this->patchJson(self::ENDPOINT.'/'.$tag->id.'/disable')
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('message', 'وسم الأعمال معطل بالفعل');

        $tag->refresh();
        $this->assertSame($disabledAt, $tag->disabled_at?->toJSON());
        $this->assertSame($updatedAt, $tag->updated_at?->toJSON());
        $this->assertSame(1, AuditEvent::query()->where('target_id', $tag->id)->count());
    }

    public function test_disable_rejects_body_and_query_and_missing_tag_is_404(): void
    {
        $this->actingAsRole('super-admin');
        $tag = WorkTag::factory()->create();

        $this->patchJson(self::ENDPOINT.'/'.$tag->id.'/disable', ['disabled_at' => now()->toJSON()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('disabled_at');
        $this->patchJson(self::ENDPOINT.'/'.$tag->id.'/disable?reason=test')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
        $this->patchJson(self::ENDPOINT.'/999999/disable')->assertNotFound();

        $this->assertTrue($tag->refresh()->isActive());
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_disable_audit_uses_exact_safe_metadata(): void
    {
        $this->actingAsRole('super-admin');
        $tag = WorkTag::factory()->create([
            'name_ar' => 'وسم تعطيل خاص',
            'name_en' => 'Private Disable Tag',
        ]);
        Work::factory()->count(2)->create()->each(fn (Work $work) => $tag->works()->attach($work->id));

        $this->patchJson(self::ENDPOINT.'/'.$tag->id.'/disable')->assertOk();

        $event = AuditEvent::query()->where('target_id', $tag->id)->sole();
        $this->assertSame('works.taxonomy.tag.disabled', $event->event_type);
        $this->assertSame(
            ['current_is_active', 'previous_is_active', 'tag_id', 'works_count'],
            $this->sortedKeys($event->metadata),
        );
        $this->assertSame(2, $event->metadata['works_count']);
        $this->assertTrue($event->metadata['previous_is_active']);
        $this->assertFalse($event->metadata['current_is_active']);
        $this->assertMetadataExcludes($event, ['وسم تعطيل خاص', 'Private Disable Tag']);
    }

    public function test_validation_forbidden_missing_and_no_op_do_not_record_tag_action_audits(): void
    {
        $tag = WorkTag::factory()->create();
        $this->actingAsRole('super-admin');
        $this->postJson(self::ENDPOINT, [])->assertUnprocessable();
        $this->patchJson(self::ENDPOINT.'/999999', ['name_ar' => 'وسم صالح'])->assertNotFound();
        $this->patchJson(self::ENDPOINT.'/'.$tag->id, ['name_ar' => $tag->name_ar])
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->actingAsRole('admin', $this->sharedPermissions());
        $this->patchJson(self::ENDPOINT.'/'.$tag->id.'/disable')->assertForbidden();

        $this->assertSame(0, AuditEvent::query()
            ->where('target_type', 'work_tag')
            ->whereIn('event_type', [
                'works.taxonomy.tag.created',
                'works.taxonomy.tag.updated',
                'works.taxonomy.tag.disabled',
            ])
            ->count());
    }

    public function test_routes_use_correct_controller_constraints_order_and_position(): void
    {
        $store = Route::getRoutes()->match(Request::create(self::ENDPOINT, 'POST'));
        $update = Route::getRoutes()->match(Request::create(self::ENDPOINT.'/123', 'PATCH'));
        $disable = Route::getRoutes()->match(Request::create(self::ENDPOINT.'/123/disable', 'PATCH'));

        $this->assertSame(WorksTaxonomyTagActionController::class.'@store', $store->getActionName());
        $this->assertSame(WorksTaxonomyTagActionController::class.'@update', $update->getActionName());
        $this->assertSame(WorksTaxonomyTagActionController::class.'@disable', $disable->getActionName());
        $this->assertSame(['POST'], $store->methods());
        $this->assertSame(['PATCH'], $update->methods());
        $this->assertSame(['PATCH'], $disable->methods());
        $this->assertSame('[0-9]+', $update->wheres['tag'] ?? null);
        $this->assertSame('[0-9]+', $disable->wheres['tag'] ?? null);

        $routes = collect(Route::getRoutes()->getRoutes())->values();
        $showPosition = $routes->search(fn ($route): bool => $route->uri() === 'api/admin/works/{work}');
        $disablePosition = $routes->search(
            fn ($route): bool => $route->getActionName() === WorksTaxonomyTagActionController::class.'@disable',
        );
        $updatePosition = $routes->search(
            fn ($route): bool => $route->getActionName() === WorksTaxonomyTagActionController::class.'@update',
        );

        foreach (['store', 'update', 'disable'] as $action) {
            $position = $routes->search(
                fn ($route): bool => $route->getActionName() === WorksTaxonomyTagActionController::class.'@'.$action,
            );
            $this->assertIsInt($position);
            $this->assertLessThan($showPosition, $position);
        }

        $this->assertLessThan($updatePosition, $disablePosition);
    }

    public function test_no_put_delete_enable_restore_merge_or_assignment_routes_exist(): void
    {
        $this->actingAsRole('super-admin');
        $tag = WorkTag::factory()->create();

        $this->putJson(self::ENDPOINT.'/'.$tag->id, ['name_ar' => 'وسم جديد'])->assertMethodNotAllowed();
        $this->deleteJson(self::ENDPOINT.'/'.$tag->id)->assertMethodNotAllowed();

        foreach (['enable', 'restore', 'merge', 'assign', 'detach'] as $action) {
            $this->patchJson(self::ENDPOINT.'/'.$tag->id.'/'.$action)->assertNotFound();
        }

        $this->assertDatabaseHas('work_tags', ['id' => $tag->id]);
    }

    public function test_existing_get_routes_and_category_create_remain_available(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson(self::ENDPOINT)->assertOk();
        $this->getJson('/api/admin/works/taxonomy')->assertOk();
        $this->postJson('/api/admin/works/taxonomy/categories', [
            'name_ar' => 'تصنيف مستقل',
            'name_en' => 'Independent Category',
            'slug' => 'independent-category',
            'sort_order' => 0,
        ])->assertCreated();
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function validCreatePayload(array $overrides = []): array
    {
        return array_merge([
            'name_ar' => 'وسم أعمال جديد',
            'name_en' => 'New Work Tag',
            'slug' => 'new-work-tag',
            'sort_order' => 3,
        ], $overrides);
    }

    /** @return list<string> */
    private function sharedPermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.taxonomy.view',
            'admin.works.taxonomy.tags.view',
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

    private function performAction(string $action, WorkTag $tag): TestResponse
    {
        return match ($action) {
            'create' => $this->postJson(self::ENDPOINT, $this->validCreatePayload([
                'slug' => 'permission-tag-'.$tag->id,
            ])),
            'update' => $this->patchJson(self::ENDPOINT.'/'.$tag->id, [
                'name_en' => 'Permission Updated '.$tag->id,
            ]),
            'disable' => $this->patchJson(self::ENDPOINT.'/'.$tag->id.'/disable'),
        };
    }

    /** @param array<string, mixed> $payload */
    private function assertSafeTagKeys(array $payload): void
    {
        $this->assertSame([
            'created_at',
            'disabled_at',
            'id',
            'is_active',
            'name_ar',
            'name_en',
            'slug',
            'sort_order',
            'tag_flags',
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
}
