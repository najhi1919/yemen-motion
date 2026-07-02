<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_super_admin_can_list_permissions(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/admin/permissions')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم جلب الصلاحيات بنجاح')
            ->assertJsonFragment([
                'name' => 'admin.roles.sync_permissions',
                'is_system' => true,
            ]);
    }

    public function test_admin_without_permission_management_cannot_list_permissions(): void
    {
        $admin = User::factory()->create(['email' => 'access-permissions-admin@example.com']);
        $admin->assignRole('admin');

        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/admin/permissions')
            ->assertStatus(403);
    }

    public function test_super_admin_can_create_custom_role(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/admin/roles', [
            'name' => 'support-agent',
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'support-agent')
            ->assertJsonPath('data.is_protected', false);

        $this->assertDatabaseHas('roles', [
            'name' => 'support-agent',
            'guard_name' => 'web',
        ]);
    }

    public function test_admin_without_create_permission_cannot_create_role(): void
    {
        $admin = User::factory()->create(['email' => 'create-role-admin@example.com']);
        $admin->assignRole('admin');

        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/admin/roles', [
            'name' => 'finance-manager',
        ])->assertStatus(403);
    }

    public function test_super_admin_can_update_custom_role_name(): void
    {
        $this->actingAsSuperAdmin();

        $role = Role::create([
            'name' => 'support-agent',
            'guard_name' => 'web',
        ]);

        $this->patchJson("/api/admin/roles/{$role->id}", [
            'name' => 'support-lead',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'support-lead');

        $this->assertDatabaseHas('roles', ['name' => 'support-lead']);
        $this->assertDatabaseMissing('roles', ['name' => 'support-agent']);
    }

    public function test_protected_role_cannot_be_updated(): void
    {
        $this->actingAsSuperAdmin();

        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $this->patchJson("/api/admin/roles/{$adminRole->id}", [
            'name' => 'admin-renamed',
        ])->assertStatus(422);
    }

    public function test_protected_role_cannot_be_deleted(): void
    {
        $this->actingAsSuperAdmin();

        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $this->deleteJson("/api/admin/roles/{$adminRole->id}")
            ->assertStatus(422);
    }

    public function test_role_assigned_to_users_cannot_be_deleted(): void
    {
        $this->actingAsSuperAdmin();

        $role = Role::create([
            'name' => 'support-agent',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create(['email' => 'support-agent-user@example.com']);
        $user->assignRole($role);

        $this->deleteJson("/api/admin/roles/{$role->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('roles', ['name' => 'support-agent']);
    }

    public function test_super_admin_can_delete_unused_custom_role(): void
    {
        $this->actingAsSuperAdmin();

        $role = Role::create([
            'name' => 'temporary-role',
            'guard_name' => 'web',
        ]);

        $this->deleteJson("/api/admin/roles/{$role->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم حذف الدور بنجاح');

        $this->assertDatabaseMissing('roles', ['name' => 'temporary-role']);
    }

    public function test_super_admin_can_sync_permissions_to_custom_role(): void
    {
        $this->actingAsSuperAdmin();

        $role = Role::create([
            'name' => 'support-agent',
            'guard_name' => 'web',
        ]);

        $this->putJson("/api/admin/roles/{$role->id}/permissions", [
            'permissions' => [
                'dashboard.overview.view',
                'admin.users.view',
            ],
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'support-agent')
            ->assertJsonPath('data.permissions_count', 2)
            ->assertJsonFragment(['dashboard.overview.view'])
            ->assertJsonFragment(['admin.users.view']);

        $role->refresh();

        $this->assertTrue($role->hasPermissionTo('dashboard.overview.view'));
        $this->assertTrue($role->hasPermissionTo('admin.users.view'));
    }

    public function test_super_admin_role_permissions_cannot_be_synced_from_api(): void
    {
        $this->actingAsSuperAdmin();

        $superAdminRole = Role::where('name', 'super-admin')->firstOrFail();

        $this->putJson("/api/admin/roles/{$superAdminRole->id}/permissions", [
            'permissions' => [
                'dashboard.overview.view',
            ],
        ])->assertStatus(422);
    }

    public function test_super_admin_can_show_role_details(): void
    {
        $this->actingAsSuperAdmin();

        $role = Role::where('name', 'admin')->firstOrFail();

        $this->getJson("/api/admin/roles/{$role->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'admin')
            ->assertJsonPath('data.is_protected', true);
    }

    public function test_super_admin_can_create_custom_permission(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/admin/permissions', [
            'name' => 'custom.reports.export',
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'custom.reports.export')
            ->assertJsonPath('data.is_system', false);

        $this->assertDatabaseHas('permissions', [
            'name' => 'custom.reports.export',
            'guard_name' => 'web',
        ]);

        $superAdminRole = Role::where('name', 'super-admin')->firstOrFail();
        $this->assertTrue($superAdminRole->hasPermissionTo('custom.reports.export'));
    }

    public function test_admin_without_permission_create_cannot_create_custom_permission(): void
    {
        $admin = User::factory()->create(['email' => 'create-permission-admin@example.com']);
        $admin->assignRole('admin');

        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/admin/permissions', [
            'name' => 'custom.reports.export',
        ])->assertStatus(403);
    }

    public function test_system_permission_cannot_be_updated(): void
    {
        $this->actingAsSuperAdmin();

        $permission = Permission::where('name', 'admin.users.view')->firstOrFail();

        $this->patchJson("/api/admin/permissions/{$permission->id}", [
            'name' => 'custom.users.view',
        ])->assertStatus(422);
    }

    public function test_system_permission_cannot_be_deleted(): void
    {
        $this->actingAsSuperAdmin();

        $permission = Permission::where('name', 'admin.users.view')->firstOrFail();

        $this->deleteJson("/api/admin/permissions/{$permission->id}")
            ->assertStatus(422);
    }

    public function test_super_admin_can_update_unused_custom_permission(): void
    {
        $this->actingAsSuperAdmin();

        $permission = Permission::create([
            'name' => 'custom.reports.export',
            'guard_name' => 'web',
        ]);

        Role::where('name', 'super-admin')
            ->firstOrFail()
            ->givePermissionTo($permission);

        $this->patchJson("/api/admin/permissions/{$permission->id}", [
            'name' => 'custom.reports.download',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'custom.reports.download');

        $this->assertDatabaseHas('permissions', ['name' => 'custom.reports.download']);
        $this->assertDatabaseMissing('permissions', ['name' => 'custom.reports.export']);
    }

    public function test_custom_permission_assigned_to_non_super_admin_role_cannot_be_deleted(): void
    {
        $this->actingAsSuperAdmin();

        $permission = Permission::create([
            'name' => 'custom.reports.export',
            'guard_name' => 'web',
        ]);

        Role::where('name', 'super-admin')->firstOrFail()->givePermissionTo($permission);

        $role = Role::create([
            'name' => 'support-agent',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $this->deleteJson("/api/admin/permissions/{$permission->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('permissions', ['name' => 'custom.reports.export']);
    }

    public function test_super_admin_can_delete_custom_permission_used_only_by_super_admin(): void
    {
        $this->actingAsSuperAdmin();

        $permission = Permission::create([
            'name' => 'custom.temporary.access',
            'guard_name' => 'web',
        ]);

        Role::where('name', 'super-admin')->firstOrFail()->givePermissionTo($permission);

        $this->deleteJson("/api/admin/permissions/{$permission->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم حذف الصلاحية بنجاح');

        $this->assertDatabaseMissing('permissions', ['name' => 'custom.temporary.access']);
    }

    private function actingAsSuperAdmin(): User
    {
        $superAdmin = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
        ]);

        $superAdmin->assignRole('super-admin');

        Sanctum::actingAs($superAdmin, ['*']);

        return $superAdmin;
    }
}
