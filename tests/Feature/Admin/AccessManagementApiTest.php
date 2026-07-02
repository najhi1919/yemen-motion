<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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
        $superAdmin = User::factory()->create(['email' => 'access-permissions-super-admin@example.com']);
        $superAdmin->assignRole('super-admin');

        Sanctum::actingAs($superAdmin, ['*']);

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
        $superAdmin = User::factory()->create(['email' => 'create-role-super-admin@example.com']);
        $superAdmin->assignRole('super-admin');

        Sanctum::actingAs($superAdmin, ['*']);

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

    public function test_super_admin_can_sync_permissions_to_custom_role(): void
    {
        $superAdmin = User::factory()->create(['email' => 'sync-role-super-admin@example.com']);
        $superAdmin->assignRole('super-admin');

        $role = Role::create([
            'name' => 'support-agent',
            'guard_name' => 'web',
        ]);

        Sanctum::actingAs($superAdmin, ['*']);

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
        $superAdminUser = User::factory()->create(['email' => 'sync-super-admin-denied@example.com']);
        $superAdminUser->assignRole('super-admin');

        $superAdminRole = Role::where('name', 'super-admin')->firstOrFail();

        Sanctum::actingAs($superAdminUser, ['*']);

        $this->putJson("/api/admin/roles/{$superAdminRole->id}/permissions", [
            'permissions' => [
                'dashboard.overview.view',
            ],
        ])->assertStatus(422);
    }

    public function test_super_admin_can_show_role_details(): void
    {
        $superAdmin = User::factory()->create(['email' => 'show-role-super-admin@example.com']);
        $superAdmin->assignRole('super-admin');

        $role = Role::where('name', 'admin')->firstOrFail();

        Sanctum::actingAs($superAdmin, ['*']);

        $this->getJson("/api/admin/roles/{$role->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'admin')
            ->assertJsonPath('data.is_protected', true);
    }
}
