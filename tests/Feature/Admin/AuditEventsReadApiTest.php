<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditEventsReadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_cannot_read_audit_events(): void
    {
        $this->getJson('/api/admin/audit-events')
            ->assertUnauthorized();
    }

    public function test_super_admin_can_read_audit_events(): void
    {
        $this->actingAsRole('super-admin');
        $event = $this->createAuditEvent();

        $this->getJson('/api/admin/audit-events')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.id', $event->id)
            ->assertJsonPath('message', 'تم جلب سجلات التدقيق بنجاح');
    }

    public function test_admin_cannot_read_even_with_accidental_permissions(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->givePermissionTo([
            'admin.access.view',
            'admin.users.view',
            'dashboard.overview.view',
        ]);
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/admin/audit-events')
            ->assertForbidden();
    }

    public function test_staff_cannot_read_audit_events(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/admin/audit-events')
            ->assertForbidden();
    }

    public function test_client_cannot_read_audit_events(): void
    {
        $this->actingAsRole('client');

        $this->getJson('/api/admin/audit-events')
            ->assertForbidden();
    }

    public function test_designer_cannot_read_audit_events(): void
    {
        $this->actingAsRole('designer');

        $this->getJson('/api/admin/audit-events')
            ->assertForbidden();
    }

    public function test_response_has_pagination_and_only_explicit_safe_event_fields(): void
    {
        $this->actingAsRole('super-admin');
        $this->createAuditEvent([
            'metadata' => ['source' => 'read_api_test'],
        ]);

        $response = $this->getJson('/api/admin/audit-events')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_page',
                    'data',
                    'first_page_url',
                    'from',
                    'last_page',
                    'last_page_url',
                    'links',
                    'next_page_url',
                    'path',
                    'per_page',
                    'prev_page_url',
                    'to',
                    'total',
                ],
                'message',
                'errors',
            ]);

        $item = $response->json('data.data.0');

        $this->assertSame([
            'id',
            'event_type',
            'category',
            'severity',
            'actor_type',
            'actor_id',
            'actor_role',
            'target_type',
            'target_id',
            'action',
            'outcome',
            'ip_address',
            'user_agent',
            'request_id',
            'correlation_id',
            'metadata',
            'occurred_at',
            'created_at',
        ], array_keys($item));
        $this->assertSame(['source' => 'read_api_test'], $item['metadata']);
        $this->assertArrayNotHasKey('updated_at', $item);
        $this->assertArrayNotHasKey('user', $item);
        $this->assertArrayNotHasKey('actor', $item);
        $this->assertArrayNotHasKey('target', $item);
    }

    public function test_events_are_ordered_by_latest_occurred_at_then_latest_id(): void
    {
        $this->actingAsRole('super-admin');
        $older = $this->createAuditEvent(['occurred_at' => now()->subHours(2)]);
        $tieTime = now()->subHour();
        $firstTie = $this->createAuditEvent(['occurred_at' => $tieTime]);
        $secondTie = $this->createAuditEvent(['occurred_at' => $tieTime]);
        $newest = $this->createAuditEvent(['occurred_at' => now()]);

        $response = $this->getJson('/api/admin/audit-events')
            ->assertOk();

        $this->assertSame([
            $newest->id,
            $secondTie->id,
            $firstTie->id,
            $older->id,
        ], collect($response->json('data.data'))->pluck('id')->all());
    }

    public function test_event_type_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $matching = $this->createAuditEvent(['event_type' => 'role.created']);
        $this->createAuditEvent(['event_type' => 'permission.created']);

        $response = $this->getJson('/api/admin/audit-events?event_type=role.created')
            ->assertOk();

        $this->assertSame([$matching->id], collect($response->json('data.data'))->pluck('id')->all());
    }

    public function test_category_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $matching = $this->createAuditEvent(['category' => 'roles']);
        $this->createAuditEvent(['category' => 'permissions']);

        $response = $this->getJson('/api/admin/audit-events?category=roles')
            ->assertOk();

        $this->assertSame([$matching->id], collect($response->json('data.data'))->pluck('id')->all());
    }

    public function test_severity_and_outcome_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        $matching = $this->createAuditEvent([
            'severity' => 'warning',
            'outcome' => 'failed',
        ]);
        $this->createAuditEvent([
            'severity' => 'info',
            'outcome' => 'success',
        ]);

        $response = $this->getJson('/api/admin/audit-events?severity=warning&outcome=failed')
            ->assertOk();

        $this->assertSame([$matching->id], collect($response->json('data.data'))->pluck('id')->all());
    }

    public function test_actor_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $matching = $this->createAuditEvent(['actor_id' => 101]);
        $this->createAuditEvent(['actor_id' => 202]);

        $response = $this->getJson('/api/admin/audit-events?actor_id=101')
            ->assertOk();

        $this->assertSame([$matching->id], collect($response->json('data.data'))->pluck('id')->all());
    }

    public function test_target_type_and_target_id_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        $matching = $this->createAuditEvent([
            'target_type' => 'role',
            'target_id' => 303,
        ]);
        $this->createAuditEvent([
            'target_type' => 'user',
            'target_id' => 303,
        ]);
        $this->createAuditEvent([
            'target_type' => 'role',
            'target_id' => 404,
        ]);

        $response = $this->getJson('/api/admin/audit-events?target_type=role&target_id=303')
            ->assertOk();

        $this->assertSame([$matching->id], collect($response->json('data.data'))->pluck('id')->all());
    }

    public function test_from_and_to_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        $this->createAuditEvent(['occurred_at' => '2026-07-10 10:00:00']);
        $matching = $this->createAuditEvent(['occurred_at' => '2026-07-11 10:00:00']);
        $this->createAuditEvent(['occurred_at' => '2026-07-12 10:00:00']);

        $response = $this->getJson('/api/admin/audit-events?from=2026-07-11&to=2026-07-11')
            ->assertOk();

        $this->assertSame([$matching->id], collect($response->json('data.data'))->pluck('id')->all());
    }

    public function test_per_page_respects_the_maximum_and_page_navigation(): void
    {
        $this->actingAsRole('super-admin');

        foreach (range(1, 55) as $index) {
            $this->createAuditEvent(['event_type' => "test.event.{$index}"]);
        }

        $this->getJson('/api/admin/audit-events?per_page=50')
            ->assertOk()
            ->assertJsonCount(50, 'data.data')
            ->assertJsonPath('data.per_page', 50)
            ->assertJsonPath('data.total', 55);

        $this->getJson('/api/admin/audit-events?per_page=50&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data.data')
            ->assertJsonPath('data.current_page', 2);

        $this->getJson('/api/admin/audit-events?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_unknown_query_parameter_returns_validation_error(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/audit-events?sort=asc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_sensitive_and_arbitrary_search_parameters_are_rejected(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/audit-events?email=x&name=x&payload=x&request=x&token=x&password=x&cookie=x&metadata=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'name',
                'payload',
                'request',
                'token',
                'password',
                'cookie',
                'metadata',
            ]);
    }

    private function actingAsRole(string $role): User
    {
        $user = $this->userWithRole($role);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function createAuditEvent(array $overrides = []): AuditEvent
    {
        return AuditEvent::query()->create(array_merge([
            'event_type' => 'test.event',
            'category' => 'test',
            'severity' => 'info',
            'actor_type' => 'user',
            'actor_id' => 1,
            'actor_role' => 'super-admin',
            'target_type' => 'user',
            'target_id' => 2,
            'action' => 'test',
            'outcome' => 'success',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Audit read API test agent',
            'request_id' => 'audit-read-request',
            'correlation_id' => 'audit-read-correlation',
            'metadata' => ['source' => 'read_api_test'],
            'occurred_at' => now(),
        ], $overrides));
    }
}
