<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStaffRolesAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_successful_sync_records_safe_role_difference_metadata(): void
    {
        $actor = $this->userWithRole('super-admin');
        $target = $this->userWithRole('staff');
        Sanctum::actingAs($actor);

        $this->putJson("/api/admin/staff/{$target->id}/roles", [
            'roles' => ['admin'],
        ])->assertOk();

        $event = AuditEvent::query()
            ->where('event_type', 'staff.roles.synced')
            ->sole();

        $this->assertSame('staff', $event->category);
        $this->assertSame('notice', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame('user', $event->target_type);
        $this->assertSame($target->id, $event->target_id);
        $this->assertSame('sync_roles', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertSame([
            'previous_roles' => ['staff'],
            'new_roles' => ['admin'],
            'added_roles' => ['admin'],
            'removed_roles' => ['staff'],
            'role_count' => 1,
            'source' => 'admin_staff_role_sync',
        ], $event->metadata);

        foreach (['name', 'email', 'password', 'token', 'cookie', 'request', 'payload'] as $key) {
            $this->assertArrayNotHasKey($key, $event->metadata);
        }
    }

    public function test_validation_failure_does_not_record_success_event(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/roles", [
            'roles' => [],
        ])->assertUnprocessable();

        $this->assertSuccessEventWasNotRecorded();
    }

    public function test_authorization_failure_does_not_record_success_event(): void
    {
        Sanctum::actingAs($this->userWithRole('staff'));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/roles", [
            'roles' => ['admin'],
        ])->assertForbidden();

        $this->assertSuccessEventWasNotRecorded();
    }

    public function test_super_admin_target_does_not_record_success_event(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('super-admin');

        $this->putJson("/api/admin/staff/{$target->id}/roles", [
            'roles' => ['admin'],
        ])->assertNotFound();

        $this->assertSuccessEventWasNotRecorded();
    }

    private function assertSuccessEventWasNotRecorded(): void
    {
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'staff.roles.synced',
            'outcome' => 'success',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
