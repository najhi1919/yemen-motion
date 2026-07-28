<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffPermissionRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_permissions_are_registered_without_baseline_grants_to_ordinary_roles(): void
    {
        $this->seed(AuthRolesSeeder::class);

        $permissions = [
            'admin.staff.view',
            'admin.staff.create',
            'admin.staff.update',
            'admin.staff.assign_roles',
            'admin.staff.assign_permissions',
            'admin.staff.activity.view',
            'admin.staff.disable',
            'admin.staff.restore',
            'admin.staff.delete',
        ];

        foreach ($permissions as $permission) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach (['admin', 'staff', 'client', 'designer'] as $roleName) {
            $role = Role::findByName($roleName, 'web');

            foreach ($permissions as $permission) {
                $this->assertFalse(
                    $role->hasPermissionTo($permission),
                    "{$roleName} must not receive {$permission} as a baseline grant.",
                );
            }
        }
    }

    public function test_super_admin_bypass_covers_every_staff_permission_without_pivots(): void
    {
        $this->seed(AuthRolesSeeder::class);

        $role = Role::findByName(User::superAdminRoleName(), 'web');
        $role->syncPermissions([]);

        $user = User::factory()->create();
        $user->assignRole($role);

        foreach ([
            'admin.staff.view',
            'admin.staff.create',
            'admin.staff.update',
            'admin.staff.assign_roles',
            'admin.staff.assign_permissions',
            'admin.staff.activity.view',
            'admin.staff.disable',
            'admin.staff.restore',
            'admin.staff.delete',
        ] as $permission) {
            $this->assertTrue(Gate::forUser($user)->allows($permission));
            $this->assertTrue($user->can($permission));
        }
    }
}
