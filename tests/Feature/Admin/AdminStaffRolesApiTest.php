<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminStaffRolesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_super_admin_can_change_staff_to_admin(): void
    {
        $actor = $this->userWithRole('super-admin');
        $staff = $this->userWithRole('staff');
        Sanctum::actingAs($actor);

        $this->putJson("/api/admin/staff/{$staff->id}/roles", [
            'roles' => ['admin'],
        ])->assertOk();

        $this->assertTrue($staff->fresh()->hasExactRoles(['admin']));
    }

    public function test_authorized_admin_can_sync_staff_roles_and_receives_staff_payload(): void
    {
        $actor = $this->userWithRole('admin');
        $actor->givePermissionTo(Permission::findByName('admin.staff.assign_roles', 'web'));
        $staff = $this->userWithRole('staff');
        Sanctum::actingAs($actor);

        $this->putJson("/api/admin/staff/{$staff->id}/roles", [
            'roles' => ['staff', 'admin'],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم تحديث أدوار الموظف بنجاح.')
            ->assertJsonPath('data.user.id', $staff->id)
            ->assertJsonPath('data.user.name', $staff->name)
            ->assertJsonPath('data.user.email', $staff->email)
            ->assertJsonPath('data.user.roles', ['admin', 'staff'])
            ->assertJsonPath('errors', null);

        $this->assertTrue($staff->fresh()->hasExactRoles(['admin', 'staff']));
    }

    public function test_internal_user_without_permission_is_forbidden(): void
    {
        Sanctum::actingAs($this->userWithRole('staff'));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/roles", [
            'roles' => ['admin'],
        ])->assertForbidden();
    }

    #[DataProvider('externalActorProvider')]
    public function test_external_actor_is_forbidden_even_with_direct_permission(string $role): void
    {
        $actor = $this->userWithRole($role);
        $actor->givePermissionTo(Permission::findByName('admin.staff.assign_roles', 'web'));
        $target = $this->userWithRole('staff');
        Sanctum::actingAs($actor);

        $this->putJson("/api/admin/staff/{$target->id}/roles", [
            'roles' => ['admin'],
        ])->assertForbidden();
    }

    public static function externalActorProvider(): array
    {
        return [
            'client' => ['client'],
            'designer' => ['designer'],
        ];
    }

    public function test_empty_roles_are_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/roles", [
            'roles' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('roles');
    }

    #[DataProvider('invalidRoleProvider')]
    public function test_disallowed_roles_are_rejected(string $role): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/roles", [
            'roles' => [$role],
        ])->assertUnprocessable()->assertJsonValidationErrors('roles.0');
    }

    public static function invalidRoleProvider(): array
    {
        return [
            'super admin' => ['super-admin'],
            'client' => ['client'],
            'designer' => ['designer'],
            'unknown' => ['unknown-role'],
        ];
    }

    public function test_additional_input_is_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/roles", [
            'roles' => ['admin'],
            'permissions' => ['admin.staff.view'],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions');
    }

    public function test_super_admin_target_is_not_found(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('super-admin');

        $this->putJson("/api/admin/staff/{$target->id}/roles", [
            'roles' => ['admin'],
        ])->assertNotFound();
    }

    public function test_external_only_target_is_not_found(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('client');

        $this->putJson("/api/admin/staff/{$target->id}/roles", [
            'roles' => ['admin'],
        ])->assertNotFound();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
