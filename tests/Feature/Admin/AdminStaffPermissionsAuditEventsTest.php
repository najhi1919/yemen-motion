<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStaffPermissionsAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_changed_sync_records_safe_permission_difference_metadata(): void
    {
        $actor = $this->userWithRole('super-admin');
        $target = $this->userWithRole('staff');
        $target->givePermissionTo('admin.staff.view');
        Sanctum::actingAs($actor);

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => ['admin.staff.update'],
        ])->assertOk();

        $event = AuditEvent::query()
            ->where('event_type', 'staff.permissions.synced')
            ->sole();

        $this->assertSame('staff', $event->category);
        $this->assertSame('notice', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame('user', $event->target_type);
        $this->assertSame($target->id, $event->target_id);
        $this->assertSame('sync_permissions', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertSame([
            'previous_direct_permissions' => ['admin.staff.view'],
            'new_direct_permissions' => ['admin.staff.update'],
            'added_permissions' => ['admin.staff.update'],
            'removed_permissions' => ['admin.staff.view'],
            'direct_permission_count' => 1,
            'source' => 'admin_staff_permission_sync',
        ], $event->metadata);

        foreach (['name', 'email', 'password', 'token', 'cookie', 'request', 'payload'] as $key) {
            $this->assertArrayNotHasKey($key, $event->metadata);
        }
    }

    public function test_validation_failure_does_not_record_success_event(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => ['unknown.permission'],
        ])->assertUnprocessable();

        $this->assertSuccessEventWasNotRecorded();
    }

    public function test_authorization_failure_does_not_record_success_event(): void
    {
        Sanctum::actingAs($this->userWithRole('staff'));
        $target = $this->userWithRole('staff');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => [],
        ])->assertForbidden();

        $this->assertSuccessEventWasNotRecorded();
    }

    public function test_super_admin_target_does_not_record_success_event(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('super-admin');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => [],
        ])->assertNotFound();

        $this->assertSuccessEventWasNotRecorded();
    }

    public function test_sync_without_an_actual_change_does_not_record_an_event(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff');
        $target->givePermissionTo('admin.staff.view');

        $this->putJson("/api/admin/staff/{$target->id}/permissions", [
            'permissions' => ['admin.staff.view'],
        ])->assertOk();

        $this->assertSuccessEventWasNotRecorded();
    }

    private function assertSuccessEventWasNotRecorded(): void
    {
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'staff.permissions.synced',
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
