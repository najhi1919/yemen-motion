<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PageViewAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_super_admin_can_record_an_admin_page_view(): void
    {
        $superAdmin = $this->userWithRole('super-admin', [
            'name' => 'Page View Auditor',
            'email' => 'page-view-auditor@example.com',
        ]);
        Sanctum::actingAs($superAdmin, ['*']);

        $this->withHeaders([
            'User-Agent' => 'Page view audit test agent',
            'X-Request-ID' => 'page-view-request-123',
            'X-Correlation-ID' => 'page-view-correlation-456',
        ])->postJson('/api/audit/page-view', [
            'page_key' => 'admin.users.index',
            'path' => '/admin/users',
            'section' => 'users',
        ])->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => null,
                'message' => 'تم تسجيل زيارة الصفحة بنجاح',
                'errors' => null,
            ]);

        $event = $this->solePageViewEvent();

        $this->assertSame('admin.page.viewed', $event->event_type);
        $this->assertSame('page_view', $event->category);
        $this->assertSame('info', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertEquals($superAdmin->id, $event->actor_id);
        $this->assertSame('super-admin', $event->actor_role);
        $this->assertSame('page', $event->target_type);
        $this->assertNull($event->target_id);
        $this->assertSame('view', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertNotNull($event->ip_address);
        $this->assertSame('Page view audit test agent', $event->user_agent);
        $this->assertSame('page-view-request-123', $event->request_id);
        $this->assertSame('page-view-correlation-456', $event->correlation_id);
        $this->assertSame([
            'page_key' => 'admin.users.index',
            'path' => '/admin/users',
            'source' => 'admin_page_view_tracking',
            'section' => 'users',
        ], $event->metadata);
        $this->assertSafeMetadata($event, $superAdmin);
    }

    public function test_admin_can_record_an_admin_page_view(): void
    {
        $admin = $this->userWithRole('admin');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/audit/page-view', $this->validPayload())
            ->assertCreated();

        $event = $this->solePageViewEvent();

        $this->assertEquals($admin->id, $event->actor_id);
        $this->assertSame('admin', $event->actor_role);
    }

    public function test_staff_can_record_an_admin_page_view(): void
    {
        $staff = $this->userWithRole('staff');
        Sanctum::actingAs($staff, ['*']);

        $this->postJson('/api/audit/page-view', $this->validPayload([
            'page_key' => 'staff.workspace',
            'path' => '/staff/workspace',
            'section' => null,
        ]))->assertCreated();

        $event = $this->solePageViewEvent();

        $this->assertEquals($staff->id, $event->actor_id);
        $this->assertSame('staff', $event->actor_role);
        $this->assertSame('/staff/workspace', $event->metadata['path']);
        $this->assertArrayNotHasKey('section', $event->metadata);
    }

    public function test_client_is_forbidden_even_with_accidental_admin_permissions(): void
    {
        $client = $this->externalUserWithAccidentalPermissions('client');
        Sanctum::actingAs($client, ['*']);

        $this->postJson('/api/audit/page-view', $this->validPayload())
            ->assertForbidden();

        $this->assertSame(0, $this->pageViewEventCount());
    }

    public function test_designer_is_forbidden_even_with_accidental_admin_permissions(): void
    {
        $designer = $this->externalUserWithAccidentalPermissions('designer');
        Sanctum::actingAs($designer, ['*']);

        $this->postJson('/api/audit/page-view', $this->validPayload())
            ->assertForbidden();

        $this->assertSame(0, $this->pageViewEventCount());
    }

    public function test_unauthenticated_request_is_rejected_without_an_event(): void
    {
        $this->postJson('/api/audit/page-view', $this->validPayload())
            ->assertUnauthorized();

        $this->assertSame(0, $this->pageViewEventCount());
    }

    public function test_non_admin_or_staff_path_is_rejected_without_an_event(): void
    {
        $this->actingAsInternalUser();

        $this->postJson('/api/audit/page-view', $this->validPayload([
            'path' => '/dashboard',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['path']);

        $this->assertSame(0, $this->pageViewEventCount());
    }

    public function test_full_url_is_rejected_without_an_event(): void
    {
        $this->actingAsInternalUser();

        $this->postJson('/api/audit/page-view', $this->validPayload([
            'path' => 'https://example.com/admin/users',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['path']);

        $this->assertSame(0, $this->pageViewEventCount());
    }

    public function test_path_with_query_string_is_rejected_without_an_event(): void
    {
        $this->actingAsInternalUser();

        $this->postJson('/api/audit/page-view', $this->validPayload([
            'path' => '/admin/users?email=hidden@example.com',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['path']);

        $this->assertSame(0, $this->pageViewEventCount());
    }

    public function test_validation_failure_does_not_record_an_event(): void
    {
        $this->actingAsInternalUser();

        $this->postJson('/api/audit/page-view', [
            'path' => '/admin/users',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['page_key']);

        $this->assertSame(0, $this->pageViewEventCount());
    }

    public function test_arbitrary_metadata_referrer_and_payload_fields_are_rejected(): void
    {
        $this->actingAsInternalUser();

        $this->postJson('/api/audit/page-view', [
            ...$this->validPayload(),
            'metadata' => ['email' => 'hidden@example.com'],
            'referrer' => 'https://example.com/private',
            'payload' => ['token' => 'secret-token'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['metadata', 'referrer', 'payload']);

        $this->assertSame(0, $this->pageViewEventCount());
    }

    private function assertSafeMetadata(AuditEvent $event, User $actor): void
    {
        foreach (['email', 'name', 'payload', 'request', 'referrer', 'password', 'token', 'cookie'] as $key) {
            $this->assertArrayNotHasKey($key, $event->metadata);
        }

        $storedMetadata = strtolower(json_encode($event->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString(strtolower($actor->email), $storedMetadata);
        $this->assertStringNotContainsString(strtolower($actor->name), $storedMetadata);
        $this->assertStringNotContainsString('password', $storedMetadata);
        $this->assertStringNotContainsString('token', $storedMetadata);
        $this->assertStringNotContainsString('cookie', $storedMetadata);
    }

    private function solePageViewEvent(): AuditEvent
    {
        return AuditEvent::query()
            ->where('event_type', 'admin.page.viewed')
            ->sole();
    }

    private function pageViewEventCount(): int
    {
        return AuditEvent::query()
            ->where('event_type', 'admin.page.viewed')
            ->count();
    }

    private function actingAsInternalUser(): User
    {
        $user = $this->userWithRole('super-admin');
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function externalUserWithAccidentalPermissions(string $role): User
    {
        $user = $this->userWithRole($role);
        $user->givePermissionTo([
            'dashboard.overview.view',
            'admin.users.view',
        ]);

        return $user;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'page_key' => 'admin.users.index',
            'path' => '/admin/users',
            'section' => 'users',
        ], $overrides);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
