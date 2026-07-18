<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksSettingsController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\WorkSetting;
use App\Services\Audit\AuditEventLogger;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksSettingsMutationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_patch_route_resolves_to_the_settings_update_action(): void
    {
        $route = Route::getRoutes()->match(Request::create('/api/admin/works/settings', 'PATCH'));

        $this->assertSame(WorksSettingsController::class.'@update', $route->getActionName());
    }

    public function test_no_post_put_or_delete_settings_routes_exist(): void
    {
        $routes = $this->settingsRoutes();

        $this->assertCount(2, $routes);
        $this->assertTrue($routes->contains(
            fn ($route): bool => $route->methods() === ['GET', 'HEAD'],
        ));
        $this->assertTrue($routes->contains(
            fn ($route): bool => $route->methods() === ['PATCH'],
        ));
        $this->assertFalse($routes->contains(
            fn ($route): bool => array_intersect(['POST', 'PUT', 'DELETE'], $route->methods()) !== [],
        ));
    }

    public function test_unauthenticated_patch_returns_401(): void
    {
        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ]))->assertUnauthorized();
    }

    public function test_client_and_designer_are_forbidden_even_with_all_settings_permissions(): void
    {
        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $this->allSettingsPermissions());

            $this->patchJson('/api/admin/works/settings', $this->payload([
                'review_sla_hours' => 48,
            ]))->assertForbidden();
        }
    }

    public function test_non_internal_role_is_forbidden(): void
    {
        Role::create(['name' => 'contractor', 'guard_name' => 'web']);
        $this->actingAsRole('contractor', $this->allSettingsPermissions());

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ]))->assertForbidden();
    }

    public function test_admin_and_staff_without_access_are_forbidden(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, [
                'admin.works.settings.view',
                'admin.works.settings.manage',
            ]);

            $this->patchJson('/api/admin/works/settings', $this->payload([
                'review_sla_hours' => 48,
            ]))->assertForbidden();
        }
    }

    public function test_access_and_view_without_a_manage_permission_are_forbidden(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, $this->settingsViewPermissions());

            $this->patchJson('/api/admin/works/settings', $this->payload([
                'review_sla_hours' => 48,
            ]))->assertForbidden();
        }
    }

    public function test_global_settings_manage_permission_allows_all_current_fields(): void
    {
        $this->actingAsRole('admin', [
            ...$this->settingsViewPermissions(),
            'admin.works.settings.manage',
        ]);

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
            'direct_publish_trust_enabled' => true,
            'media_limits' => [
                'max_items' => 12,
                'max_file_size_kb' => 51200,
                'allowed_types' => ['image', 'video'],
            ],
        ]))
            ->assertOk()
            ->assertJsonPath('data.changed', true);
    }

    public function test_review_sla_permission_allows_only_review_sla_hours(): void
    {
        $this->actingAsRole('staff', [
            ...$this->settingsViewPermissions(),
            'admin.works.settings.review_sla.manage',
        ]);

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ]))->assertOk();

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'direct_publish_trust_enabled' => true,
        ], version: 2))->assertForbidden();
    }

    public function test_direct_publish_permission_allows_only_its_field(): void
    {
        $this->actingAsRole('staff', [
            ...$this->settingsViewPermissions(),
            'admin.works.settings.direct_publish_trust.manage',
        ]);

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'direct_publish_trust_enabled' => true,
        ]))->assertOk();

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ], version: 2))->assertForbidden();
    }

    public function test_media_limits_permission_allows_only_media_limits(): void
    {
        $this->actingAsRole('staff', [
            ...$this->settingsViewPermissions(),
            'admin.works.settings.media_limits.manage',
        ]);

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'media_limits' => ['max_items' => 12],
        ]))->assertOk();

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ], version: 2))->assertForbidden();
    }

    public function test_workflow_manage_alone_does_not_allow_current_fields(): void
    {
        $this->actingAsRole('admin', [
            ...$this->settingsViewPermissions(),
            'admin.works.settings.workflow.manage',
        ]);

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ]))->assertForbidden();
    }

    public function test_multi_field_authorization_failure_rejects_the_whole_update(): void
    {
        $this->actingAsRole('admin', [
            ...$this->settingsViewPermissions(),
            'admin.works.settings.review_sla.manage',
        ]);
        $before = $this->settingSnapshot($this->globalSetting());

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
            'direct_publish_trust_enabled' => true,
        ]))->assertForbidden();

        $this->assertSame($before, $this->settingSnapshot($this->globalSetting()));
    }

    public function test_version_is_required_and_must_be_a_positive_integer(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([null, 0, -1, 'one', '1', 1.5] as $version) {
            $payload = ['values' => ['review_sla_hours' => 48]];

            if ($version !== null) {
                $payload['version'] = $version;
            }

            $this->patchJson('/api/admin/works/settings', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('version');
        }
    }

    public function test_values_are_required_and_cannot_be_empty(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([['version' => 1], ['version' => 1, 'values' => []]] as $payload) {
            $this->patchJson('/api/admin/works/settings', $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('values');
        }

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'media_limits' => [],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('values.media_limits');
    }

    public function test_unknown_top_level_body_fields_return_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson('/api/admin/works/settings', [
            ...$this->payload(['review_sla_hours' => 48]),
            'metadata' => ['hidden' => true],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metadata');
    }

    public function test_unknown_values_fields_return_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'unknown_setting' => true,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('values');
    }

    public function test_unknown_media_limit_fields_return_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'media_limits' => ['metadata' => ['hidden' => true]],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('values.media_limits');
    }

    public function test_review_sla_accepts_null_and_boundaries_and_rejects_other_values(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([null, 1, 720] as $validValue) {
            $this->resetGlobal();
            $this->patchJson('/api/admin/works/settings', $this->payload([
                'review_sla_hours' => $validValue,
            ]))->assertOk();
        }

        foreach ([0, 721, '48', 1.5, false] as $invalidValue) {
            $this->resetGlobal();
            $this->patchJson('/api/admin/works/settings', $this->payload([
                'review_sla_hours' => $invalidValue,
            ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('values.review_sla_hours');
        }
    }

    public function test_direct_publish_trust_accepts_only_actual_booleans(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([true, false] as $validValue) {
            $this->resetGlobal();
            $this->patchJson('/api/admin/works/settings', $this->payload([
                'direct_publish_trust_enabled' => $validValue,
            ]))->assertOk();
        }

        foreach (['false', 'true', '0', '1', 0, 1, null] as $invalidValue) {
            $this->resetGlobal();
            $this->patchJson('/api/admin/works/settings', $this->payload([
                'direct_publish_trust_enabled' => $invalidValue,
            ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('values.direct_publish_trust_enabled');
        }
    }

    public function test_max_items_accepts_null_and_boundaries_and_rejects_other_values(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([null, 1, 100] as $validValue) {
            $this->resetGlobal();
            $this->patchJson('/api/admin/works/settings', $this->payload([
                'media_limits' => ['max_items' => $validValue],
            ]))->assertOk();
        }

        foreach ([0, 101, '12', 1.5, false] as $invalidValue) {
            $this->resetGlobal();
            $this->patchJson('/api/admin/works/settings', $this->payload([
                'media_limits' => ['max_items' => $invalidValue],
            ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('values.media_limits.max_items');
        }
    }

    public function test_max_file_size_accepts_null_and_boundaries_and_rejects_other_values(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([null, 1, 2097152] as $validValue) {
            $this->resetGlobal();
            $this->patchJson('/api/admin/works/settings', $this->payload([
                'media_limits' => ['max_file_size_kb' => $validValue],
            ]))->assertOk();
        }

        foreach ([0, 2097153, '51200', 1.5, false] as $invalidValue) {
            $this->resetGlobal();
            $this->patchJson('/api/admin/works/settings', $this->payload([
                'media_limits' => ['max_file_size_kb' => $invalidValue],
            ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('values.media_limits.max_file_size_kb');
        }
    }

    public function test_allowed_types_accepts_null_and_known_types_and_rejects_invalid_arrays(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([null, ['image'], ['video'], ['gallery'], ['image', 'video', 'gallery']] as $validValue) {
            $this->resetGlobal();
            $this->patchJson('/api/admin/works/settings', $this->payload([
                'media_limits' => ['allowed_types' => $validValue],
            ]))->assertOk();
        }

        foreach ([['image', 'image'], ['audio'], ['image', 1], 'image'] as $invalidValue) {
            $this->resetGlobal();
            $this->patchJson('/api/admin/works/settings', $this->payload([
                'media_limits' => ['allowed_types' => $invalidValue],
            ]))->assertUnprocessable();
        }
    }

    public function test_query_parameters_are_rejected(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson('/api/admin/works/settings?metadata=blocked', $this->payload([
            'review_sla_hours' => 48,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metadata');
    }

    public function test_single_field_update_preserves_all_other_values(): void
    {
        $this->actingAsRole('super-admin');
        $this->setGlobalValues([
            'review_sla_hours' => 24,
            'direct_publish_trust_enabled' => true,
            'media_limits' => [
                'max_items' => 10,
                'max_file_size_kb' => 4096,
                'allowed_types' => ['image'],
            ],
        ]);

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ]))
            ->assertOk()
            ->assertJsonPath('data.stored_settings.values', [
                'review_sla_hours' => 48,
                'direct_publish_trust_enabled' => true,
                'media_limits' => [
                    'max_items' => 10,
                    'max_file_size_kb' => 4096,
                    'allowed_types' => ['image'],
                ],
            ]);
    }

    public function test_partial_media_limits_update_preserves_unsubmitted_nested_values(): void
    {
        $this->actingAsRole('super-admin');
        $this->setGlobalValues([
            'review_sla_hours' => null,
            'direct_publish_trust_enabled' => false,
            'media_limits' => [
                'max_items' => 10,
                'max_file_size_kb' => 4096,
                'allowed_types' => ['image', 'video'],
            ],
        ]);

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'media_limits' => ['max_items' => 20],
        ]))
            ->assertOk()
            ->assertJsonPath('data.stored_settings.values.media_limits', [
                'max_items' => 20,
                'max_file_size_kb' => 4096,
                'allowed_types' => ['image', 'video'],
            ]);
    }

    public function test_null_clears_every_nullable_setting(): void
    {
        $this->actingAsRole('super-admin');
        $this->setGlobalValues([
            'review_sla_hours' => 24,
            'direct_publish_trust_enabled' => true,
            'media_limits' => [
                'max_items' => 10,
                'max_file_size_kb' => 4096,
                'allowed_types' => ['image'],
            ],
        ]);

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => null,
            'media_limits' => [
                'max_items' => null,
                'max_file_size_kb' => null,
                'allowed_types' => null,
            ],
        ]))
            ->assertOk()
            ->assertJsonPath('data.stored_settings.values.review_sla_hours', null)
            ->assertJsonPath('data.stored_settings.values.media_limits', [
                'max_items' => null,
                'max_file_size_kb' => null,
                'allowed_types' => null,
            ]);
    }

    public function test_real_change_increments_version_exactly_once(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ]))
            ->assertOk()
            ->assertJsonPath('data.previous_version', 1)
            ->assertJsonPath('data.current_version', 2);

        $this->assertSame(2, $this->globalSetting()->version);
    }

    public function test_real_change_sets_updated_by_to_the_actor(): void
    {
        $actor = $this->actingAsRole('admin', [
            ...$this->settingsViewPermissions(),
            'admin.works.settings.manage',
        ]);

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ]))->assertOk();

        $this->assertSame($actor->id, $this->globalSetting()->updated_by);
    }

    public function test_success_response_returns_complete_normalized_stored_settings(): void
    {
        $this->actingAsRole('super-admin');

        $response = $this->patchJson('/api/admin/works/settings', $this->payload([
            'media_limits' => ['allowed_types' => ['image', 'video']],
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.changed_keys', ['media_limits.allowed_types'])
            ->assertJsonPath('data.stored_settings.scope', WorkSetting::SCOPE_GLOBAL)
            ->assertJsonPath('data.stored_settings.version', 2)
            ->assertJsonPath('data.stored_settings.storage_record_found', true)
            ->assertJsonStructure(['data' => ['stored_settings' => ['updated_at']]])
            ->assertJsonPath('errors', null);

        $this->assertNotContains('updated_by', $this->recursiveKeys($response->json()));
    }

    public function test_sensitive_and_unknown_keys_are_neither_stored_nor_exposed(): void
    {
        $this->actingAsRole('super-admin');
        $before = $this->globalSetting()->values;

        $response = $this->patchJson('/api/admin/works/settings', [
            'version' => 1,
            'values' => [
                'review_sla_hours' => 48,
                'password' => 'secret',
                'token' => 'secret-token',
                'metadata' => ['hidden' => true],
                'payload' => ['hidden' => true],
            ],
        ])->assertUnprocessable();

        $this->assertSame($before, $this->globalSetting()->values);
        $this->assertStringNotContainsString('secret-token', $response->getContent());
    }

    public function test_current_version_succeeds(): void
    {
        $this->actingAsRole('super-admin');
        $this->globalSetting()->forceFill(['version' => 4])->save();

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ], version: 4))
            ->assertOk()
            ->assertJsonPath('data.current_version', 5);
    }

    public function test_stale_version_returns_safe_409_contract(): void
    {
        $this->actingAsRole('super-admin');
        $this->globalSetting()->forceFill([
            'version' => 4,
            'values' => ['review_sla_hours' => 48],
        ])->save();

        $response = $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ], version: 3))
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', ['current_version' => 4])
            ->assertJsonPath('message', 'تغيرت إعدادات الأعمال منذ آخر قراءة. أعد تحميل القيم ثم حاول مجددًا.')
            ->assertJsonPath('errors.version', ['إصدار إعدادات الأعمال لم يعد حديثًا.']);

        $this->assertSame(
            ['current_version'],
            array_keys($response->json('data')),
        );
    }

    public function test_conflict_changes_no_settings_columns(): void
    {
        $this->actingAsRole('super-admin');
        $setting = $this->globalSetting();
        $setting->forceFill([
            'version' => 4,
            'values' => ['review_sla_hours' => 24],
        ])->save();
        $before = $this->settingSnapshot($setting->fresh());

        $this->travel(1)->minute();
        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ], version: 3))->assertStatus(409);

        $this->assertSame($before, $this->settingSnapshot($setting->fresh()));
    }

    public function test_missing_global_record_is_created_on_first_real_write_with_version_one(): void
    {
        $this->actingAsRole('super-admin');
        WorkSetting::query()->where('scope', WorkSetting::SCOPE_GLOBAL)->delete();

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ], version: 1))
            ->assertOk()
            ->assertJsonPath('data.previous_version', 1)
            ->assertJsonPath('data.current_version', 2)
            ->assertJsonPath('data.stored_settings.storage_record_found', true);

        $this->assertDatabaseCount('work_settings', 1);
    }

    public function test_missing_global_record_with_non_initial_version_conflicts_without_creation(): void
    {
        $this->actingAsRole('super-admin');
        WorkSetting::query()->where('scope', WorkSetting::SCOPE_GLOBAL)->delete();

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ], version: 2))
            ->assertStatus(409)
            ->assertJsonPath('data.current_version', 1);

        $this->assertDatabaseCount('work_settings', 0);
    }

    public function test_no_op_returns_changed_false(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => null,
        ]))
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.changed_keys', [])
            ->assertJsonPath('data.previous_version', 1)
            ->assertJsonPath('data.current_version', 1)
            ->assertJsonPath(
                'message',
                'لم تتغير إعدادات الأعمال لأن القيم المرسلة مطابقة للقيم الحالية.',
            );
    }

    public function test_no_op_preserves_version_timestamp_and_updated_by(): void
    {
        $updater = User::factory()->create();
        $this->actingAsRole('super-admin');
        $setting = $this->globalSetting();
        $setting->forceFill([
            'values' => ['review_sla_hours' => 48],
            'version' => 3,
            'updated_by' => $updater->id,
        ])->save();
        $before = $this->settingSnapshot($setting->fresh());

        $this->travel(1)->minute();
        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ], version: 3))->assertOk()->assertJsonPath('data.changed', false);

        $this->assertSame($before, $this->settingSnapshot($setting->fresh()));
    }

    public function test_no_op_records_no_audit_event(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => null,
        ]))->assertOk();

        $this->assertSame(0, $this->settingsAuditEvents()->count());
    }

    public function test_real_change_records_one_settings_audit_event(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ]))->assertOk();

        $this->assertSame(1, $this->settingsAuditEvents()->count());
        $this->assertSame('works.settings.updated', $this->settingsAuditEvents()->sole()->event_type);
    }

    public function test_audit_actor_and_target_are_correct(): void
    {
        $actor = $this->actingAsRole('admin', [
            ...$this->settingsViewPermissions(),
            'admin.works.settings.manage',
        ]);

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ]))->assertOk();

        $event = $this->settingsAuditEvents()->sole();
        $setting = $this->globalSetting();

        $this->assertSame('works', $event->category);
        $this->assertSame('info', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame('admin', $event->actor_role);
        $this->assertSame('work_setting', $event->target_type);
        $this->assertSame($setting->id, $event->target_id);
        $this->assertSame('update', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertNull($event->ip_address);
        $this->assertNull($event->user_agent);
    }

    public function test_audit_metadata_contains_only_scope_versions_and_changed_keys(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
            'media_limits' => ['max_items' => 12],
        ]))->assertOk();

        $metadata = $this->settingsAuditEvents()->sole()->metadata;

        $this->assertSame([
            'changed_keys',
            'current_version',
            'previous_version',
            'scope',
        ], collect(array_keys($metadata))->sort()->values()->all());
        $this->assertSame(WorkSetting::SCOPE_GLOBAL, $metadata['scope']);
        $this->assertSame(1, $metadata['previous_version']);
        $this->assertSame(2, $metadata['current_version']);
        $this->assertSame([
            'review_sla_hours',
            'media_limits.max_items',
        ], $metadata['changed_keys']);
    }

    public function test_audit_event_contains_no_setting_values_or_request_payload(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 417,
            'direct_publish_trust_enabled' => true,
            'media_limits' => [
                'max_items' => 83,
                'max_file_size_kb' => 73123,
                'allowed_types' => ['gallery'],
            ],
        ]))->assertOk();

        $event = $this->settingsAuditEvents()->sole()->toArray();
        $metadataKeys = $this->recursiveKeys($event['metadata']);

        foreach ([
            'values',
            'old_values',
            'new_values',
            'payload',
            'review_sla_hours',
            'direct_publish_trust_enabled',
            'media_limits',
            'updated_by',
        ] as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $metadataKeys);
        }
    }

    public function test_validation_authorization_and_conflict_failures_record_no_audit(): void
    {
        $this->actingAsRole('super-admin');
        $this->patchJson('/api/admin/works/settings', [
            'version' => 1,
            'values' => [],
        ])->assertUnprocessable();

        $this->actingAsRole('admin', $this->settingsViewPermissions());
        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ]))->assertForbidden();

        $this->actingAsRole('super-admin');
        $this->globalSetting()->forceFill(['version' => 4])->save();
        $this->patchJson('/api/admin/works/settings', $this->payload([
            'review_sla_hours' => 48,
        ], version: 3))->assertStatus(409);

        $this->assertSame(0, $this->settingsAuditEvents()->count());
    }

    public function test_audit_failure_rolls_back_both_setting_and_audit_rows(): void
    {
        $this->actingAsRole('super-admin');
        $setting = $this->globalSetting();
        $before = $this->settingSnapshot($setting);
        $this->app->instance(AuditEventLogger::class, new class extends AuditEventLogger
        {
            public function record(array $event): AuditEvent
            {
                parent::record($event);

                throw new LogicException('Forced audit failure.');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->patchJson('/api/admin/works/settings', $this->payload([
                'review_sla_hours' => 48,
            ]));
            $this->fail('The forced audit failure was not thrown.');
        } catch (LogicException $exception) {
            $this->assertSame('Forced audit failure.', $exception->getMessage());
        }

        $this->assertSame($before, $this->settingSnapshot($setting->fresh()));
        $this->assertSame(0, $this->settingsAuditEvents()->count());
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array{version: int, values: array<array-key, mixed>}
     */
    private function payload(array $values, int $version = 1): array
    {
        return [
            'version' => $version,
            'values' => $values,
        ];
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function setGlobalValues(array $values, int $version = 1, ?int $updatedBy = null): void
    {
        $this->globalSetting()->forceFill([
            'values' => $values,
            'version' => $version,
            'updated_by' => $updatedBy,
        ])->save();
    }

    private function resetGlobal(): void
    {
        $this->setGlobalValues([
            'review_sla_hours' => null,
            'direct_publish_trust_enabled' => false,
            'media_limits' => [
                'max_items' => null,
                'max_file_size_kb' => null,
                'allowed_types' => null,
            ],
        ]);
    }

    private function globalSetting(): WorkSetting
    {
        return WorkSetting::query()
            ->where('scope', WorkSetting::SCOPE_GLOBAL)
            ->sole();
    }

    /**
     * @return array{
     *     values: array<array-key, mixed>,
     *     version: int,
     *     updated_by: int|null,
     *     updated_at: string|null
     * }
     */
    private function settingSnapshot(WorkSetting $setting): array
    {
        return [
            'values' => is_array($setting->values) ? $setting->values : [],
            'version' => (int) $setting->version,
            'updated_by' => $setting->updated_by === null ? null : (int) $setting->updated_by,
            'updated_at' => $setting->updated_at?->toJSON(),
        ];
    }

    private function settingsAuditEvents(): Builder
    {
        return AuditEvent::query()->where('event_type', 'works.settings.updated');
    }

    /**
     * @return list<string>
     */
    private function settingsViewPermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.settings.view',
        ];
    }

    /**
     * @return list<string>
     */
    private function allSettingsPermissions(): array
    {
        return [
            ...$this->settingsViewPermissions(),
            'admin.works.settings.manage',
            'admin.works.settings.review_sla.manage',
            'admin.works.settings.direct_publish_trust.manage',
            'admin.works.settings.media_limits.manage',
        ];
    }

    /**
     * @param list<string> $permissions
     */
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

    private function settingsRoutes(): Collection
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->uri() === 'api/admin/works/settings'
                || str_starts_with($route->uri(), 'api/admin/works/settings/'));
    }

    /**
     * @return list<string>
     */
    private function recursiveKeys(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $keys = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $keys[] = strtolower($key);
            }

            $keys = [...$keys, ...$this->recursiveKeys($item)];
        }

        return $keys;
    }
}
