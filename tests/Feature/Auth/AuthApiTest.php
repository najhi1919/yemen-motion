<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'designer', 'guard_name' => 'web']);
    }

    public function test_register_returns_user_token_and_role(): void
    {
        $data = [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'client',
        ];

        $response = $this->postJson('/api/auth/register', $data);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data',
            'errors',
        ]);
        $response->assertJson([
            'success' => true,
            'message' => 'User registered successfully',
            'errors' => null,
        ]);
        $response->assertJson(['data' => ['user' => ['email' => 'client@example.com']]]);
        $response->assertJson(['data' => ['role' => 'client']]);
        $this->assertNotEmpty($response->json('data.token'));

        $this->assertDatabaseHas('users', ['email' => 'client@example.com']);
        $user = User::where('email', 'client@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('client'));
    }

    public function test_register_role_designer(): void
    {
        $data = [
            'name' => 'Designer Test',
            'email' => 'designer@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'designer',
        ];

        $response = $this->postJson('/api/auth/register', $data);
        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'designer@example.com']);
        $user = User::where('email', 'designer@example.com')->first();
        $this->assertTrue($user->hasRole('designer'));
    }

    public function test_login_returns_user_token_and_role(): void
    {
        $user = User::factory()->create([
            'email' => 'loginuser@example.com',
        ]);
        $user->password = 'password123';
        $user->save();
        $user->assignRole('client');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'loginuser@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data',
            'errors',
        ]);
        $response->assertJson(['success' => true]);
        $response->assertJson(['data' => ['user' => ['email' => 'loginuser@example.com']]]);
        $response->assertJson(['data' => ['role' => 'client']]);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_wrong_login_returns_401(): void
    {
        User::factory()->create([
            'email' => 'wronglogin@example.com',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'wronglogin@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    public function test_get_user_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/user');
        $response->assertStatus(401);
    }

    public function test_get_user_with_token_returns_user_role_and_permissions(): void
    {
        $user = User::factory()->create([
            'email' => 'usertest@example.com',
        ]);
        $user->password = 'Password123!';
        $user->save();
        $user->assignRole('client');
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.user.email', 'usertest@example.com');
        $this->assertEquals('client', $response->json('data.role'));
    }

    public function test_logout_invalidates_current_token(): void
    {
        $user = User::factory()->create([
            'email' => 'logoutuser@example.com',
        ]);
        $user->password = 'Password123!';
        $user->save();
        $user->assignRole('designer');
        $token = $user->createToken('auth-token')->plainTextToken;
        $originalTokenId = $user->tokens()->latest()->first()->id;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals(0, $user->tokens()->where('id', $originalTokenId)->count());
    }

    public function test_after_logout_same_token_cannot_access_user_endpoint(): void
    {
        $user = User::factory()->create([
            'email' => 'reuseuser@example.com',
        ]);
        $user->password = 'Password123!';
        $user->save();
        $user->assignRole('client');
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/auth/logout');

        // Forget auth guards so the next request re-checks the token
        $this->app['auth']->forgetGuards();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/user');
        $response->assertStatus(401);
    }
}
