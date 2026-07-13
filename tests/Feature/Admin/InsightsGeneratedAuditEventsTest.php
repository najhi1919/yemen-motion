<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InsightsGeneratedAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_super_admin_report_request_records_safe_generated_event_and_keeps_response_shape(): void
    {
        $actor = $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-02 10:00:00']);

        $response = $this
            ->withHeaders([
                'X-Request-ID' => 'reports-generated-request',
                'X-Correlation-ID' => 'reports-generated-correlation',
            ])
            ->getJson('/api/admin/reports/users?from=2026-07-01&to=2026-07-31&role=client&period=month')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary',
                    'role_breakdown',
                    'registrations_series',
                    'filters',
                    'generated_at',
                ],
                'message',
                'errors',
            ]);

        $event = $this->soleEvent('reports.users.generated');

        $this->assertSame('reports', $event->category);
        $this->assertSame('info', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame('super-admin', $event->actor_role);
        $this->assertSame('report', $event->target_type);
        $this->assertNull($event->target_id);
        $this->assertSame('generate', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertSame('reports-generated-request', $event->request_id);
        $this->assertSame('reports-generated-correlation', $event->correlation_id);
        $this->assertSame([
            'source' => 'users_reports_api',
            'period' => 'month',
            'has_from_filter' => true,
            'has_to_filter' => true,
            'has_role_filter' => true,
            'users_in_range' => 1,
            'role_breakdown_count' => 1,
            'series_points_count' => 1,
        ], $event->metadata);
        $this->assertSafeMetadata($event->metadata, ['2026-07-01', '2026-07-31', 'client']);
        $this->assertSame(1, $response->json('data.summary.users_in_range'));
    }

    public function test_denied_and_invalid_report_requests_do_not_record_generated_event(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/admin/reports/users')
            ->assertForbidden();
        $this->assertEventCount('reports.users.generated', 0);

        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/reports/users?period=quarter')
            ->assertUnprocessable();
        $this->assertEventCount('reports.users.generated', 0);
    }

    public function test_super_admin_analytics_request_records_safe_generated_event_and_keeps_response_shape(): void
    {
        $actor = $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-08 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-10 10:00:00']);

        $response = $this
            ->withHeaders([
                'X-Request-ID' => 'analytics-generated-request',
                'X-Correlation-ID' => 'analytics-generated-correlation',
            ])
            ->getJson('/api/admin/analytics/users?from=2026-07-10&to=2026-07-11&role=client&period=day')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary',
                    'trend',
                    'role_mix',
                    'comparison',
                    'filters',
                    'generated_at',
                ],
                'message',
                'errors',
            ]);

        $event = $this->soleEvent('analytics.users.generated');

        $this->assertSame('analytics', $event->category);
        $this->assertSame('info', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame('super-admin', $event->actor_role);
        $this->assertSame('analytics', $event->target_type);
        $this->assertNull($event->target_id);
        $this->assertSame('generate', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertSame('analytics-generated-request', $event->request_id);
        $this->assertSame('analytics-generated-correlation', $event->correlation_id);
        $this->assertSame([
            'source' => 'users_analytics_api',
            'period' => 'day',
            'has_from_filter' => true,
            'has_to_filter' => true,
            'has_role_filter' => true,
            'current_period_users' => 1,
            'previous_period_users' => 1,
            'absolute_change' => 0,
            'percentage_change_available' => true,
            'trend_points_count' => 1,
            'role_mix_count' => 1,
        ], $event->metadata);
        $this->assertSafeMetadata($event->metadata, ['2026-07-10', '2026-07-11', 'client']);
        $this->assertSame(1, $response->json('data.summary.current_period_users'));
    }

    public function test_denied_and_invalid_analytics_requests_do_not_record_generated_event(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/admin/analytics/users')
            ->assertForbidden();
        $this->assertEventCount('analytics.users.generated', 0);

        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/analytics/users?period=quarter')
            ->assertUnprocessable();
        $this->assertEventCount('analytics.users.generated', 0);
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<string> $rawValues
     */
    private function assertSafeMetadata(array $metadata, array $rawValues): void
    {
        foreach ([
            'from',
            'to',
            'role',
            'email',
            'name',
            'password',
            'token',
            'cookie',
            'query',
            'query_string',
            'full_url',
            'payload',
            'request',
            'raw_request',
            'users',
        ] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $metadata);
        }

        $encoded = json_encode($metadata, JSON_THROW_ON_ERROR);

        foreach ($rawValues as $rawValue) {
            $this->assertStringNotContainsString($rawValue, $encoded);
        }
    }

    private function soleEvent(string $eventType): AuditEvent
    {
        return AuditEvent::query()
            ->where('event_type', $eventType)
            ->sole();
    }

    private function assertEventCount(string $eventType, int $expected): void
    {
        $this->assertSame(
            $expected,
            AuditEvent::query()->where('event_type', $eventType)->count(),
        );
    }

    private function actingAsRole(string $role, array $attributes = []): User
    {
        $user = $this->userWithRole($role, $attributes);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
