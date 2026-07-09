<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminStaffApiTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_unauthenticated_user_cannot_create_staff(): void
    {
        $this->postJson('/api/admin/staff', $this->validPayload())
            ->assertStatus(401);
    }

    public function test_non_super_admin_cannot_create_staff(): void
    {
        $admin = $this->userWithRole('admin');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/admin/staff', $this->validPayload())
            ->assertStatus(403);
    }

    public function test_super_admin_can_create_staff_user_with_staff_role(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $response = $this->postJson('/api/admin/staff', $this->validPayload([
            'name' => 'Staff Operator',
            'email' => 'staff.operator@example.com',
            'role' => 'staff',
        ]))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم إنشاء الموظف بنجاح.')
            ->assertJsonPath('data.user.name', 'Staff Operator')
            ->assertJsonPath('data.user.email', 'staff.operator@example.com')
            ->assertJsonPath('data.user.role', 'staff')
            ->assertJsonMissing(['password' => 'password-secret']);

        $user = User::where('email', 'staff.operator@example.com')->firstOrFail();

        $this->assertSame($user->id, $response->json('data.user.id'));
        $this->assertTrue($user->hasRole('staff'));
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
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'admin');

        $user = User::where('email', 'admin.operator@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_super_admin_cannot_create_user_with_super_admin_role(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $this->postJson('/api/admin/staff', $this->validPayload([
            'role' => 'super-admin',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_duplicate_email_returns_validation_error(): void
    {
        User::factory()->create(['email' => 'duplicate.staff@example.com']);

        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $this->postJson('/api/admin/staff', $this->validPayload([
            'email' => 'duplicate.staff@example.com',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_password_confirmation_is_required(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $payload = $this->validPayload();
        unset($payload['password_confirmation']);

        $this->postJson('/api/admin/staff', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_created_password_is_hashed(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $this->postJson('/api/admin/staff', $this->validPayload([
            'email' => 'hashed.staff@example.com',
            'password' => 'password-secret',
            'password_confirmation' => 'password-secret',
        ]))
            ->assertStatus(201);

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
