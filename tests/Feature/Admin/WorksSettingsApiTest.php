<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksSettingsController;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkSetting;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private const LIFECYCLE_EVENTS = [
        'created',
        'updated',
        'submitted',
        'reviewed',
        'approved',
        'published',
        'rejected',
        'hidden',
        'archived',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/admin/works/settings')
            ->assertUnauthorized();
    }

    public function test_super_admin_receives_the_safe_settings_contract(): void
    {
        $this->actingAsRole('super-admin');

        $response = $this->getJson('/api/admin/works/settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم جلب إعدادات وصلاحيات الأعمال بنجاح')
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.settings_support', [
                'persistent_settings_available' => true,
                'source' => 'work_settings',
                'reason' => 'توجد طبقة تخزين دائمة لإعدادات الأعمال، لكن واجهات الحفظ والتعديل لم تُبنَ بعد.',
            ])
            ->assertJsonPath('data.stored_settings.scope', WorkSetting::SCOPE_GLOBAL)
            ->assertJsonPath('data.stored_settings.version', 1)
            ->assertJsonPath('data.stored_settings.storage_record_found', true)
            ->assertJsonPath('data.stored_settings.values', [
                'review_sla_hours' => null,
                'direct_publish_trust_enabled' => false,
                'media_limits' => [
                    'max_items' => null,
                    'max_file_size_kb' => null,
                    'allowed_types' => null,
                ],
            ])
            ->assertJsonPath('data.access_model', [
                'internal_roles' => ['super-admin', 'admin', 'staff'],
                'forbidden_roles' => ['client', 'designer'],
                'super_admin_has_all_permissions' => true,
                'client_designer_forbidden_even_if_granted' => true,
            ])
            ->assertJsonPath('data.management_support', [
                'settings_mutation_available' => false,
                'workflow_mutation_available' => false,
                'review_sla_mutation_available' => false,
                'direct_publish_trust_mutation_available' => false,
                'media_limits_mutation_available' => false,
                'reason' => 'واجهات الحفظ والتعديل غير مبنية في هذه المرحلة.',
            ]);

        $this->assertSame(
            [
                'access_model',
                'current_user_capabilities',
                'management_support',
                'permission_registry',
                'settings_support',
                'stored_settings',
                'workflow',
            ],
            collect(array_keys($response->json('data')))->sort()->values()->all(),
        );
        $this->assertSame(
            [
                'can_manage_direct_publish_trust' => true,
                'can_manage_media_limits' => true,
                'can_manage_review_sla' => true,
                'can_manage_settings' => true,
                'can_manage_workflow' => true,
                'can_view_settings' => true,
            ],
            collect($response->json('data.current_user_capabilities'))->sortKeys()->all(),
        );
        $response->assertJsonStructure([
            'data' => [
                'stored_settings' => ['updated_at'],
            ],
        ]);
    }

    public function test_admin_staff_and_access_only_accounts_get_403(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role);
            $this->getJson('/api/admin/works/settings')->assertForbidden();

            $this->actingAsRole($role, ['admin.works.access']);
            $this->getJson('/api/admin/works/settings')->assertForbidden();
        }
    }

    public function test_admin_and_staff_with_required_permissions_can_read_settings(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, $this->settingsViewPermissions());

            $this->getJson('/api/admin/works/settings')
                ->assertOk()
                ->assertJsonPath('data.current_user_capabilities.can_view_settings', true);
        }
    }

    public function test_client_and_designer_with_accidental_permissions_get_403(): void
    {
        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, [
                ...$this->settingsViewPermissions(),
                'admin.works.settings.manage',
            ]);

            $this->getJson('/api/admin/works/settings')->assertForbidden();
        }
    }

    public function test_non_internal_role_gets_403(): void
    {
        Role::create(['name' => 'contractor', 'guard_name' => 'web']);
        $this->actingAsRole('contractor', $this->settingsViewPermissions());

        $this->getJson('/api/admin/works/settings')->assertForbidden();
    }

    public function test_any_query_parameter_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/settings?unexpected=value')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unexpected');
    }

    public function test_sensitive_query_parameters_return_422(): void
    {
        $this->actingAsRole('super-admin');
        $parameters = [
            'email',
            'password',
            'token',
            'cookie',
            'internal_notes',
            'rejection_reason',
            'change_request_notes',
            'payload',
            'metadata',
            'description',
            'summary',
            'user',
            'users',
            'role',
            'roles',
            'permission',
            'permissions',
            'settings',
        ];

        foreach ($parameters as $parameter) {
            $this->getJson('/api/admin/works/settings?'.$parameter.'=blocked')
                ->assertUnprocessable()
                ->assertJsonValidationErrors($parameter);
        }
    }

    public function test_workflow_matches_work_constants_and_activity_contract(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/settings')
            ->assertOk()
            ->assertJsonPath('data.workflow.statuses', [
                Work::STATUS_DRAFT,
                Work::STATUS_SUBMITTED,
                Work::STATUS_IN_REVIEW,
                Work::STATUS_CHANGES_REQUESTED,
                Work::STATUS_APPROVED,
                Work::STATUS_PUBLISHED,
                Work::STATUS_REJECTED,
                Work::STATUS_HIDDEN,
                Work::STATUS_ARCHIVED,
            ])
            ->assertJsonPath('data.workflow.visibility_statuses', [
                Work::VISIBILITY_HIDDEN,
                Work::VISIBILITY_PUBLIC,
            ])
            ->assertJsonPath('data.workflow.lifecycle_events', self::LIFECYCLE_EVENTS)
            ->assertJsonPath('data.workflow.review_queue_statuses', [
                Work::STATUS_SUBMITTED,
                Work::STATUS_IN_REVIEW,
                Work::STATUS_CHANGES_REQUESTED,
            ])
            ->assertJsonPath(
                'data.workflow.derived_from',
                'App\\Models\\Work constants and current works API contracts',
            );
    }

    public function test_permission_registry_is_derived_from_the_registered_works_group(): void
    {
        $this->actingAsRole('super-admin');
        $response = $this->getJson('/api/admin/works/settings')
            ->assertOk()
            ->assertJsonPath('data.permission_registry.group', 'admin.works');

        $registeredPermissions = collect(config('yemen-motion-permissions.permissions'))
            ->where('group', 'admin.works')
            ->pluck('name')
            ->sort()
            ->values();
        $sections = collect($response->json('data.permission_registry.sections'));
        $returnedPermissions = $sections
            ->flatMap(fn (array $section): array => $section['permissions'])
            ->pluck('name')
            ->sort()
            ->values();

        $this->assertSame($registeredPermissions->count(), $response->json('data.permission_registry.total_permissions'));
        $this->assertSame($registeredPermissions->all(), $returnedPermissions->all());
        $this->assertSame($returnedPermissions->count(), $returnedPermissions->unique()->count());
        $this->assertContains('admin.works.settings.view', $returnedPermissions);
        $this->assertContains('admin.works.settings.manage', $returnedPermissions);
        $this->assertContains('admin.works.settings.workflow.manage', $returnedPermissions);
        $this->assertNotContains('admin.works.delete', $returnedPermissions);
        $this->assertNotContains('admin.works.force_delete', $returnedPermissions);

        $navigationPermissions = $sections
            ->firstWhere('key', 'navigation')['permissions'];
        $navigationNames = collect($navigationPermissions)->pluck('name');

        foreach ([
            'admin.works.access',
            'admin.works.overview.view',
            'admin.works.all.view',
            'admin.works.review.view',
            'admin.works.visibility.view',
            'admin.works.reports.view',
            'admin.works.taxonomy.view',
            'admin.works.activity.view',
            'admin.works.settings.view',
        ] as $navigationPermission) {
            $this->assertContains($navigationPermission, $navigationNames);
        }

        $this->assertTrue($sections->every(
            fn (array $section): bool => collect($section['permissions'])->every(
                fn (array $permission): bool => array_diff(
                    array_keys($permission),
                    ['name', 'label', 'description'],
                ) === [],
            ),
        ));
    }

    public function test_admin_and_staff_capabilities_reflect_granted_manage_permissions(): void
    {
        $this->actingAsRole('admin', [
            ...$this->settingsViewPermissions(),
            'admin.works.settings.manage',
            'admin.works.settings.workflow.manage',
        ]);

        $this->getJson('/api/admin/works/settings')
            ->assertOk()
            ->assertJsonPath('data.current_user_capabilities', [
                'can_view_settings' => true,
                'can_manage_settings' => true,
                'can_manage_workflow' => true,
                'can_manage_review_sla' => false,
                'can_manage_direct_publish_trust' => false,
                'can_manage_media_limits' => false,
            ]);

        $this->actingAsRole('staff', [
            ...$this->settingsViewPermissions(),
            'admin.works.settings.review_sla.manage',
            'admin.works.settings.direct_publish_trust.manage',
            'admin.works.settings.media_limits.manage',
        ]);

        $this->getJson('/api/admin/works/settings')
            ->assertOk()
            ->assertJsonPath('data.current_user_capabilities', [
                'can_view_settings' => true,
                'can_manage_settings' => false,
                'can_manage_workflow' => false,
                'can_manage_review_sla' => true,
                'can_manage_direct_publish_trust' => true,
                'can_manage_media_limits' => true,
            ]);
    }

    public function test_response_does_not_expose_sensitive_or_raw_data(): void
    {
        $user = $this->actingAsRole('admin', $this->settingsViewPermissions());
        WorkSetting::query()
            ->where('scope', WorkSetting::SCOPE_GLOBAL)
            ->sole()
            ->forceFill([
                'updated_by' => $user->id,
                'values' => [
                    'review_sla_hours' => 24,
                    'password' => 'secret',
                    'token' => 'secret-token',
                    'metadata' => ['hidden' => true],
                    'private_notes' => 'hidden',
                    'unknown_setting' => true,
                    'reporter_email' => $user->email,
                ],
            ])
            ->save();

        $response = $this->getJson('/api/admin/works/settings')->assertOk();
        $keys = $this->recursiveKeys($response->json());
        $encodedResponse = strtolower($response->getContent());

        foreach ([
            'user',
            'users',
            'email',
            'password',
            'token',
            'cookie',
            'metadata',
            'payload',
            'updated_by',
            'raw_config',
            'raw_model',
            'unknown_setting',
            'reporter_email',
            'rows',
            'works',
            'work_rows',
            'private_notes',
            'internal_notes',
            'rejection_reason',
            'change_request_notes',
        ] as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $keys);
        }

        $this->assertStringNotContainsString(strtolower($user->email), $encodedResponse);
        $this->assertStringNotContainsString(strtolower($user->name), $encodedResponse);
    }

    public function test_endpoint_returns_normalized_stored_values_without_unknown_keys(): void
    {
        $this->actingAsRole('super-admin');
        WorkSetting::query()
            ->where('scope', WorkSetting::SCOPE_GLOBAL)
            ->sole()
            ->forceFill([
                'version' => 7,
                'values' => [
                    'review_sla_hours' => 72,
                    'direct_publish_trust_enabled' => 'false',
                    'media_limits' => [
                        'max_items' => 12,
                        'max_file_size_kb' => 4096,
                        'allowed_types' => ['video', 'unknown', 'video', 'image'],
                        'token' => 'nested-secret',
                    ],
                    'password' => 'secret',
                    'metadata' => ['private' => true],
                    'private_notes' => 'hidden',
                    'unknown_setting' => true,
                    'reporter_email' => 'reporter@example.test',
                ],
            ])
            ->save();

        $response = $this->getJson('/api/admin/works/settings')
            ->assertOk()
            ->assertJsonPath('data.stored_settings.scope', WorkSetting::SCOPE_GLOBAL)
            ->assertJsonPath('data.stored_settings.version', 7)
            ->assertJsonPath('data.stored_settings.storage_record_found', true)
            ->assertJsonPath('data.stored_settings.values', [
                'review_sla_hours' => 72,
                'direct_publish_trust_enabled' => false,
                'media_limits' => [
                    'max_items' => 12,
                    'max_file_size_kb' => 4096,
                    'allowed_types' => ['video', 'image'],
                ],
            ]);

        foreach ([
            'password',
            'token',
            'metadata',
            'private_notes',
            'unknown_setting',
            'reporter_email',
            'updated_by',
        ] as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $this->recursiveKeys($response->json()));
        }
    }

    public function test_static_settings_route_resolves_to_settings_controller(): void
    {
        $this->actingAsRole('super-admin');

        $route = Route::getRoutes()->match(Request::create('/api/admin/works/settings', 'GET'));

        $this->assertSame(WorksSettingsController::class.'@index', $route->getActionName());
        $this->getJson('/api/admin/works/settings')
            ->assertOk()
            ->assertJsonPath('message', 'تم جلب إعدادات وصلاحيات الأعمال بنجاح');
        $this->getJson('/api/admin/works/not-a-number')->assertNotFound();
    }

    public function test_no_settings_mutation_routes_exist(): void
    {
        $settingsRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->uri() === 'api/admin/works/settings'
                || str_starts_with($route->uri(), 'api/admin/works/settings/'));

        $this->assertCount(1, $settingsRoutes);
        $this->assertSame(['GET', 'HEAD'], $settingsRoutes->first()->methods());
        $this->assertFalse($settingsRoutes->contains(
            fn ($route): bool => array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods()) !== [],
        ));
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
