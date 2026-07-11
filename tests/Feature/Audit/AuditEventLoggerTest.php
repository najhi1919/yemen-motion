<?php

namespace Tests\Feature\Audit;

use App\Services\Audit\AuditEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use stdClass;
use Tests\TestCase;

class AuditEventLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_a_basic_audit_event_and_safe_metadata(): void
    {
        $occurredAt = Carbon::parse('2026-07-11 10:30:00');

        $event = app(AuditEventLogger::class)->record([
            'event_type' => 'user.role.assigned',
            'category' => 'roles',
            'severity' => 'notice',
            'actor_type' => 'user',
            'actor_id' => 7,
            'actor_role' => 'super-admin',
            'target_type' => 'user',
            'target_id' => 19,
            'action' => 'assign',
            'outcome' => 'success',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Audit test agent',
            'request_id' => 'request-123',
            'correlation_id' => 'correlation-456',
            'metadata' => [
                'role' => 'staff',
                'route' => 'admin.users.roles.assign',
                'filters' => ['status' => 'active'],
            ],
            'occurred_at' => $occurredAt,
        ]);

        $event->refresh();

        $this->assertSame('user.role.assigned', $event->event_type);
        $this->assertSame('roles', $event->category);
        $this->assertSame('notice', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertEquals(7, $event->actor_id);
        $this->assertSame('super-admin', $event->actor_role);
        $this->assertSame('user', $event->target_type);
        $this->assertEquals(19, $event->target_id);
        $this->assertSame('assign', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertSame('request-123', $event->request_id);
        $this->assertSame('correlation-456', $event->correlation_id);
        $this->assertTrue($event->occurred_at->equalTo($occurredAt));
        $this->assertSame([
            'role' => 'staff',
            'route' => 'admin.users.roles.assign',
            'filters' => ['status' => 'active'],
        ], $event->metadata);

        $this->assertDatabaseHas('audit_events', [
            'id' => $event->id,
            'event_type' => 'user.role.assigned',
            'category' => 'roles',
            'severity' => 'notice',
            'outcome' => 'success',
        ]);
    }

    public function test_it_defaults_outcome_and_occurred_at(): void
    {
        $now = Carbon::parse('2026-07-11 12:00:00');
        Carbon::setTestNow($now);

        try {
            $event = app(AuditEventLogger::class)->record([
                'event_type' => 'admin.page.viewed',
                'category' => 'page_view',
                'severity' => 'info',
            ]);
        } finally {
            Carbon::setTestNow();
        }

        $event->refresh();

        $this->assertSame('success', $event->outcome);
        $this->assertTrue($event->occurred_at->equalTo($now));
    }

    public function test_it_redacts_sensitive_metadata_recursively_and_discards_sensitive_direct_fields(): void
    {
        $event = app(AuditEventLogger::class)->record([
            'event_type' => 'user.login.failed',
            'category' => 'auth',
            'severity' => 'warning',
            'outcome' => 'failed',
            'password' => 'direct-password',
            'access_token' => 'direct-access-token',
            'payload' => ['unsafe' => true],
            'metadata' => [
                'password' => 'plain-password',
                'password_confirmation' => 'confirmed-password',
                'token' => 'plain-token',
                'access_token' => 'access-token-value',
                'refresh_token' => 'refresh-token-value',
                'cookie' => 'session-cookie-value',
                'api_key' => 'api-key-value',
                'secret' => 'secret-value',
                'payload' => [
                    'email' => 'user@example.test',
                    'password' => 'payload-password',
                ],
                'nested' => [
                    'current_password' => 'current-password-value',
                    'credentials' => [
                        'clientSecret' => 'nested-client-secret',
                        'card_number' => '4111111111111111',
                        'cvv' => '123',
                    ],
                ],
                'safe_reason' => 'invalid_credentials',
            ],
        ]);

        $event->refresh();
        $metadata = $event->metadata;

        foreach ([
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'cookie',
            'api_key',
            'secret',
            'payload',
        ] as $key) {
            $this->assertSame('[REDACTED]', $metadata[$key]);
        }

        $this->assertSame('[REDACTED]', $metadata['nested']['current_password']);
        $this->assertSame('[REDACTED]', $metadata['nested']['credentials']['clientSecret']);
        $this->assertSame('[REDACTED]', $metadata['nested']['credentials']['card_number']);
        $this->assertSame('[REDACTED]', $metadata['nested']['credentials']['cvv']);
        $this->assertSame('invalid_credentials', $metadata['safe_reason']);

        $storedMetadata = json_encode($metadata, JSON_THROW_ON_ERROR);

        foreach ([
            'plain-password',
            'confirmed-password',
            'plain-token',
            'access-token-value',
            'refresh-token-value',
            'session-cookie-value',
            'api-key-value',
            'secret-value',
            'payload-password',
            'current-password-value',
            'nested-client-secret',
            '4111111111111111',
        ] as $sensitiveValue) {
            $this->assertStringNotContainsString($sensitiveValue, $storedMetadata);
        }

        $attributes = $event->getAttributes();
        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertArrayNotHasKey('access_token', $attributes);
        $this->assertArrayNotHasKey('payload', $attributes);
    }

    public function test_it_limits_metadata_depth_size_and_supported_value_types(): void
    {
        $deepMetadata = ['visible_at_the_end' => 'must-not-reach-storage'];

        for ($level = 0; $level < 7; $level++) {
            $deepMetadata = ["level_{$level}" => $deepMetadata];
        }

        $event = app(AuditEventLogger::class)->record([
            'event_type' => 'dashboard.search.performed',
            'category' => 'dashboard',
            'severity' => 'info',
            'metadata' => [
                'long_value' => str_repeat('a', 1500),
                'deep' => $deepMetadata,
                'unsupported_object' => new stdClass(),
                'safe_count' => 3,
                'safe_flag' => true,
            ],
        ]);

        $event->refresh();

        $this->assertLessThanOrEqual(1000, strlen($event->metadata['long_value']));
        $this->assertStringNotContainsString(
            'must-not-reach-storage',
            json_encode($event->metadata['deep'], JSON_THROW_ON_ERROR),
        );
        $this->assertNull($event->metadata['unsupported_object']);
        $this->assertSame(3, $event->metadata['safe_count']);
        $this->assertTrue($event->metadata['safe_flag']);
    }
}
