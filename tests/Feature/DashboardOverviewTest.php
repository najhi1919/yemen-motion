<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'designer', 'guard_name' => 'web']);

        $overviewPermission = Permission::firstOrCreate(['name' => 'dashboard.overview.view', 'guard_name' => 'web']);
        $adminUsersPermission = Permission::firstOrCreate(['name' => 'admin.users.view', 'guard_name' => 'web']);
        $adminRolesPermission = Permission::firstOrCreate(['name' => 'admin.roles.view', 'guard_name' => 'web']);
        $adminPermissionsPermission = Permission::firstOrCreate(['name' => 'admin.permissions.view', 'guard_name' => 'web']);
        $adminAccessPermission = Permission::firstOrCreate(['name' => 'admin.access.view', 'guard_name' => 'web']);

        Role::where('name', 'super-admin')->firstOrFail()->givePermissionTo($overviewPermission);

        Role::where('name', 'admin')->firstOrFail()->givePermissionTo([
            $overviewPermission,
            $adminUsersPermission,
            $adminRolesPermission,
        ]);

        Role::where('name', 'staff')->firstOrFail()->givePermissionTo($overviewPermission);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(401);
    }

    public function test_authenticated_user_without_overview_permission_returns_403(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(403);
    }

    public function test_user_with_overview_permission_but_without_internal_role_returns_403(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('dashboard.overview.view');
        Sanctum::actingAs($user, ['*']);

        $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(403);
    }

    public function test_authenticated_admin_gets_valid_json_shape(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'role',
                    'period',
                    'sections',
                    'cards',
                    'charts',
                    'activities',
                ],
                'message',
                'errors',
                'meta' => [
                    'periods',
                    'selected_period',
                ],
            ])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('meta.selected_period', 'month')
            ->assertJsonPath('meta.periods', ['day', 'week', 'month', 'year']);

        $sectionKeys = collect($response->json('data.sections'))->pluck('key')->toArray();
        $this->assertContains('users', $sectionKeys);
        $this->assertContains('roles', $sectionKeys);
        $this->assertContains('overview', $sectionKeys);

        foreach (['orders', 'works', 'contests', 'wallet', 'staff', 'permissions', 'access', 'works_review', 'reports', 'activities_feed'] as $key) {
            $this->assertNotContains($key, $sectionKeys);
        }
    }

    public function test_authenticated_super_admin_can_access_dashboard_overview(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'admin');

        $sectionKeys = collect($response->json('data.sections'))->pluck('key')->toArray();
        $cardKeys = collect($response->json('data.cards'))->pluck('key')->toArray();
        $chartKeys = collect($response->json('data.charts'))->pluck('key')->toArray();

        $expectedKeys = [
            'users',
            'orders',
            'works',
            'contests',
            'wallet',
            'staff',
            'roles',
            'permissions',
            'access',
            'works_review',
            'reports',
            'activities_feed',
            'overview',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertContains($key, $sectionKeys);
            $this->assertContains($key, $cardKeys);
            $this->assertContains($key, $chartKeys);
        }
    }

    public function test_overview_response_includes_permission_and_live_metadata(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $sections = collect($response->json('data.sections'))->keyBy('key');
        $cards = collect($response->json('data.cards'))->keyBy('key');
        $charts = collect($response->json('data.charts'))->keyBy('key');

        $this->assertSame('admin.users.view', $sections->get('users')['permission']);
        $this->assertTrue($sections->get('users')['can_view']);
        $this->assertFalse($sections->get('users')['is_admin_only']);
        $this->assertTrue($sections->get('users')['is_live']);
        $this->assertFalse($sections->get('users')['is_placeholder']);

        $this->assertFalse($sections->has('orders'));
        $this->assertFalse($cards->has('orders'));
        $this->assertFalse($charts->has('orders'));

        foreach (['permission', 'can_view', 'is_admin_only', 'is_live', 'is_placeholder'] as $field) {
            $this->assertArrayHasKey($field, $cards->get('users'));
            $this->assertArrayHasKey($field, $charts->get('users'));
        }
    }

    public function test_default_month_period_returns_current_month_access_metrics(): void
    {
        Permission::firstOrCreate(['name' => 'dashboard.overview.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'admin.roles.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'custom.reports.export', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'support-agent', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo([
            'admin.permissions.view',
            'admin.access.view',
        ]);

        User::factory()->count(2)->create()->each(fn(User $user) => $user->assignRole('staff'));
        User::factory()->create()->assignRole('client');
        User::factory()->create()->assignRole('designer');

        Sanctum::actingAs($admin, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $cards = collect($response->json('data.cards'))->keyBy('key');
        $current = now();
        $periodRange = [$current->copy()->startOfMonth(), $current->copy()->endOfMonth()];
        $periodRoles = Role::query()->whereBetween('created_at', $periodRange)->count();
        $periodPermissions = Permission::query()->whereBetween('created_at', $periodRange)->count();

        $this->assertSame(
            User::query()->whereBetween('created_at', $periodRange)->count(),
            $cards->get('users')['value']
        );
        $this->assertSame($periodRoles, $cards->get('roles')['value']);
        $this->assertSame($periodPermissions, $cards->get('permissions')['value']);
        $this->assertSame($periodRoles + $periodPermissions, $cards->get('access')['value']);
        $this->assertFalse($cards->has('staff'));
    }

    public function test_admin_without_precise_permissions_does_not_see_permissions_or_access_surfaces(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $sectionKeys = collect($response->json('data.sections'))->pluck('key')->toArray();
        $cardKeys = collect($response->json('data.cards'))->pluck('key')->toArray();
        $chartKeys = collect($response->json('data.charts'))->pluck('key')->toArray();

        foreach (['permissions', 'access'] as $key) {
            $this->assertNotContains($key, $sectionKeys);
            $this->assertNotContains($key, $cardKeys);
            $this->assertNotContains($key, $chartKeys);
        }
    }

    public function test_admin_with_permissions_view_sees_permissions_surfaces(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo('admin.permissions.view');
        Sanctum::actingAs($admin, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $sections = collect($response->json('data.sections'))->keyBy('key');
        $cards = collect($response->json('data.cards'))->keyBy('key');
        $charts = collect($response->json('data.charts'))->keyBy('key');

        $this->assertSame('admin.permissions.view', $sections->get('permissions')['permission']);
        $this->assertTrue($sections->get('permissions')['can_view']);
        $this->assertSame('admin.permissions.view', $cards->get('permissions')['permission']);
        $this->assertSame('admin.permissions.view', $charts->get('permissions')['permission']);
        $this->assertFalse($sections->has('access'));
        $this->assertFalse($cards->has('access'));
        $this->assertFalse($charts->has('access'));
    }

    public function test_admin_with_access_view_sees_access_surfaces(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo('admin.access.view');
        Sanctum::actingAs($admin, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $sections = collect($response->json('data.sections'))->keyBy('key');
        $cards = collect($response->json('data.cards'))->keyBy('key');
        $charts = collect($response->json('data.charts'))->keyBy('key');

        $this->assertSame('admin.access.view', $sections->get('access')['permission']);
        $this->assertTrue($sections->get('access')['can_view']);
        $this->assertSame('admin.access.view', $cards->get('access')['permission']);
        $this->assertSame('admin.access.view', $charts->get('access')['permission']);
        $this->assertFalse($sections->has('permissions'));
        $this->assertFalse($cards->has('permissions'));
        $this->assertFalse($charts->has('permissions'));
    }

    public function test_authenticated_staff_gets_valid_json_shape(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Sanctum::actingAs($staff, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'role',
                    'period',
                    'sections',
                    'cards',
                    'charts',
                    'activities',
                ],
                'message',
                'errors',
                'meta' => [
                    'periods',
                    'selected_period',
                ],
            ])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonPath('meta.selected_period', 'month');
    }

    public function test_staff_with_only_dashboard_overview_permission_sees_no_admin_or_placeholder_surfaces(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Sanctum::actingAs($staff, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $this->assertSame(
            ['overview'],
            collect($response->json('data.sections'))->pluck('key')->all()
        );
        $this->assertSame(
            ['overview'],
            collect($response->json('data.cards'))->pluck('key')->all()
        );
        $this->assertSame(
            ['overview'],
            collect($response->json('data.charts'))->pluck('key')->all()
        );
    }

    public function test_staff_with_users_view_sees_users_section_card_and_chart(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->givePermissionTo('admin.users.view');
        Sanctum::actingAs($staff, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $sections = collect($response->json('data.sections'))->keyBy('key');
        $cards = collect($response->json('data.cards'))->keyBy('key');
        $charts = collect($response->json('data.charts'))->keyBy('key');

        $this->assertSame('admin.users.view', $sections->get('users')['permission']);
        $this->assertTrue($sections->get('users')['can_view']);
        $this->assertFalse($sections->get('users')['is_admin_only']);
        $this->assertTrue($cards->has('users'));
        $this->assertTrue($charts->has('users'));
        $this->assertFalse($sections->has('roles'));
    }

    public function test_staff_with_roles_view_sees_roles_section_card_and_chart(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->givePermissionTo('admin.roles.view');
        Sanctum::actingAs($staff, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $sections = collect($response->json('data.sections'))->keyBy('key');
        $cards = collect($response->json('data.cards'))->keyBy('key');
        $charts = collect($response->json('data.charts'))->keyBy('key');

        $this->assertSame('admin.roles.view', $sections->get('roles')['permission']);
        $this->assertTrue($sections->get('roles')['can_view']);
        $this->assertFalse($sections->get('roles')['is_admin_only']);
        $this->assertTrue($cards->has('roles'));
        $this->assertTrue($charts->has('roles'));
        $this->assertFalse($sections->has('users'));
    }

    public function test_staff_without_permissions_view_does_not_see_permissions_surfaces(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->givePermissionTo('admin.users.view');
        Sanctum::actingAs($staff, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $this->assertNotContains(
            'permissions',
            collect($response->json('data.sections'))->pluck('key')->all()
        );
        $this->assertNotContains(
            'permissions',
            collect($response->json('data.cards'))->pluck('key')->all()
        );
        $this->assertNotContains(
            'permissions',
            collect($response->json('data.charts'))->pluck('key')->all()
        );
    }

    public function test_staff_with_permissions_view_sees_permissions_section_card_and_chart(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->givePermissionTo('admin.permissions.view');
        Sanctum::actingAs($staff, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $sections = collect($response->json('data.sections'))->keyBy('key');
        $cards = collect($response->json('data.cards'))->keyBy('key');
        $charts = collect($response->json('data.charts'))->keyBy('key');

        $this->assertSame('admin.permissions.view', $sections->get('permissions')['permission']);
        $this->assertTrue($sections->get('permissions')['can_view']);
        $this->assertFalse($sections->get('permissions')['is_admin_only']);
        $this->assertTrue($cards->has('permissions'));
        $this->assertTrue($charts->has('permissions'));
        $this->assertFalse($sections->has('access'));
    }

    public function test_staff_with_access_view_sees_access_section_card_and_chart(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->givePermissionTo('admin.access.view');
        Sanctum::actingAs($staff, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $sections = collect($response->json('data.sections'))->keyBy('key');
        $cards = collect($response->json('data.cards'))->keyBy('key');
        $charts = collect($response->json('data.charts'))->keyBy('key');

        $this->assertSame('admin.access.view', $sections->get('access')['permission']);
        $this->assertTrue($sections->get('access')['can_view']);
        $this->assertFalse($sections->get('access')['is_admin_only']);
        $this->assertTrue($cards->has('access'));
        $this->assertTrue($charts->has('access'));
        $this->assertFalse($sections->has('permissions'));
    }

    public function test_client_with_dashboard_and_admin_permissions_still_returns_403(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');
        $client->givePermissionTo([
            'dashboard.overview.view',
            'admin.users.view',
        ]);
        Sanctum::actingAs($client, ['*']);

        $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(403);
    }

    public function test_designer_with_dashboard_and_admin_permissions_still_returns_403(): void
    {
        $designer = User::factory()->create();
        $designer->assignRole('designer');
        $designer->givePermissionTo([
            'dashboard.overview.view',
            'admin.users.view',
        ]);
        Sanctum::actingAs($designer, ['*']);

        $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(403);
    }

    public function test_overview_does_not_return_fake_demo_activity(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $activityLabels = collect($response->json('data.activities'))
            ->map(fn(array $activity) => $activity['label']['ar'] ?? null)
            ->filter()
            ->values()
            ->all();
        $activityKeys = collect($response->json('data.activities'))->pluck('key')->all();

        $this->assertNotContains('نشاط تجريبي', $activityLabels);
        $this->assertNotContains('access-data-synced', $activityKeys);
    }

    public function test_staff_with_only_dashboard_overview_permission_has_empty_activity_feed(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Sanctum::actingAs($staff, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'staff');

        $this->assertSame([], $response->json('data.activities'));
    }

    public function test_staff_with_users_view_sees_only_user_created_activity(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->givePermissionTo('admin.users.view');
        User::factory()->create(['name' => 'Activity Client']);

        Sanctum::actingAs($staff, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $activityKeys = collect($response->json('data.activities'))->pluck('key');

        $this->assertNotEmpty($activityKeys);
        $this->assertTrue($activityKeys->every(
            fn(string $key) => str_starts_with($key, 'user-created-')
        ));
        $this->assertFalse($activityKeys->contains(
            fn(string $key) => str_starts_with($key, 'role-created-')
        ));
        $this->assertFalse($activityKeys->contains(
            fn(string $key) => str_starts_with($key, 'permission-created-')
        ));
    }

    public function test_staff_with_roles_view_sees_only_role_created_activity(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->givePermissionTo('admin.roles.view');
        Role::create(['name' => 'activity-reviewer', 'guard_name' => 'web']);

        Sanctum::actingAs($staff, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $activityKeys = collect($response->json('data.activities'))->pluck('key');

        $this->assertNotEmpty($activityKeys);
        $this->assertTrue($activityKeys->every(
            fn(string $key) => str_starts_with($key, 'role-created-')
        ));
    }

    public function test_staff_with_permissions_view_sees_only_permission_created_activity(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $staff->givePermissionTo('admin.permissions.view');
        Permission::create(['name' => 'activity.reports.view', 'guard_name' => 'web']);

        Sanctum::actingAs($staff, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $activityKeys = collect($response->json('data.activities'))->pluck('key');

        $this->assertNotEmpty($activityKeys);
        $this->assertTrue($activityKeys->every(
            fn(string $key) => str_starts_with($key, 'permission-created-')
        ));
    }

    public function test_admin_without_permissions_view_does_not_see_permission_created_activity(): void
    {
        $admin = User::factory()->create(['name' => 'Activity Admin']);
        $admin->assignRole('admin');
        User::factory()->create(['name' => 'Activity Client']);
        Role::create(['name' => 'activity-reviewer', 'guard_name' => 'web']);
        Permission::create(['name' => 'activity.reports.view', 'guard_name' => 'web']);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $activityKeys = collect($response->json('data.activities'))->pluck('key');

        $this->assertTrue($activityKeys->contains(
            fn(string $key) => str_starts_with($key, 'user-created-')
        ));
        $this->assertTrue($activityKeys->contains(
            fn(string $key) => str_starts_with($key, 'role-created-')
        ));
        $this->assertFalse($activityKeys->contains(
            fn(string $key) => str_starts_with($key, 'permission-created-')
        ));
    }

    public function test_super_admin_sees_all_permission_scoped_activity_types(): void
    {
        $superAdmin = User::factory()->create(['name' => 'Activity Super Admin']);
        $superAdmin->assignRole('super-admin');
        User::factory()->create(['name' => 'Activity Client']);
        Role::create(['name' => 'activity-reviewer', 'guard_name' => 'web']);
        Permission::create(['name' => 'activity.reports.view', 'guard_name' => 'web']);

        Sanctum::actingAs($superAdmin, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $activities = $response->json('data.activities');
        $activityKeys = collect($activities)->pluck('key');

        $this->assertTrue($activityKeys->contains(
            fn(string $key) => str_starts_with($key, 'user-created-')
        ));
        $this->assertTrue($activityKeys->contains(
            fn(string $key) => str_starts_with($key, 'role-created-')
        ));
        $this->assertTrue($activityKeys->contains(
            fn(string $key) => str_starts_with($key, 'permission-created-')
        ));
        $this->assertLessThanOrEqual(8, count($activities));

        foreach ($activities as $activity) {
            $this->assertSame(['key', 'label', 'time', 'icon'], array_keys($activity));
            $this->assertSame(['ar', 'en'], array_keys($activity['label']));
        }
    }

    public function test_period_is_reflected_in_response(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['*']);

        foreach (['day', 'week', 'month', 'year'] as $period) {
            $this->json('GET', "/api/dashboard/overview?period=$period")
                ->assertStatus(200)
                ->assertJsonPath('data.period', $period)
                ->assertJsonPath('meta.selected_period', $period);
        }
    }

    public function test_day_period_counts_only_users_created_today(): void
    {
        $current = now();
        $admin = User::factory()->create(['created_at' => $current->copy()->subYears(2)]);
        $admin->assignRole('admin');
        User::factory()->create(['created_at' => $current]);
        User::factory()->create(['created_at' => $current->copy()->subDay()]);

        Sanctum::actingAs($admin, ['*']);

        $cards = collect(
            $this->json('GET', '/api/dashboard/overview?period=day')
                ->assertStatus(200)
                ->json('data.cards')
        )->keyBy('key');

        $this->assertSame(1, $cards->get('users')['value']);
        $this->assertSame(1, $cards->get('overview')['value']);
    }

    public function test_week_period_counts_only_users_created_in_current_week(): void
    {
        $current = now();
        $weekStart = $current->copy()->startOfWeek();
        $admin = User::factory()->create(['created_at' => $current->copy()->subYears(2)]);
        $admin->assignRole('admin');
        User::factory()->create(['created_at' => $weekStart]);
        User::factory()->create(['created_at' => $weekStart->copy()->subSecond()]);

        Sanctum::actingAs($admin, ['*']);

        $cards = collect(
            $this->json('GET', '/api/dashboard/overview?period=week')
                ->assertStatus(200)
                ->json('data.cards')
        )->keyBy('key');

        $this->assertSame(1, $cards->get('users')['value']);
    }

    public function test_month_period_counts_only_users_created_in_current_month(): void
    {
        $current = now();
        $monthStart = $current->copy()->startOfMonth();
        $admin = User::factory()->create(['created_at' => $current->copy()->subYears(2)]);
        $admin->assignRole('admin');
        User::factory()->create(['created_at' => $monthStart]);
        User::factory()->create(['created_at' => $monthStart->copy()->subSecond()]);

        Sanctum::actingAs($admin, ['*']);

        $cards = collect(
            $this->json('GET', '/api/dashboard/overview?period=month')
                ->assertStatus(200)
                ->json('data.cards')
        )->keyBy('key');

        $this->assertSame(1, $cards->get('users')['value']);
    }

    public function test_year_period_excludes_users_created_before_current_year(): void
    {
        $current = now();
        $yearStart = $current->copy()->startOfYear();
        $admin = User::factory()->create(['created_at' => $current->copy()->subYears(2)]);
        $admin->assignRole('admin');
        User::factory()->create(['created_at' => $yearStart]);
        User::factory()->create(['created_at' => $yearStart->copy()->subSecond()]);

        Sanctum::actingAs($admin, ['*']);

        $cards = collect(
            $this->json('GET', '/api/dashboard/overview?period=year')
                ->assertStatus(200)
                ->json('data.cards')
        )->keyBy('key');

        $this->assertSame(1, $cards->get('users')['value']);
    }

    public function test_day_period_scopes_roles_permissions_and_access_card_values(): void
    {
        $current = now();
        $outsidePeriod = $current->copy()->subDay();

        Role::query()->update(['created_at' => $outsidePeriod]);
        Permission::query()->update(['created_at' => $outsidePeriod]);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        Role::create([
            'name' => 'period-role-current',
            'guard_name' => 'web',
            'created_at' => $current,
        ]);
        Role::create([
            'name' => 'period-role-old',
            'guard_name' => 'web',
            'created_at' => $outsidePeriod,
        ]);
        Permission::create([
            'name' => 'period.permission.current',
            'guard_name' => 'web',
            'created_at' => $current,
        ]);
        Permission::create([
            'name' => 'period.permission.old',
            'guard_name' => 'web',
            'created_at' => $outsidePeriod,
        ]);

        Sanctum::actingAs($superAdmin, ['*']);

        $cards = collect(
            $this->json('GET', '/api/dashboard/overview?period=day')
                ->assertStatus(200)
                ->json('data.cards')
        )->keyBy('key');

        $this->assertSame(1, $cards->get('roles')['value']);
        $this->assertSame(1, $cards->get('permissions')['value']);
        $this->assertSame(2, $cards->get('access')['value']);
    }

    public function test_invalid_period_returns_422(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['*']);

        $this->json('GET', '/api/dashboard/overview?period=invalid')
            ->assertStatus(422)
            ->assertJson(['message' => 'Invalid period provided']);
    }
}
