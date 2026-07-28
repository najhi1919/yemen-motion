<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStaffLifecycleAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_disable_restore_and_delete_record_expected_safe_events(): void
    {
        $actor = $this->userWithRole('super-admin');
        Sanctum::actingAs($actor);
        $target = $this->userWithRole('staff');
        $target->createToken('revoke');
        $this->createSession($target);

        $this->patchJson("/api/admin/staff/{$target->id}/disable")->assertOk();
        $disabled = $this->event('staff.disabled');
        $this->assertEventContext($disabled, $actor, $target->id, 'disable');
        $this->assertSame([
            'previous_status' => 'active',
            'current_status' => 'disabled',
            'revoked_access_count' => 1,
            'revoked_session_count' => 1,
            'source' => 'admin_staff_disable',
        ], $disabled->metadata);

        $this->patchJson("/api/admin/staff/{$target->id}/restore")->assertOk();
        $restored = $this->event('staff.restored');
        $this->assertEventContext($restored, $actor, $target->id, 'restore');
        $this->assertSame([
            'previous_status' => 'disabled',
            'current_status' => 'active',
            'source' => 'admin_staff_restore',
        ], $restored->metadata);

        $target->update(['disabled_at' => now()]);
        $this->deleteJson("/api/admin/staff/{$target->id}", [
            'confirmation' => 'DELETE',
        ])->assertOk();

        $deleted = $this->event('staff.deleted');
        $this->assertEventContext($deleted, $actor, $target->id, 'delete');
        $this->assertSame([
            'was_disabled' => true,
            'revoked_access_count' => 0,
            'revoked_session_count' => 0,
            'deletion_guard_passed' => true,
            'source' => 'admin_staff_delete',
        ], $deleted->metadata);
        $this->assertIsInt($disabled->metadata['revoked_access_count']);
        $this->assertIsInt($deleted->metadata['revoked_access_count']);
        $this->assertDatabaseHas('audit_events', ['id' => $deleted->id]);

        foreach ([$disabled, $restored, $deleted] as $event) {
            foreach (array_keys($event->metadata) as $key) {
                $this->assertStringNotContainsString('token', strtolower($key));
            }

            foreach ([
                'name',
                'email',
                'password',
                'token',
                'cookie',
                'request',
                'payload',
                'confirmation',
                'roles',
                'permissions',
            ] as $key) {
                $this->assertArrayNotHasKey($key, $event->metadata);
            }
        }
    }

    public function test_no_op_disable_and_restore_do_not_record_additional_events(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $disabled = $this->userWithRole('staff', ['disabled_at' => now()]);
        $active = $this->userWithRole('staff');

        $this->patchJson("/api/admin/staff/{$disabled->id}/disable")
            ->assertOk()
            ->assertJsonPath('data.changed', false);
        $this->patchJson("/api/admin/staff/{$active->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertLifecycleSuccessEventCount(0);
    }

    public function test_validation_authorization_and_protected_targets_do_not_record_success(): void
    {
        $target = $this->userWithRole('staff');
        Sanctum::actingAs($this->userWithRole('staff'));

        $this->patchJson("/api/admin/staff/{$target->id}/disable")
            ->assertForbidden();

        Sanctum::actingAs($this->userWithRole('super-admin'));
        $superAdmin = $this->userWithRole('super-admin');

        $this->patchJson("/api/admin/staff/{$superAdmin->id}/disable")
            ->assertNotFound();
        $this->patchJson("/api/admin/staff/{$target->id}/disable", [
            'reason' => 'invalid',
        ])->assertUnprocessable();

        $this->assertLifecycleSuccessEventCount(0);
    }

    public function test_self_active_and_blocked_delete_do_not_record_success(): void
    {
        $actor = $this->userWithRole('admin');
        $actor->givePermissionTo([
            'admin.staff.disable',
            'admin.staff.delete',
        ]);
        Sanctum::actingAs($actor);

        $this->patchJson("/api/admin/staff/{$actor->id}/disable")
            ->assertUnprocessable();

        $active = $this->userWithRole('staff');
        $this->deleteJson("/api/admin/staff/{$active->id}", [
            'confirmation' => 'DELETE',
        ])->assertUnprocessable();

        $blocked = $this->userWithRole('staff', ['disabled_at' => now()]);
        Work::factory()->create(['designer_id' => $blocked->id]);

        $this->deleteJson("/api/admin/staff/{$blocked->id}", [
            'confirmation' => 'DELETE',
        ])->assertStatus(409);

        $this->assertLifecycleSuccessEventCount(0);
        $this->assertDatabaseHas('users', ['id' => $blocked->id]);
    }

    private function assertEventContext(
        AuditEvent $event,
        User $actor,
        int $targetId,
        string $action,
    ): void {
        $this->assertSame('staff', $event->category);
        $this->assertSame('user', $event->actor_type);
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame('user', $event->target_type);
        $this->assertSame($targetId, $event->target_id);
        $this->assertSame($action, $event->action);
        $this->assertSame('success', $event->outcome);
    }

    private function event(string $eventType): AuditEvent
    {
        return AuditEvent::query()->where('event_type', $eventType)->sole();
    }

    private function assertLifecycleSuccessEventCount(int $count): void
    {
        $this->assertSame(
            $count,
            AuditEvent::query()
                ->whereIn('event_type', ['staff.disabled', 'staff.restored', 'staff.deleted'])
                ->where('outcome', 'success')
                ->count(),
        );
    }

    private function createSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => fake()->uuid(),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
