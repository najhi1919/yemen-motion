<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardSearchAuditEventsTest extends TestCase
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

        foreach ([
            'dashboard.overview.view',
            'admin.users.view',
            'admin.roles.view',
            'admin.permissions.view',
        ] as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_super_admin_search_records_a_safe_audit_event(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        $this->createMatchingFixtures();
        Sanctum::actingAs($superAdmin, ['*']);

        $this->withHeaders([
            'User-Agent' => 'Dashboard search audit test agent',
            'X-Request-ID' => 'dashboard-search-request-123',
            'X-Correlation-ID' => 'dashboard-search-correlation-456',
        ])->getJson('/api/dashboard/search?q=auditneedle')
            ->assertOk();

        $event = AuditEvent::query()
            ->where('event_type', 'dashboard.search.performed')
            ->sole();

        $this->assertSame('dashboard', $event->category);
        $this->assertSame('info', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertEquals($superAdmin->id, $event->actor_id);
        $this->assertSame('super-admin', $event->actor_role);
        $this->assertSame('search', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertSame('Dashboard search audit test agent', $event->user_agent);
        $this->assertSame('dashboard-search-request-123', $event->request_id);
        $this->assertSame('dashboard-search-correlation-456', $event->correlation_id);
        $this->assertSame([
            'source' => 'dashboard_search',
            'has_query' => true,
            'query_length' => 11,
            'result_count' => 5,
            'result_sections' => ['users', 'staff', 'roles', 'permissions'],
        ], $event->metadata);

        $this->assertArrayNotHasKey('query', $event->metadata);
        $this->assertArrayNotHasKey('email', $event->metadata);
        $this->assertArrayNotHasKey('name', $event->metadata);
        $this->assertArrayNotHasKey('payload', $event->metadata);
        $this->assertArrayNotHasKey('results', $event->metadata);

        $storedMetadata = json_encode($event->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('auditneedle', strtolower($storedMetadata));
        $this->assertStringNotContainsString('auditneedle.staff@example.com', strtolower($storedMetadata));
        $this->assertStringNotContainsString('Auditneedle Staff', $storedMetadata);
    }

    public function test_authorized_staff_search_records_actor_id_and_role(): void
    {
        $staff = $this->userWithRole('staff');
        $staff->givePermissionTo('dashboard.overview.view');
        Sanctum::actingAs($staff, ['*']);

        $this->getJson('/api/dashboard/search?q=noresults')
            ->assertOk();

        $event = AuditEvent::query()
            ->where('event_type', 'dashboard.search.performed')
            ->sole();

        $this->assertEquals($staff->id, $event->actor_id);
        $this->assertSame('staff', $event->actor_role);
        $this->assertSame('dashboard_search', $event->metadata['source']);
        $this->assertTrue($event->metadata['has_query']);
        $this->assertSame(9, $event->metadata['query_length']);
        $this->assertSame(0, $event->metadata['result_count']);
        $this->assertSame([], $event->metadata['result_sections']);
    }

    public function test_client_and_designer_denials_do_not_record_search_events(): void
    {
        foreach (['client', 'designer'] as $roleName) {
            $user = $this->userWithRole($roleName);
            $user->givePermissionTo([
                'dashboard.overview.view',
                'admin.users.view',
            ]);
            Sanctum::actingAs($user, ['*']);

            $this->getJson('/api/dashboard/search?q=deniedsearch')
                ->assertForbidden();
        }

        $this->assertSame(0, $this->searchAuditEventCount());
    }

    public function test_validation_failures_and_short_queries_do_not_record_search_events(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $this->getJson('/api/dashboard/search')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);

        $this->getJson('/api/dashboard/search?q=a')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);

        $this->assertSame(0, $this->searchAuditEventCount());
    }

    public function test_unauthenticated_search_does_not_record_search_events(): void
    {
        $this->getJson('/api/dashboard/search?q=auditneedle')
            ->assertUnauthorized();

        $this->assertSame(0, $this->searchAuditEventCount());
    }

    private function createMatchingFixtures(): void
    {
        $staff = User::factory()->create([
            'name' => 'Auditneedle Staff',
            'email' => 'auditneedle.staff@example.com',
        ]);
        $staff->assignRole('staff');

        User::factory()->create([
            'name' => 'Auditneedle Customer',
            'email' => 'auditneedle.customer@example.com',
        ]);

        Role::create([
            'name' => 'auditneedle-reviewer',
            'guard_name' => 'web',
        ]);

        Permission::create([
            'name' => 'auditneedle.permission.view',
            'guard_name' => 'web',
        ]);
    }

    private function searchAuditEventCount(): int
    {
        return AuditEvent::query()
            ->where('event_type', 'dashboard.search.performed')
            ->count();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
