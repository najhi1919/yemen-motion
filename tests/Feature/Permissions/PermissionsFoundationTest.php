<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_roles_seeder_creates_protected_roles_and_baseline_permissions(): void
    {
        $this->seed(AuthRolesSeeder::class);

        foreach (config('yemen-motion-permissions.roles') as $roleName) {
            $this->assertDatabaseHas('roles', [
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }

        foreach (config('yemen-motion-permissions.permissions') as $permission) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permission['name'],
                'guard_name' => 'web',
            ]);
        }

        $this->assertGreaterThan(0, Permission::count());
    }

    public function test_super_admin_role_receives_all_registered_permissions(): void
    {
        $this->seed(AuthRolesSeeder::class);

        $superAdmin = Role::where('name', 'super-admin')->firstOrFail();
        $permissionNames = collect(config('yemen-motion-permissions.permissions'))->pluck('name');

        foreach ($permissionNames as $permissionName) {
            $this->assertTrue(
                $superAdmin->hasPermissionTo($permissionName),
                "Super Admin should have permission: {$permissionName}",
            );
        }
    }

    public function test_admin_role_receives_current_admin_baseline_permissions(): void
    {
        $this->seed(AuthRolesSeeder::class);

        $admin = Role::where('name', 'admin')->firstOrFail();

        foreach (config('yemen-motion-permissions.role_permissions.admin') as $permissionName) {
            $this->assertTrue(
                $admin->hasPermissionTo($permissionName),
                "Admin should have baseline permission: {$permissionName}",
            );
        }
    }

    public function test_internal_dashboard_overview_baseline_is_limited_to_admin_and_staff_roles(): void
    {
        $this->seed(AuthRolesSeeder::class);

        $this->assertTrue(Role::where('name', 'admin')->firstOrFail()->hasPermissionTo('dashboard.overview.view'));
        $this->assertTrue(Role::where('name', 'staff')->firstOrFail()->hasPermissionTo('dashboard.overview.view'));
        $this->assertFalse(Role::where('name', 'client')->firstOrFail()->hasPermissionTo('dashboard.overview.view'));
        $this->assertFalse(Role::where('name', 'designer')->firstOrFail()->hasPermissionTo('dashboard.overview.view'));
    }

    public function test_auth_roles_seeder_removes_obsolete_registered_baseline_permissions(): void
    {
        $this->seed(AuthRolesSeeder::class);

        $customPermission = Permission::create([
            'name' => 'client.custom.view',
            'guard_name' => 'web',
        ]);

        $client = Role::where('name', 'client')->firstOrFail();
        $designer = Role::where('name', 'designer')->firstOrFail();

        $client->givePermissionTo([
            'dashboard.overview.view',
            $customPermission,
        ]);
        $designer->givePermissionTo('dashboard.overview.view');

        $this->seed(AuthRolesSeeder::class);

        $client->refresh();
        $designer->refresh();

        $this->assertFalse($client->hasPermissionTo('dashboard.overview.view'));
        $this->assertTrue($client->hasPermissionTo('client.custom.view'));
        $this->assertFalse($designer->hasPermissionTo('dashboard.overview.view'));
    }

    public function test_database_seeder_creates_super_admin_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdminUser = User::where('email', 'admin@yemenmotion.com')->first();

        $this->assertNotNull($superAdminUser);
        $this->assertTrue($superAdminUser->hasRole('super-admin'));
        $this->assertTrue($superAdminUser->can('admin.permissions.view'));
        $this->assertTrue($superAdminUser->can('admin.roles.sync_permissions'));
    }
}
