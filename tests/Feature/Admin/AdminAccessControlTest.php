<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'staff', 'client', 'designer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_unauthenticated_user_cannot_access_admin_users_endpoint(): void
    {
        $this->getJson('/api/admin/users')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'غير مصادق عليه.',
                'data' => null,
                'errors' => null,
            ]);
    }

    public function test_unauthenticated_user_cannot_access_admin_roles_endpoint(): void
    {
        $this->getJson('/api/admin/roles')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'غير مصادق عليه.',
                'data' => null,
                'errors' => null,
            ]);
    }

    public function test_admin_can_access_admin_users_endpoint(): void
    {
        $admin = User::factory()->create(['email' => 'admin-access@example.com']);
        $admin->assignRole('admin');

        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/admin/users')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'تم جلب المستخدمين بنجاح',
                'errors' => null,
            ]);
    }

    public function test_admin_can_access_admin_roles_endpoint(): void
    {
        $admin = User::factory()->create(['email' => 'admin-roles-access@example.com']);
        $admin->assignRole('admin');

        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/admin/roles')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'تم جلب الأدوار بنجاح',
                'errors' => null,
            ]);
    }

    public function test_non_admin_roles_cannot_access_admin_users_endpoint(): void
    {
        foreach (['staff', 'client', 'designer'] as $role) {
            $user = User::factory()->create(['email' => "{$role}-users-denied@example.com"]);
            $user->assignRole($role);

            Sanctum::actingAs($user, ['*']);

            $this->getJson('/api/admin/users')
                ->assertStatus(403);
        }
    }

    public function test_non_admin_roles_cannot_access_admin_roles_endpoint(): void
    {
        foreach (['staff', 'client', 'designer'] as $role) {
            $user = User::factory()->create(['email' => "{$role}-roles-denied@example.com"]);
            $user->assignRole($role);

            Sanctum::actingAs($user, ['*']);

            $this->getJson('/api/admin/roles')
                ->assertStatus(403);
        }
    }
}
