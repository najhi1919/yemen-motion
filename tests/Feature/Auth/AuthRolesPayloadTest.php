<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthRolesPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['designer', 'super-admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_designer_roles_are_returned_by_login_and_current_user(): void
    {
        $user = $this->userWithRoles(['designer']);

        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('data.role', 'designer')
            ->assertJsonPath('data.user.roles', ['designer']);

        $this->withToken($login->json('data.token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.role', 'designer')
            ->assertJsonPath('data.user.roles', ['designer']);
    }

    public function test_super_admin_without_designer_does_not_gain_designer_membership(): void
    {
        $user = $this->userWithRoles(['super-admin']);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        $this->assertSame('super-admin', $response->json('data.role'));
        $this->assertContains('super-admin', $response->json('data.user.roles'));
        $this->assertNotContains('designer', $response->json('data.user.roles'));
    }

    public function test_multi_role_user_keeps_designer_and_super_admin_memberships(): void
    {
        $user = $this->userWithRoles(['super-admin', 'designer']);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['role', 'user' => ['roles']]]);

        $this->assertSame('super-admin', $response->json('data.role'));
        $this->assertSame(
            ['designer', 'super-admin'],
            $response->json('data.user.roles'),
        );
    }

    /**
     * @param array<int, string> $roles
     */
    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);
        $user->assignRole($roles);

        return $user;
    }
}
