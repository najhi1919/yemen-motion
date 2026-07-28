<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminStaffApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_cannot_read_or_create_staff(): void
    {
        $this->getJson('/api/admin/staff')->assertUnauthorized();
        $this->postJson('/api/admin/staff', $this->validPayload())->assertUnauthorized();
    }

    public function test_internal_user_without_permissions_cannot_read_or_create_staff(): void
    {
        $admin = $this->userWithRole('admin');
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/admin/staff')->assertForbidden();
        $this->postJson('/api/admin/staff', $this->validPayload())->assertForbidden();
    }

    public function test_delegated_admin_can_read_internal_team_only(): void
    {
        $viewer = $this->userWithRole('admin');
        $viewer->givePermissionTo('admin.staff.view');

        $staff = $this->userWithRole('staff', [
            'name' => 'Staff Member',
            'email' => 'staff.member@example.com',
        ]);
        $admin = $this->userWithRole('admin', [
            'name' => 'Admin Member',
            'email' => 'admin.member@example.com',
        ]);
        $superAdmin = $this->userWithRole('super-admin');
        $client = $this->userWithRole('client');
        $designer = $this->userWithRole('designer');

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->getJson('/api/admin/staff?sort_by=name&sort_direction=asc')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم جلب فريق العمل بنجاح.')
            ->assertJsonPath('meta.summary.total', 3);

        $ids = collect($response->json('data.data'))->pluck('id');

        $this->assertTrue($ids->contains($viewer->id));
        $this->assertTrue($ids->contains($staff->id));
        $this->assertTrue($ids->contains($admin->id));
        $this->assertFalse($ids->contains($superAdmin->id));
        $this->assertFalse($ids->contains($client->id));
        $this->assertFalse($ids->contains($designer->id));
    }

    public function test_staff_role_with_view_permission_can_read_team(): void
    {
        $viewer = $this->userWithRole('staff');
        $viewer->givePermissionTo('admin.staff.view');
        Sanctum::actingAs($viewer, ['*']);

        $this->getJson('/api/admin/staff')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $viewer->id);
    }

    public function test_external_roles_remain_forbidden_even_with_staff_permissions(): void
    {
        foreach (['client', 'designer'] as $role) {
            $user = $this->userWithRole($role);
            $user->givePermissionTo([
                'admin.staff.view',
                'admin.staff.create',
                'admin.staff.activity.view',
            ]);
            Sanctum::actingAs($user, ['*']);

            $this->getJson('/api/admin/staff')->assertForbidden();
            $this->postJson('/api/admin/staff', $this->validPayload([
                'email' => "{$role}.forbidden@example.com",
            ]))->assertForbidden();
        }
    }

    public function test_role_and_search_filters_are_scoped_to_internal_team(): void
    {
        $viewer = $this->userWithRole('admin');
        $viewer->givePermissionTo('admin.staff.view');

        $staff = $this->userWithRole('staff', [
            'name' => 'Ali Staff',
            'email' => 'ali.staff@example.com',
        ]);
        $admin = $this->userWithRole('admin', [
            'name' => 'Ali Admin',
            'email' => 'ali.admin@example.com',
        ]);

        Sanctum::actingAs($viewer, ['*']);

        $staffResponse = $this->getJson('/api/admin/staff?search=Ali&role=staff')
            ->assertOk();

        $this->assertSame(
            [$staff->id],
            collect($staffResponse->json('data.data'))->pluck('id')->all(),
        );

        $adminResponse = $this->getJson('/api/admin/staff?search=Ali&role=admin')
            ->assertOk();

        $this->assertContains(
            $admin->id,
            collect($adminResponse->json('data.data'))->pluck('id')->all(),
        );
    }

    public function test_unknown_list_parameter_is_rejected(): void
    {
        $viewer = $this->userWithRole('admin');
        $viewer->givePermissionTo('admin.staff.view');
        Sanctum::actingAs($viewer, ['*']);

        $this->getJson('/api/admin/staff?payload=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payload']);
    }

    public function test_delegated_admin_can_create_staff_with_create_permission(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->givePermissionTo('admin.staff.create');
        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson('/api/admin/staff', $this->validPayload([
            'name' => 'Delegated Staff',
            'email' => 'delegated.staff@example.com',
            'role' => 'staff',
        ]))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'staff');

        $created = User::findOrFail($response->json('data.user.id'));

        $this->assertTrue($created->hasRole('staff'));
    }

    public function test_delegated_creator_cannot_create_admin_role(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->givePermissionTo('admin.staff.create');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/admin/staff', $this->validPayload([
            'email' => 'delegated.admin.denied@example.com',
            'role' => 'admin',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_super_admin_can_read_and_create_without_permission_pivots(): void
    {
        $role = Role::findByName(User::superAdminRoleName(), 'web');
        $role->syncPermissions([]);

        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $this->getJson('/api/admin/staff')->assertOk();
        $this->postJson('/api/admin/staff', $this->validPayload([
            'email' => 'super.created.staff@example.com',
        ]))->assertCreated();
    }

    public function test_super_admin_can_create_admin_user_with_admin_role(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $this->postJson('/api/admin/staff', $this->validPayload([
            'name' => 'Admin Operator',
            'email' => 'admin.operator@example.com',
            'role' => 'admin',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.user.role', 'admin');

        $user = User::where('email', 'admin.operator@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_super_admin_role_cannot_be_created_from_staff_endpoint(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $this->postJson('/api/admin/staff', $this->validPayload([
            'role' => 'super-admin',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_duplicate_email_and_missing_confirmation_are_rejected(): void
    {
        User::factory()->create(['email' => 'duplicate.staff@example.com']);

        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $this->postJson('/api/admin/staff', $this->validPayload([
            'email' => 'duplicate.staff@example.com',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $payload = $this->validPayload(['email' => 'missing.confirmation@example.com']);
        unset($payload['password_confirmation']);

        $this->postJson('/api/admin/staff', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_created_password_is_hashed(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $this->postJson('/api/admin/staff', $this->validPayload([
            'email' => 'hashed.staff@example.com',
        ]))->assertCreated();

        $user = User::where('email', 'hashed.staff@example.com')->firstOrFail();

        $this->assertNotSame('password-secret', $user->password);
        $this->assertTrue(Hash::check('password-secret', $user->password));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Staff',
            'email' => 'new.staff@example.com',
            'password' => 'password-secret',
            'password_confirmation' => 'password-secret',
            'role' => 'staff',
        ], $overrides);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
