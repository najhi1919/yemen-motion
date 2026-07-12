<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminUsersRoleAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_super_admin_role_sync_records_safe_role_differences(): void
    {
        $superAdmin = $this->userWithRoles(['super-admin']);
        $target = $this->userWithRoles(['client', 'designer'], [
            'name' => 'Audited Role Target',
            'email' => 'audited-role-target@example.com',
        ]);
        Sanctum::actingAs($superAdmin, ['*']);

        $this->withHeaders([
            'User-Agent' => 'Admin users role audit test agent',
            'X-Request-ID' => 'user-role-sync-request-123',
            'X-Correlation-ID' => 'user-role-sync-correlation-456',
        ])->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => ['staff', 'designer', 'staff'],
        ])->assertOk();

        $event = $this->soleRoleSyncEvent();

        $this->assertRoleSyncEventBasics($event, $superAdmin, $target);
        $this->assertSame('Admin users role audit test agent', $event->user_agent);
        $this->assertSame('user-role-sync-request-123', $event->request_id);
        $this->assertSame('user-role-sync-correlation-456', $event->correlation_id);
        $this->assertSame([
            'previous_roles' => ['client', 'designer'],
            'new_roles' => ['designer', 'staff'],
            'added_roles' => ['staff'],
            'removed_roles' => ['client'],
            'role_count' => 2,
            'source' => 'admin_users_role_sync',
        ], $event->metadata);
        $this->assertSafeMetadata($event, $target);
    }

    public function test_authorized_admin_role_sync_records_admin_as_actor(): void
    {
        $admin = $this->authorizedAdmin();
        $target = $this->userWithRoles(['client']);
        Sanctum::actingAs($admin, ['*']);

        $this->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => ['staff'],
        ])->assertOk();

        $event = $this->soleRoleSyncEvent();

        $this->assertRoleSyncEventBasics($event, $admin, $target, 'admin');
        $this->assertSame(['client'], $event->metadata['previous_roles']);
        $this->assertSame(['staff'], $event->metadata['new_roles']);
        $this->assertSame(['staff'], $event->metadata['added_roles']);
        $this->assertSame(['client'], $event->metadata['removed_roles']);
        $this->assertSame(1, $event->metadata['role_count']);
        $this->assertSame('admin_users_role_sync', $event->metadata['source']);
        $this->assertSafeMetadata($event, $target);
    }

    public function test_validation_failure_does_not_record_role_sync_event(): void
    {
        $superAdmin = $this->userWithRoles(['super-admin']);
        $target = $this->userWithRoles(['client']);
        Sanctum::actingAs($superAdmin, ['*']);

        $this->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => [],
        ])->assertUnprocessable();

        $this->assertSame(0, $this->roleSyncEventCount());
    }

    public function test_unknown_role_rejection_does_not_record_role_sync_event(): void
    {
        $superAdmin = $this->userWithRoles(['super-admin']);
        $target = $this->userWithRoles(['client']);
        Sanctum::actingAs($superAdmin, ['*']);

        $this->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => ['unknown-role'],
        ])->assertUnprocessable();

        $this->assertSame(0, $this->roleSyncEventCount());
    }

    public function test_admin_cannot_assign_super_admin_role_or_record_success_event(): void
    {
        $admin = $this->authorizedAdmin();
        $target = $this->userWithRoles(['client']);
        Sanctum::actingAs($admin, ['*']);

        $this->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => ['super-admin'],
        ])->assertForbidden();

        $this->assertSame(0, $this->roleSyncEventCount());
    }

    public function test_admin_cannot_modify_super_admin_user_or_record_success_event(): void
    {
        $admin = $this->authorizedAdmin();
        $target = $this->userWithRoles(['super-admin']);
        Sanctum::actingAs($admin, ['*']);

        $this->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => ['admin'],
        ])->assertForbidden();

        $this->assertSame(0, $this->roleSyncEventCount());
    }

    public function test_super_admin_role_removal_rejection_does_not_record_success_event(): void
    {
        $superAdmin = $this->userWithRoles(['super-admin']);
        Sanctum::actingAs($superAdmin, ['*']);

        $this->putJson("/api/admin/users/{$superAdmin->id}/roles", [
            'roles' => ['admin'],
        ])->assertUnprocessable();

        $this->assertSame(0, $this->roleSyncEventCount());
    }

    public function test_unauthorized_user_cannot_record_role_sync_success_event(): void
    {
        $staff = $this->userWithRoles(['staff']);
        $target = $this->userWithRoles(['client']);
        Sanctum::actingAs($staff, ['*']);

        $this->putJson("/api/admin/users/{$target->id}/roles", [
            'roles' => ['designer'],
        ])->assertForbidden();

        $this->assertSame(0, $this->roleSyncEventCount());
    }

    private function assertRoleSyncEventBasics(
        AuditEvent $event,
        User $actor,
        User $target,
        string $actorRole = 'super-admin',
    ): void {
        $this->assertSame('user.roles.synced', $event->event_type);
        $this->assertSame('users', $event->category);
        $this->assertSame('notice', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertEquals($actor->id, $event->actor_id);
        $this->assertSame($actorRole, $event->actor_role);
        $this->assertSame('user', $event->target_type);
        $this->assertEquals($target->id, $event->target_id);
        $this->assertSame('sync_roles', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertNotNull($event->ip_address);
    }

    private function assertSafeMetadata(AuditEvent $event, User $target): void
    {
        foreach (['email', 'name', 'payload', 'request', 'request_body', 'password', 'token', 'cookie'] as $key) {
            $this->assertArrayNotHasKey($key, $event->metadata);
        }

        $storedMetadata = strtolower(json_encode($event->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString(strtolower($target->email), $storedMetadata);
        $this->assertStringNotContainsString(strtolower($target->name), $storedMetadata);
        $this->assertStringNotContainsString('password', $storedMetadata);
        $this->assertStringNotContainsString('token', $storedMetadata);
        $this->assertStringNotContainsString('cookie', $storedMetadata);
    }

    private function soleRoleSyncEvent(): AuditEvent
    {
        return AuditEvent::query()
            ->where('event_type', 'user.roles.synced')
            ->sole();
    }

    private function roleSyncEventCount(): int
    {
        return AuditEvent::query()
            ->where('event_type', 'user.roles.synced')
            ->count();
    }

    private function authorizedAdmin(): User
    {
        Role::where('name', 'admin')
            ->firstOrFail()
            ->givePermissionTo('admin.users.assign_roles');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->userWithRoles(['admin']);
    }

    /**
     * @param list<string> $roles
     * @param array<string, mixed> $attributes
     */
    private function userWithRoles(array $roles, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($roles);

        return $user;
    }
}
