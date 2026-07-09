<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AuthRolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $config = config('yemen-motion-permissions');

        $roles = $config['roles'] ?? [];
        $permissions = collect($config['permissions'] ?? [])
            ->pluck('name')
            ->filter()
            ->unique()
            ->values();
        $registeredPermissions = $permissions->all();

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }

        $availablePermissions = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        $superAdmin = Role::where('name', 'super-admin')
            ->where('guard_name', 'web')
            ->firstOrFail();

        // Super Admin must receive every current permission without removing
        // future UI-created custom permissions.
        $superAdmin->givePermissionTo($availablePermissions);

        foreach (($config['role_permissions'] ?? []) as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if (! $role) {
                continue;
            }

            $validPermissions = array_values(array_intersect($permissionNames, $availablePermissions));
            $customPermissions = $role->permissions()
                ->whereNotIn('name', $registeredPermissions)
                ->pluck('name')
                ->all();

            // Keep UI-managed custom permissions, but remove registered baseline
            // permissions that are no longer configured for this role.
            $role->syncPermissions(array_values(array_unique([
                ...$customPermissions,
                ...$validPermissions,
            ])));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
