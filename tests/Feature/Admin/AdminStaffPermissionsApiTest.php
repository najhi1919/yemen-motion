<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminStaffPermissionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_super_admin_can_read_the_complete_registered_permission_catalog(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff');

        $response = $this->getJson("/api/admin/staff/{$target->id}/permissions")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم جلب صلاحيات الموظف بنجاح.')
            ->assertJsonPath('data.user.id', $target->id)
            ->assertJsonPath('errors', null);

        $expected = collect(config('yemen-motion-permissions.permissions'))
            ->sortBy([
                ['group', 'asc'],
                ['name', 'asc'],
            ])
            ->map(fn (array $permission): array => [
                'name' => $permission['name'],
                'group' => $permission['group'],
                'label_ar' => $permission['label_ar'],
            ])
            ->values()
            ->all();

        $this->assertSame($expected, $response->json('data.permissions.available'));
    }

    public function test_internal_manager_sees_only_registered_permissions_they_effectively_own(): void
    {
        $actor = $this->userWithRole('admin');
        $actor->givePermissionTo([
            'admin.staff.assign_permissions',
            'admin.staff.view',
        ]);
        Sanctum::actingAs($actor);
        $target = $this->userWithRole('staff');

        $response = $this->getJson("/api/admin/staff/{$target->id}/permissions")
            ->assertOk();

        $availableNames = collect($response->json('data.permissions.available'))
            ->pluck('name')
            ->all();
        $registeredNames = $this->registeredPermissionNames();
        $expectedNames = $this->sortedPermissionNames(
            $actor->getAllPermissions()
                ->pluck('name')
                ->intersect($registeredNames),
        );

        $this->assertSame($expectedNames, $availableNames);
        $this->assertContains(
            'admin.staff.assign_permissions',
            $availableNames,
        );
        $this->assertContains(
            'admin.staff.view',
            $availableNames,
        );
        $this->assertSame($availableNames, $this->sortedPermissionNames($availableNames));
        $this->assertSame([], array_values(array_diff($availableNames, $registeredNames)));
    }

    public function test_super_admin_can_sync_direct_permissions(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => [
                'admin.staff.view',
                'admin.staff.update',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.permissions.direct', [
                'admin.staff.update',
                'admin.staff.view',
            ]);

        $this->assertSame([
            'admin.staff.update',
            'admin.staff.view',
        ], $target->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all());
    }

    public function test_internal_manager_can_sync_permissions_within_their_scope(): void
    {
        $actor = $this->authorizedManager([
            'admin.staff.view',
            'admin.staff.update',
        ]);
        Sanctum::actingAs($actor);
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => [
                'admin.staff.update',
                'admin.staff.view',
            ],
        ])->assertOk();

        $this->assertSame([
            'admin.staff.update',
            'admin.staff.view',
        ], $target->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all());
    }

    public function test_internal_manager_cannot_grant_a_registered_permission_they_do_not_own(): void
    {
        Sanctum::actingAs($this->authorizedManager(['admin.staff.view']));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => ['admin.staff.update'],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
    }

    public function test_direct_permissions_outside_the_managers_scope_are_preserved(): void
    {
        Sanctum::actingAs($this->authorizedManager([
            'admin.staff.view',
            'admin.staff.update',
        ]));
        $target = $this->userWithRole('staff');
        $target->givePermissionTo([
            'admin.staff.view',
            'admin.staff.create',
        ]);

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => ['admin.staff.update'],
        ])->assertOk();

        $this->assertSame([
            'admin.staff.create',
            'admin.staff.update',
        ], $target->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all());
    }

    public function test_empty_array_removes_only_manageable_direct_permissions(): void
    {
        Sanctum::actingAs($this->authorizedManager(['admin.staff.view']));
        $target = $this->userWithRole('staff');
        $target->givePermissionTo([
            'admin.staff.view',
            'admin.staff.create',
        ]);

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => [],
        ])->assertOk();

        $this->assertSame(
            ['admin.staff.create'],
            $target->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_inherited_role_permissions_are_not_removed(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        Role::findByName('staff', 'web')->givePermissionTo('admin.staff.view');
        $target = $this->userWithRole('staff');

        $response = $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.permissions.direct', []);

        $freshTarget = $target->fresh();
        $expectedDirect = [];
        $expectedInherited = $this->sortedPermissionNames(
            $freshTarget->getPermissionsViaRoles()->pluck('name'),
        );
        $expectedEffective = $this->sortedPermissionNames([
            ...$expectedDirect,
            ...$expectedInherited,
        ]);

        $this->assertSame($expectedInherited, $response->json('data.permissions.inherited'));
        $this->assertSame($expectedEffective, $response->json('data.permissions.effective'));
        $this->assertContains('admin.staff.view', $expectedInherited);
        $this->assertTrue($freshTarget->hasPermissionTo('admin.staff.view'));
        $this->assertTrue($freshTarget->getDirectPermissions()->isEmpty());
    }

    public function test_internal_user_without_assign_permission_is_forbidden(): void
    {
        Sanctum::actingAs($this->userWithRole('staff'));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => [],
        ])->assertForbidden();
    }

    #[DataProvider('externalActorProvider')]
    public function test_external_actor_is_forbidden_even_with_direct_permission(string $role): void
    {
        $actor = $this->userWithRole($role);
        $actor->givePermissionTo('admin.staff.assign_permissions');
        Sanctum::actingAs($actor);
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => [],
        ])->assertForbidden();
    }

    public static function externalActorProvider(): array
    {
        return [
            'client' => ['client'],
            'designer' => ['designer'],
        ];
    }

    public function test_unknown_permission_is_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => ['unknown.permission'],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
    }

    public function test_database_permission_not_registered_in_config_is_rejected(): void
    {
        Permission::create([
            'name' => 'database.only.permission',
            'guard_name' => 'web',
        ]);
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => ['database.only.permission'],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
    }

    public function test_additional_input_is_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => [],
            'roles' => ['admin'],
        ])->assertUnprocessable()->assertJsonValidationErrors('roles');
    }

    public function test_super_admin_target_is_not_found(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('super-admin');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => [],
        ])->assertNotFound();
    }

    public function test_external_only_target_is_not_found(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('client');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => [],
        ])->assertNotFound();
    }

    public function test_response_returns_sorted_direct_inherited_and_effective_permissions(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        Role::findByName('staff', 'web')->givePermissionTo('admin.staff.create');
        $target = $this->userWithRole('staff');

        $response = $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => [
                'admin.staff.view',
                'admin.staff.update',
            ],
        ])
            ->assertOk();

        $freshTarget = $target->fresh();
        $expectedDirect = $this->sortedPermissionNames([
            'admin.staff.update',
            'admin.staff.view',
        ]);
        $expectedInherited = $this->sortedPermissionNames(
            $freshTarget->getPermissionsViaRoles()->pluck('name'),
        );
        $expectedEffective = $this->sortedPermissionNames([
            ...$expectedDirect,
            ...$expectedInherited,
        ]);

        $this->assertSame($expectedDirect, $response->json('data.permissions.direct'));
        $this->assertSame($expectedInherited, $response->json('data.permissions.inherited'));
        $this->assertSame($expectedEffective, $response->json('data.permissions.effective'));
        $this->assertContains('admin.staff.create', $expectedInherited);
        $this->assertContains('admin.staff.update', $expectedDirect);
        $this->assertContains('admin.staff.view', $expectedDirect);
    }

    /**
     * @param list<string> $permissions
     */
    private function authorizedManager(array $permissions): User
    {
        $user = $this->userWithRole('admin');
        $user->givePermissionTo([
            'admin.staff.assign_permissions',
            ...$permissions,
        ]);

        return $user;
    }

    /**
     * @param iterable<int, string> $permissions
     * @return list<string>
     */
    private function sortedPermissionNames(iterable $permissions): array
    {
        return collect($permissions)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function registeredPermissionNames(): array
    {
        return $this->sortedPermissionNames(
            collect(config('yemen-motion-permissions.permissions', []))->pluck('name'),
        );
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
