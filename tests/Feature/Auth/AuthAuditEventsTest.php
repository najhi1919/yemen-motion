<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'designer', 'guard_name' => 'web']);
    }

    public function test_successful_login_records_a_safe_audit_event_with_actor(): void
    {
        $user = User::factory()->create([
            'email' => 'audit-login-success@example.com',
        ]);
        $user->password = 'Password123!';
        $user->save();
        $user->assignRole('client');

        $this->withHeaders([
            'User-Agent' => 'Auth audit test agent',
            'X-Request-ID' => 'login-request-123',
        ])->postJson('/api/auth/login', [
            'email' => 'audit-login-success@example.com',
            'password' => 'Password123!',
        ])->assertStatus(200);

        $event = AuditEvent::query()
            ->where('event_type', 'user.login.success')
            ->sole();

        $this->assertSame('auth', $event->category);
        $this->assertSame('info', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertEquals($user->id, $event->actor_id);
        $this->assertSame('client', $event->actor_role);
        $this->assertSame('login', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertSame('login-request-123', $event->request_id);
        $this->assertSame(['auth_context' => 'sanctum'], $event->metadata);

        $storedMetadata = json_encode($event->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('audit-login-success@example.com', $storedMetadata);
        $this->assertStringNotContainsString('Password123!', $storedMetadata);
        $this->assertStringNotContainsString('token', strtolower($storedMetadata));
    }

    public function test_failed_login_records_the_same_safe_metadata_for_existing_and_missing_accounts(): void
    {
        $user = User::factory()->create([
            'email' => 'audit-login-failed@example.com',
        ]);
        $user->password = 'Password123!';
        $user->save();

        $this->postJson('/api/auth/login', [
            'email' => 'audit-login-failed@example.com',
            'password' => 'wrong-password-for-existing-user',
        ])->assertStatus(401);

        $this->postJson('/api/auth/login', [
            'email' => 'missing-audit-user@example.com',
            'password' => 'wrong-password-for-missing-user',
        ])->assertStatus(401);

        $events = AuditEvent::query()
            ->where('event_type', 'user.login.failed')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $events);

        foreach ($events as $event) {
            $this->assertSame('auth', $event->category);
            $this->assertSame('warning', $event->severity);
            $this->assertSame('guest', $event->actor_type);
            $this->assertNull($event->actor_id);
            $this->assertSame('login', $event->action);
            $this->assertSame('failed', $event->outcome);
            $this->assertSame([
                'auth_context' => 'sanctum',
                'reason' => 'invalid_credentials',
                'has_identifier' => true,
            ], $event->metadata);

            $storedMetadata = json_encode($event->metadata, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('password', strtolower($storedMetadata));
            $this->assertStringNotContainsString('email', strtolower($storedMetadata));
            $this->assertStringNotContainsString('account_exists', strtolower($storedMetadata));
            $this->assertStringNotContainsString('user_exists', strtolower($storedMetadata));
        }

        $this->assertSame($events[0]->metadata, $events[1]->metadata);
    }

    public function test_logout_records_a_safe_audit_event_with_actor_before_token_deletion(): void
    {
        $user = User::factory()->create([
            'email' => 'audit-logout@example.com',
        ]);
        $user->password = 'Password123!';
        $user->save();
        $user->assignRole('designer');
        $token = $user->createToken('auth-token')->plainTextToken;
        $tokenId = $user->tokens()->latest()->firstOrFail()->id;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        $event = AuditEvent::query()
            ->where('event_type', 'user.logout')
            ->sole();

        $this->assertSame('auth', $event->category);
        $this->assertSame('info', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertEquals($user->id, $event->actor_id);
        $this->assertSame('designer', $event->actor_role);
        $this->assertSame('logout', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertSame(['auth_context' => 'sanctum'], $event->metadata);
        $this->assertSame(0, $user->tokens()->whereKey($tokenId)->count());
    }
}
