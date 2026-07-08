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

    public function test_admin_can_filter_users_by_created_date_range(): void
    {
        $admin = $this->userWithRole('admin', [
            'created_at' => '2026-06-01 10:00:00',
        ]);
        $olderUser = $this->userWithRole('client', [
            'name' => 'Older User',
            'created_at' => '2026-06-25 10:00:00',
        ]);
        $insideUser = $this->userWithRole('client', [
            'name' => 'Inside User',
            'created_at' => '2026-07-04 10:00:00',
        ]);
        $newerUser = $this->userWithRole('client', [
            'name' => 'Newer User',
            'created_at' => '2026-07-12 10:00:00',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/admin/users?created_from=2026-07-01&created_to=2026-07-08')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $returnedIds = collect($response->json('data.data'))->pluck('id');

        $this->assertTrue($returnedIds->contains($insideUser->id));
        $this->assertFalse($returnedIds->contains($olderUser->id));
        $this->assertFalse($returnedIds->contains($newerUser->id));
        $this->assertFalse($returnedIds->contains($admin->id));
    }

    public function test_admin_users_date_filter_rejects_invalid_range(): void
    {
        $admin = $this->userWithRole('admin');

        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/admin/users?created_from=2026-07-08&created_to=2026-07-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['created_to']);
    }

    public function test_admin_can_combine_search_role_and_created_date_filters(): void
    {
        $admin = $this->userWithRole('admin', [
            'created_at' => '2026-06-01 10:00:00',
        ]);
        $matchingUser = $this->userWithRole('designer', [
            'name' => 'Ahmed Designer',
            'email' => 'ahmed.designer@example.com',
            'created_at' => '2026-07-04 10:00:00',
        ]);
        $wrongRoleUser = $this->userWithRole('client', [
            'name' => 'Ahmed Client',
            'email' => 'ahmed.client@example.com',
            'created_at' => '2026-07-04 10:00:00',
        ]);
        $wrongDateUser = $this->userWithRole('designer', [
            'name' => 'Ahmed Old Designer',
            'email' => 'ahmed.old@example.com',
            'created_at' => '2026-06-20 10:00:00',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/admin/users?search=Ahmed&role=designer&created_from=2026-07-01&created_to=2026-07-08')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $returnedIds = collect($response->json('data.data'))->pluck('id');

        $this->assertTrue($returnedIds->contains($matchingUser->id));
        $this->assertFalse($returnedIds->contains($wrongRoleUser->id));
        $this->assertFalse($returnedIds->contains($wrongDateUser->id));
        $this->assertFalse($returnedIds->contains($admin->id));
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

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
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
