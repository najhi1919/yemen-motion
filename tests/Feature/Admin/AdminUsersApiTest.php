<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminUsersApiTest extends TestCase
{
    use RefreshDatabase;

    private Permission $viewUsersPermission;
    private Permission $assignRolesPermission;

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

        $this->viewUsersPermission = Permission::firstOrCreate([
            'name' => 'admin.users.view',
            'guard_name' => 'web',
        ]);

        $this->assignRolesPermission = Permission::firstOrCreate([
            'name' => 'admin.users.assign_roles',
            'guard_name' => 'web',
        ]);

        Role::where('name', 'super-admin')
            ->firstOrFail()
            ->givePermissionTo([$this->viewUsersPermission, $this->assignRolesPermission]);

        Role::where('name', 'admin')
            ->firstOrFail()
            ->givePermissionTo($this->viewUsersPermission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_list_users(): void
    {
        $admin = $this->userWithRole('admin');

        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/admin/users')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'data',
                ],
                'meta' => [
                    'available_roles',
                ],
            ]);
    }

    public function test_user_without_manage_permission_cannot_sync_user_roles(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('client');

        Sanctum::actingAs($admin, ['*']);

        $this->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => ['staff'],
        ])->assertStatus(403);
    }

    public function test_super_admin_can_sync_regular_user_roles(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        $target = $this->userWithRole('client');

        Sanctum::actingAs($superAdmin, ['*']);

        $response = $this->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => ['staff', 'client', 'staff'],
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم تحديث أدوار المستخدم بنجاح');

        $target->refresh();

        $this->assertEqualsCanonicalizing(['staff', 'client'], $response->json('data.roles'));
        $this->assertEqualsCanonicalizing(['staff', 'client'], $target->roles->pluck('name')->all());
    }

    public function test_admin_cannot_assign_super_admin_role(): void
    {
        $admin = $this->adminWithRoleAssignmentPermission();
        $target = $this->userWithRole('client');

        Sanctum::actingAs($admin, ['*']);

        $this->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => ['super-admin'],
        ])->assertStatus(403);
    }

    public function test_admin_cannot_modify_super_admin_user_roles(): void
    {
        $admin = $this->adminWithRoleAssignmentPermission();
        $target = $this->userWithRole('super-admin');

        Sanctum::actingAs($admin, ['*']);

        $this->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => ['admin'],
        ])->assertStatus(403);
    }

    public function test_super_admin_role_cannot_be_removed_from_super_admin_account(): void
    {
        $superAdmin = $this->userWithRole('super-admin');

        Sanctum::actingAs($superAdmin, ['*']);

        $this->putJson("/api/admin/users/{$superAdmin->id}/roles", [
            'roles' => ['admin'],
        ])->assertStatus(422);

        $superAdmin->refresh();

        $this->assertTrue($superAdmin->hasRole('super-admin'));
    }

    public function test_sync_user_roles_rejects_unknown_role(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        $target = $this->userWithRole('client');

        Sanctum::actingAs($superAdmin, ['*']);

        $this->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => ['unknown-role'],
        ])->assertStatus(422);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function adminWithRoleAssignmentPermission(): User
    {
        Role::where('name', 'admin')
            ->firstOrFail()
            ->givePermissionTo($this->assignRolesPermission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->userWithRole('admin');
    }
}
