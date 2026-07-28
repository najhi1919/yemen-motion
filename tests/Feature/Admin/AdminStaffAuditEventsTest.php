<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStaffAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_staff_creation_records_a_safe_audit_event_with_actor_and_target(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $response = $this->withHeaders([
            'User-Agent' => 'Staff audit test agent',
            'X-Request-ID' => 'staff-request-123',
            'X-Correlation-ID' => 'staff-correlation-456',
        ])->postJson('/api/admin/staff', $this->validPayload([
            'name' => 'Audited Staff',
            'email' => 'audited.staff@example.com',
        ]))->assertCreated();

        $createdUser = User::findOrFail($response->json('data.user.id'));
        $event = AuditEvent::query()
            ->where('event_type', 'staff.created')
            ->sole();

        $this->assertSame('staff', $event->category);
        $this->assertSame('notice', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertEquals($superAdmin->id, $event->actor_id);
        $this->assertSame('super-admin', $event->actor_role);
        $this->assertSame('user', $event->target_type);
        $this->assertEquals($createdUser->id, $event->target_id);
        $this->assertSame('create', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertSame('staff-request-123', $event->request_id);
        $this->assertSame('staff-correlation-456', $event->correlation_id);
        $this->assertSame([
            'assigned_role' => 'staff',
            'created_user_role' => 'staff',
            'source' => 'admin_staff_create',
        ], $event->metadata);

        $storedMetadata = json_encode($event->metadata, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('password-secret', $storedMetadata);
        $this->assertStringNotContainsString('audited.staff@example.com', $storedMetadata);
        $this->assertStringNotContainsString('password', strtolower($storedMetadata));
        $this->assertStringNotContainsString('email', strtolower($storedMetadata));
        $this->assertStringNotContainsString('payload', strtolower($storedMetadata));
    }

    public function test_delegated_creator_is_recorded_as_the_actor(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->givePermissionTo('admin.staff.create');
        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson('/api/admin/staff', $this->validPayload([
            'email' => 'delegated.audit@example.com',
            'role' => 'staff',
        ]))->assertCreated();

        $event = AuditEvent::query()
            ->where('event_type', 'staff.created')
            ->sole();

        $this->assertEquals($admin->id, $event->actor_id);
        $this->assertSame('admin', $event->actor_role);
        $this->assertEquals($response->json('data.user.id'), $event->target_id);
        $this->assertSame('staff', $event->metadata['assigned_role']);
    }

    public function test_validation_failure_does_not_record_staff_created(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        $this->postJson('/api/admin/staff', $this->validPayload([
            'role' => 'super-admin',
        ]))->assertUnprocessable();

        $this->assertSame(
            0,
            AuditEvent::query()->where('event_type', 'staff.created')->count(),
        );
    }

    public function test_unauthorized_attempt_does_not_record_staff_created(): void
    {
        $admin = $this->userWithRole('admin');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/admin/staff', $this->validPayload())
            ->assertForbidden();

        $this->assertSame(
            0,
            AuditEvent::query()->where('event_type', 'staff.created')->count(),
        );
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Staff',
            'email' => 'new.staff@example.com',
            'password' => 'password-secret',
            'password_confirmation' => 'password-secret',
            'role' => 'staff',
        ], $overrides);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
