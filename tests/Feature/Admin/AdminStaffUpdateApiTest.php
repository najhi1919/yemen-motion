<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminStaffUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_cannot_update_staff(): void
    {
        $target = $this->userWithRole('staff');

        $this->patchJson("/api/admin/staff/{$target->id}", $this->validPayload())
            ->assertUnauthorized();
    }

    public function test_internal_user_without_update_permission_is_forbidden(): void
    {
        $actor = $this->userWithRole('admin');
        $target = $this->userWithRole('staff');
        Sanctum::actingAs($actor, ['*']);

        $this->patchJson("/api/admin/staff/{$target->id}", $this->validPayload())
            ->assertForbidden();
    }

    public function test_delegated_admin_can_update_name_and_email_without_changing_role_or_password(): void
    {
        $actor = $this->userWithRole('admin');
        $actor->givePermissionTo('admin.staff.update');

        $target = $this->userWithRole('staff', [
            'name' => 'Original Staff',
            'email' => 'original.staff@example.com',
        ]);
        $originalPassword = $target->password;
        $originalRoles = $target->getRoleNames()->sort()->values()->all();

        Sanctum::actingAs($actor, ['*']);

        $this->patchJson("/api/admin/staff/{$target->id}", [
            'name' => 'Updated Staff',
            'email' => 'updated.staff@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم تحديث بيانات الموظف بنجاح.')
            ->assertJsonPath('data.user.id', $target->id)
            ->assertJsonPath('data.user.name', 'Updated Staff')
            ->assertJsonPath('data.user.email', 'updated.staff@example.com');

        $target->refresh();

        $this->assertSame('Updated Staff', $target->name);
        $this->assertSame('updated.staff@example.com', $target->email);
        $this->assertSame($originalPassword, $target->password);
        $this->assertSame($originalRoles, $target->getRoleNames()->sort()->values()->all());
    }

    public function test_staff_role_with_update_permission_can_update_internal_account(): void
    {
        $actor = $this->userWithRole('staff');
        $actor->givePermissionTo('admin.staff.update');
        $target = $this->userWithRole('staff');
        Sanctum::actingAs($actor, ['*']);

        $this->patchJson("/api/admin/staff/{$target->id}", [
            'name' => 'Delegated Editor Result',
            'email' => 'delegated.editor.result@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Delegated Editor Result');
    }

    public function test_external_roles_remain_forbidden_even_with_update_permission(): void
    {
        foreach (['client', 'designer'] as $role) {
            $actor = $this->userWithRole($role);
            $actor->givePermissionTo('admin.staff.update');
            $target = $this->userWithRole('staff');

            Sanctum::actingAs($actor, ['*']);

            $this->patchJson("/api/admin/staff/{$target->id}", [
                'name' => 'Forbidden Update',
                'email' => "{$role}.forbidden.update@example.com",
            ])->assertForbidden();
        }
    }

    public function test_super_admin_can_update_without_permission_pivots(): void
    {
        $role = Role::findByName(User::superAdminRoleName(), 'web');
        $role->syncPermissions([]);

        $actor = $this->userWithRole('super-admin');
        $target = $this->userWithRole('admin');
        Sanctum::actingAs($actor, ['*']);

        $this->patchJson("/api/admin/staff/{$target->id}", [
            'name' => 'Updated Admin Account',
            'email' => 'updated.admin.account@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Updated Admin Account');
    }

    public function test_super_admin_target_is_not_exposed_by_staff_update_endpoint(): void
    {
        $actor = $this->userWithRole('super-admin');
        $target = $this->userWithRole('super-admin');
        Sanctum::actingAs($actor, ['*']);

        $this->patchJson("/api/admin/staff/{$target->id}", $this->validPayload())
            ->assertNotFound();
    }

    public function test_external_target_is_not_exposed_by_staff_update_endpoint(): void
    {
        $actor = $this->userWithRole('super-admin');
        Sanctum::actingAs($actor, ['*']);

        foreach (['client', 'designer'] as $role) {
            $target = $this->userWithRole($role);

            $this->patchJson("/api/admin/staff/{$target->id}", [
                'name' => 'External Target',
                'email' => "{$role}.target@example.com",
            ])->assertNotFound();
        }
    }

    public function test_duplicate_email_is_rejected_but_current_email_is_allowed(): void
    {
        $actor = $this->userWithRole('super-admin');
        $target = $this->userWithRole('staff', [
            'email' => 'current.staff@example.com',
        ]);
        User::factory()->create([
            'email' => 'occupied.staff@example.com',
        ]);
        Sanctum::actingAs($actor, ['*']);

        $this->patchJson("/api/admin/staff/{$target->id}", [
            'name' => 'Same Email',
            'email' => 'current.staff@example.com',
        ])->assertOk();

        $this->patchJson("/api/admin/staff/{$target->id}", [
            'name' => 'Duplicate Email',
            'email' => 'occupied.staff@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_role_password_and_other_fields_are_rejected(): void
    {
        $actor = $this->userWithRole('super-admin');
        $target = $this->userWithRole('staff');
        $originalPassword = $target->password;
        Sanctum::actingAs($actor, ['*']);

        $this->patchJson("/api/admin/staff/{$target->id}", [
            'name' => 'Attempted Escalation',
            'email' => 'attempted.escalation@example.com',
            'role' => 'admin',
            'password' => 'new-password-secret',
            'password_confirmation' => 'new-password-secret',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
                'password',
                'password_confirmation',
            ]);

        $target->refresh();

        $this->assertTrue($target->hasRole('staff'));
        $this->assertSame($originalPassword, $target->password);
        $this->assertNotSame('Attempted Escalation', $target->name);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Updated Staff',
            'email' => 'updated.staff@example.com',
        ], $overrides);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
