<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DisabledAccountAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_account_can_use_api_login(): void
    {
        $user = $this->userWithPassword();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_disabled_account_with_correct_password_is_rejected_without_token_or_success_event(): void
    {
        $user = $this->userWithPassword(['disabled_at' => now()]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'هذا الحساب معطل. تواصل مع الإدارة.',
                'data' => null,
                'errors' => null,
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'user.login.success',
            'actor_id' => $user->id,
        ]);
    }

    public function test_disabled_login_records_non_pii_failure_reason(): void
    {
        $user = $this->userWithPassword(['disabled_at' => now()]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertForbidden();

        $event = AuditEvent::query()
            ->where('event_type', 'user.login.failed')
            ->sole();

        $this->assertSame([
            'auth_context' => 'sanctum',
            'reason' => 'account_disabled',
        ], $event->metadata);

        foreach (['name', 'email', 'password', 'token', 'cookie'] as $key) {
            $this->assertArrayNotHasKey($key, $event->metadata);
        }
    }

    public function test_wrong_password_does_not_disclose_disabled_status(): void
    {
        $user = $this->userWithPassword(['disabled_at' => now()]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'بيانات الدخول غير صحيحة.');

        $event = AuditEvent::query()
            ->where('event_type', 'user.login.failed')
            ->sole();

        $this->assertSame('invalid_credentials', $event->metadata['reason']);
    }

    public function test_existing_token_is_rejected_and_deleted_for_disabled_account(): void
    {
        $user = $this->userWithPassword(['disabled_at' => now()]);
        $token = $user->createToken('old-token');

        $this->withToken($token->plainTextToken)
            ->getJson('/api/user')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'هذا الحساب معطل. تواصل مع الإدارة.',
                'data' => null,
                'errors' => null,
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_restored_account_can_login_again(): void
    {
        $user = $this->userWithPassword(['disabled_at' => now()]);
        $user->update(['disabled_at' => null]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertOk();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function userWithPassword(array $attributes = []): User
    {
        return User::factory()->create([
            ...$attributes,
            'password' => Hash::make('correct-password'),
        ]);
    }
}
