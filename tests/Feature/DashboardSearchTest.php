<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super-admin', 'admin', 'staff', 'client', 'designer'] as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }

        foreach ([
            'dashboard.overview.view',
            'admin.users.view',
            'admin.roles.view',
            'admin.permissions.view',
        ] as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        Role::where('name', 'admin')->firstOrFail()->givePermissionTo([
            'dashboard.overview.view',
            'admin.users.view',
            'admin.roles.view',
        ]);

        Role::where('name', 'staff')->firstOrFail()->givePermissionTo('dashboard.overview.view');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_unauthenticated_user_cannot_search(): void
    {
        $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(401);
    }

    public function test_user_without_dashboard_overview_permission_cannot_search(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(403);
    }

    public function test_client_with_dashboard_overview_permission_cannot_search(): void
    {
        $client = $this->userWithRole('client');
        $client->givePermissionTo('dashboard.overview.view');
        Sanctum::actingAs($client, ['*']);

        $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(403);
    }

    public function test_designer_with_dashboard_and_admin_users_permissions_still_cannot_search(): void
    {
        $designer = $this->userWithRole('designer');
        $designer->givePermissionTo([
            'dashboard.overview.view',
            'admin.users.view',
        ]);
        Sanctum::actingAs($designer, ['*']);

        $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(403);
    }

    public function test_client_with_dashboard_and_admin_users_permissions_still_cannot_search(): void
    {
        $client = $this->userWithRole('client');
        $client->givePermissionTo([
            'dashboard.overview.view',
            'admin.users.view',
        ]);
        Sanctum::actingAs($client, ['*']);

        $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(403);
    }

    public function test_staff_with_dashboard_overview_permission_gets_no_admin_results(): void
    {
        $staff = $this->userWithRole('staff');
        $this->createSearchFixtures();

        Sanctum::actingAs($staff, ['*']);

        $response = $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame([], $response->json('data.results'));
        $this->assertSame([], $response->json('data.grouped.users'));
        $this->assertSame([], $response->json('data.grouped.staff'));
        $this->assertSame([], $response->json('data.grouped.roles'));
        $this->assertSame([], $response->json('data.grouped.permissions'));
    }

    public function test_staff_with_users_view_can_find_matching_users(): void
    {
        $staff = $this->userWithRole('staff');
        $staff->givePermissionTo('admin.users.view');
        User::factory()->create([
            'name' => 'ali customer',
            'email' => 'ali.customer@example.com',
        ]);

        Sanctum::actingAs($staff, ['*']);

        $response = $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(200);

        $users = collect($response->json('data.grouped.users'));

        $this->assertTrue($users->contains(fn (array $result) => $result['title'] === 'ali customer'));
        $this->assertSame('/admin/users?search=ali', $users->first()['route']);
    }

    public function test_staff_with_roles_view_can_find_matching_roles(): void
    {
        $staff = $this->userWithRole('staff');
        $staff->givePermissionTo('admin.roles.view');
        Role::create([
            'name' => 'ali-reviewer',
            'guard_name' => 'web',
        ]);

        Sanctum::actingAs($staff, ['*']);

        $response = $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(200);

        $roles = collect($response->json('data.grouped.roles'));

        $this->assertTrue($roles->contains(fn (array $result) => $result['title'] === 'ali-reviewer'));
        $this->assertSame('/admin/roles', $roles->first()['route']);
    }

    public function test_staff_without_permissions_view_cannot_find_matching_permissions(): void
    {
        $staff = $this->userWithRole('staff');
        Permission::create([
            'name' => 'ali.permission.view',
            'guard_name' => 'web',
        ]);

        Sanctum::actingAs($staff, ['*']);

        $response = $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(200);

        $this->assertSame([], $response->json('data.grouped.permissions'));
        $this->assertFalse(collect($response->json('data.results'))->contains(
            fn (array $result) => $result['type'] === 'permission'
        ));
    }

    public function test_admin_with_users_view_can_find_matching_users(): void
    {
        $admin = $this->userWithRole('admin');
        User::factory()->create([
            'name' => 'ali customer',
            'email' => 'ali.customer@example.com',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(200);

        $users = collect($response->json('data.grouped.users'));

        $this->assertTrue($users->contains(fn (array $result) => $result['title'] === 'ali customer'));
        $this->assertSame('/admin/users?search=ali', $users->first()['route']);
    }

    public function test_admin_without_permissions_view_cannot_find_matching_permissions(): void
    {
        $admin = $this->userWithRole('admin');
        Permission::create([
            'name' => 'ali.permission.view',
            'guard_name' => 'web',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(200);

        $this->assertSame([], $response->json('data.grouped.permissions'));
        $this->assertFalse(collect($response->json('data.results'))->contains(
            fn (array $result) => $result['type'] === 'permission'
        ));
    }

    public function test_admin_with_permissions_view_can_find_matching_permissions(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->givePermissionTo('admin.permissions.view');
        Permission::create([
            'name' => 'ali.permission.view',
            'guard_name' => 'web',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(200);

        $permissions = collect($response->json('data.grouped.permissions'));

        $this->assertTrue($permissions->contains(fn (array $result) => $result['title'] === 'ali.permission.view'));
        $this->assertSame('/admin/permissions', $permissions->first()['route']);
    }

    public function test_admin_with_roles_view_can_find_matching_roles(): void
    {
        $admin = $this->userWithRole('admin');
        Role::create([
            'name' => 'ali-reviewer',
            'guard_name' => 'web',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(200);

        $roles = collect($response->json('data.grouped.roles'));

        $this->assertTrue($roles->contains(fn (array $result) => $result['title'] === 'ali-reviewer'));
        $this->assertSame('/admin/roles', $roles->first()['route']);
    }

    public function test_super_admin_can_find_users_staff_roles_and_permissions(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        $this->createSearchFixtures();

        Sanctum::actingAs($superAdmin, ['*']);

        $response = $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(200);

        $this->assertNotEmpty($response->json('data.grouped.users'));
        $this->assertNotEmpty($response->json('data.grouped.staff'));
        $this->assertNotEmpty($response->json('data.grouped.roles'));
        $this->assertNotEmpty($response->json('data.grouped.permissions'));

        $types = collect($response->json('data.results'))->pluck('type')->all();

        $this->assertContains('user', $types);
        $this->assertContains('staff', $types);
        $this->assertContains('role', $types);
        $this->assertContains('permission', $types);
    }

    public function test_short_query_returns_validation_error(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $this->getJson('/api/dashboard/search?q=a')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_response_does_not_expose_sensitive_user_fields(): void
    {
        $admin = $this->userWithRole('admin');
        User::factory()->create([
            'name' => 'ali sensitive',
            'email' => 'ali.sensitive@example.com',
            'remember_token' => 'secret-token',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $content = $this->getJson('/api/dashboard/search?q=ali')
            ->assertStatus(200)
            ->getContent();

        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('remember_token', $content);
        $this->assertStringNotContainsString('secret-token', $content);
    }

    private function createSearchFixtures(): void
    {
        $staff = User::factory()->create([
            'name' => 'ali staff',
            'email' => 'ali.staff@example.com',
        ]);
        $staff->assignRole('staff');

        User::factory()->create([
            'name' => 'ali customer',
            'email' => 'ali.customer@example.com',
        ]);

        Role::create([
            'name' => 'ali-reviewer',
            'guard_name' => 'web',
        ]);

        Permission::create([
            'name' => 'ali.permission.view',
            'guard_name' => 'web',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
