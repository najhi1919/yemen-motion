<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStaffUpdateAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_staff_update_records_safe_changed_field_names_only(): void
    {
        $actor = $this->userWithRole('admin');
        $actor->givePermissionTo('admin.staff.update');
        $target = $this->userWithRole('staff', [
            'name' => 'Old Staff Name',
            'email' => 'old.staff.audit@example.com',
        ]);
        Sanctum::actingAs($actor, ['*']);

        $this->withHeaders([
            'User-Agent' => 'Staff update audit test agent',
            'X-Request-ID' => 'staff-update-request-123',
            'X-Correlation-ID' => 'staff-update-correlation-456',
        ])->patchJson("/api/admin/staff/{$target->id}", [
            'name' => 'New Staff Name',
            'email' => 'new.staff.audit@example.com',
        ])->assertOk();

        $event = AuditEvent::query()
            ->where('event_type', 'staff.updated')
            ->sole();

        $this->assertSame('staff', $event->category);
        $this->assertSame('notice', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertEquals($actor->id, $event->actor_id);
        $this->assertSame('admin', $event->actor_role);
        $this->assertSame('user', $event->target_type);
        $this->assertEquals($target->id, $event->target_id);
        $this->assertSame('update', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertSame('staff-update-request-123', $event->request_id);
        $this->assertSame('staff-update-correlation-456', $event->correlation_id);
        $this->assertSame([
            'changed_fields' => ['name', 'email'],
            'source' => 'admin_staff_update',
        ], $event->metadata);

        $storedMetadata = json_encode($event->metadata, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('Old Staff Name', $storedMetadata);
        $this->assertStringNotContainsString('New Staff Name', $storedMetadata);
        $this->assertStringNotContainsString('old.staff.audit@example.com', $storedMetadata);
        $this->assertStringNotContainsString('new.staff.audit@example.com', $storedMetadata);
        $this->assertStringNotContainsString('password', strtolower($storedMetadata));
        $this->assertStringNotContainsString('payload', strtolower($storedMetadata));
    }

    public function test_no_change_update_does_not_record_staff_updated_event(): void
    {
        $actor = $this->userWithRole('super-admin');
        $target = $this->userWithRole('staff', [
            'name' => 'No Change Staff',
            'email' => 'no.change.staff@example.com',
        ]);
        Sanctum::actingAs($actor, ['*']);

        $this->patchJson("/api/admin/staff/{$target->id}", [
            'name' => 'No Change Staff',
            'email' => 'no.change.staff@example.com',
        ])->assertOk();

        $this->assertSame(
            0,
            AuditEvent::query()->where('event_type', 'staff.updated')->count(),
        );
    }

    public function test_validation_failure_does_not_record_staff_updated_event(): void
    {
        $actor = $this->userWithRole('super-admin');
        $target = $this->userWithRole('staff');
        Sanctum::actingAs($actor, ['*']);

        $this->patchJson("/api/admin/staff/{$target->id}", [
            'name' => 'Invalid Update',
            'email' => 'not-an-email',
        ])->assertUnprocessable();

        $this->assertSame(
            0,
            AuditEvent::query()->where('event_type', 'staff.updated')->count(),
        );
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
