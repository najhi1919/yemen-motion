<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessDeniedAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_admin_request_records_one_safe_guest_denial_and_keeps_401_response(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer secret-access-token',
            'Cookie' => 'session=secret-cookie',
            'Referer' => 'https://example.com/private',
            'User-Agent' => 'Access denied audit test agent',
            'X-Request-ID' => 'denied-request-123',
            'X-Correlation-ID' => 'denied-correlation-456',
        ])->getJson('/api/admin/users?email=hidden@example.com&token=query-secret');

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'غير مصادق عليه.',
                'data' => null,
                'errors' => null,
            ]);

        $event = $this->soleAccessDeniedEvent();

        $this->assertSame('access.denied', $event->event_type);
        $this->assertSame('access_control', $event->category);
        $this->assertSame('warning', $event->severity);
        $this->assertSame('guest', $event->actor_type);
        $this->assertNull($event->actor_id);
        $this->assertNull($event->actor_role);
        $this->assertSame('route', $event->target_type);
        $this->assertNull($event->target_id);
        $this->assertSame('access_denied', $event->action);
        $this->assertSame('denied', $event->outcome);
        $this->assertSame('Access denied audit test agent', $event->user_agent);
        $this->assertSame('denied-request-123', $event->request_id);
        $this->assertSame('denied-correlation-456', $event->correlation_id);
        $this->assertSame([
            'method' => 'GET',
            'path' => '/api/admin/users',
            'status' => 401,
            'source' => 'access_denied_tracking',
        ], $event->metadata);
        $this->assertSame(1, $this->accessDeniedEventCount());
        $this->assertSafeMetadata($event);
    }

    public function test_admin_without_permission_records_user_denial_and_keeps_403_response(): void
    {
        $admin = $this->actingAsRole('admin');

        $this->postJson('/api/admin/staff', [
            'name' => 'Hidden Staff Name',
            'email' => 'hidden.staff@example.com',
            'password' => 'hidden-password',
            'password_confirmation' => 'hidden-password',
            'role' => 'staff',
        ])->assertForbidden();

        $event = $this->soleAccessDeniedEvent();

        $this->assertSame('user', $event->actor_type);
        $this->assertEquals($admin->id, $event->actor_id);
        $this->assertSame('admin', $event->actor_role);
        $this->assertSame(403, $event->metadata['status']);
        $this->assertSame('/api/admin/staff', $event->metadata['path']);
        $this->assertSafeMetadata($event);
    }

    public function test_staff_client_and_designer_denials_on_internal_admin_routes_are_recorded(): void
    {
        foreach (['staff', 'client', 'designer'] as $role) {
            $user = $this->actingAsRole($role);

            $this->getJson('/api/admin/roles')
                ->assertForbidden();

            $event = AuditEvent::query()
                ->where('event_type', 'access.denied')
                ->where('actor_id', $user->id)
                ->sole();

            $this->assertSame($role, $event->actor_role);
            $this->assertSame(403, $event->metadata['status']);
        }

        $this->assertSame(3, $this->accessDeniedEventCount());
    }

    public function test_dashboard_403_denial_is_recorded(): void
    {
        $staff = $this->actingAsRole('staff');

        $this->getJson('/api/dashboard/stats')
            ->assertForbidden();

        $event = $this->soleAccessDeniedEvent();

        $this->assertEquals($staff->id, $event->actor_id);
        $this->assertSame('/api/dashboard/stats', $event->metadata['path']);
        $this->assertSame(403, $event->metadata['status']);
    }

    public function test_unauthenticated_dashboard_denial_is_recorded(): void
    {
        $this->getJson('/api/dashboard/overview')
            ->assertUnauthorized();

        $event = $this->soleAccessDeniedEvent();

        $this->assertSame('guest', $event->actor_type);
        $this->assertSame('/api/dashboard/overview', $event->metadata['path']);
        $this->assertSame(401, $event->metadata['status']);
    }

    public function test_client_and_designer_page_view_denials_are_recorded_even_with_accidental_permissions(): void
    {
        foreach (['client', 'designer'] as $role) {
            $user = $this->actingAsRole($role);
            $user->givePermissionTo([
                'dashboard.overview.view',
                'admin.users.view',
            ]);

            $this->postJson('/api/audit/page-view', [
                'page_key' => 'admin.users.index',
                'path' => '/admin/users',
                'section' => 'users',
            ])->assertForbidden();

            $event = AuditEvent::query()
                ->where('event_type', 'access.denied')
                ->where('actor_id', $user->id)
                ->sole();

            $this->assertSame($role, $event->actor_role);
            $this->assertSame('/api/audit/page-view', $event->metadata['path']);
            $this->assertSame(403, $event->metadata['status']);
        }

        $this->assertSame(2, $this->accessDeniedEventCount());
    }

    public function test_unauthenticated_logout_denial_is_recorded(): void
    {
        $this->postJson('/api/auth/logout')
            ->assertUnauthorized();

        $event = $this->soleAccessDeniedEvent();

        $this->assertSame('POST', $event->metadata['method']);
        $this->assertSame('/api/auth/logout', $event->metadata['path']);
        $this->assertSame(401, $event->metadata['status']);
    }

    public function test_public_auth_endpoint_errors_do_not_record_access_denied_events(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable();

        $this->postJson('/api/auth/forgot-password', [])
            ->assertUnprocessable();

        $this->postJson('/api/auth/reset-password', [])
            ->assertUnprocessable();

        $this->assertSame(0, $this->accessDeniedEventCount());
    }

    private function assertSafeMetadata(AuditEvent $event): void
    {
        foreach ([
            'full_url',
            'query',
            'query_string',
            'referrer',
            'payload',
            'request',
            'request_body',
            'response_body',
            'headers',
            'authorization',
            'cookie',
            'token',
            'password',
            'email',
            'name',
        ] as $key) {
            $this->assertArrayNotHasKey($key, $event->metadata);
        }

        $storedMetadata = strtolower(json_encode($event->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('hidden@example.com', $storedMetadata);
        $this->assertStringNotContainsString('query-secret', $storedMetadata);
        $this->assertStringNotContainsString('secret-access-token', $storedMetadata);
        $this->assertStringNotContainsString('secret-cookie', $storedMetadata);
        $this->assertStringNotContainsString('example.com/private', $storedMetadata);
        $this->assertStringNotContainsString('hidden staff name', $storedMetadata);
        $this->assertStringNotContainsString('hidden.staff@example.com', $storedMetadata);
        $this->assertStringNotContainsString('hidden-password', $storedMetadata);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function soleAccessDeniedEvent(): AuditEvent
    {
        return AuditEvent::query()
            ->where('event_type', 'access.denied')
            ->sole();
    }

    private function accessDeniedEventCount(): int
    {
        return AuditEvent::query()
            ->where('event_type', 'access.denied')
            ->count();
    }
}
