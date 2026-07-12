<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessManagementRoleAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_super_admin_role_creation_records_a_safe_audit_event(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();

        $response = $this->withHeaders([
            'User-Agent' => 'Access role audit test agent',
            'X-Request-ID' => 'role-create-request-123',
            'X-Correlation-ID' => 'role-create-correlation-456',
        ])->postJson('/api/admin/roles', [
            'name' => 'support-agent',
        ])->assertCreated();

        $event = $this->soleRoleEvent('role.created');

        $this->assertRoleEventBasics(
            $event,
            $superAdmin,
            (int) $response->json('data.id'),
            'role.created',
            'create',
        );
        $this->assertSame('Access role audit test agent', $event->user_agent);
        $this->assertSame('role-create-request-123', $event->request_id);
        $this->assertSame('role-create-correlation-456', $event->correlation_id);
        $this->assertSame([
            'role_name' => 'support-agent',
            'source' => 'access_management_role_create',
        ], $event->metadata);
        $this->assertSafeMetadata($event);
    }

    public function test_super_admin_role_name_update_records_old_and_new_names(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();
        $role = $this->createCustomRole('support-agent');

        $this->patchJson("/api/admin/roles/{$role->id}", [
            'name' => 'support-lead',
        ])->assertOk();

        $event = $this->soleRoleEvent('role.updated');

        $this->assertRoleEventBasics(
            $event,
            $superAdmin,
            $role->id,
            'role.updated',
            'update',
        );
        $this->assertSame([
            'changed_fields' => ['name'],
            'old_role_name' => 'support-agent',
            'new_role_name' => 'support-lead',
            'source' => 'access_management_role_update',
        ], $event->metadata);
        $this->assertSafeMetadata($event);
    }

    public function test_super_admin_unused_role_deletion_records_deleted_role_identity(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();
        $role = $this->createCustomRole('temporary-role');
        $roleId = $role->id;

        $this->deleteJson("/api/admin/roles/{$roleId}")
            ->assertOk();

        $event = $this->soleRoleEvent('role.deleted');

        $this->assertRoleEventBasics(
            $event,
            $superAdmin,
            $roleId,
            'role.deleted',
            'delete',
        );
        $this->assertSame([
            'role_name' => 'temporary-role',
            'source' => 'access_management_role_delete',
        ], $event->metadata);
        $this->assertSafeMetadata($event);
    }

    public function test_super_admin_permission_sync_records_resulting_system_permission_names(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();
        $role = $this->createCustomRole('support-agent');

        $this->putJson("/api/admin/roles/{$role->id}/permissions", [
            'permissions' => [
                'dashboard.overview.view',
                'admin.users.view',
            ],
        ])->assertOk();

        $event = $this->soleRoleEvent('role.permissions.synced');

        $this->assertRoleEventBasics(
            $event,
            $superAdmin,
            $role->id,
            'role.permissions.synced',
            'sync_permissions',
        );
        $this->assertSame([
            'role_name' => 'support-agent',
            'permission_count' => 2,
            'permission_names' => [
                'admin.users.view',
                'dashboard.overview.view',
            ],
            'source' => 'access_management_role_permissions_sync',
        ], $event->metadata);
        $this->assertSafeMetadata($event);
    }

    public function test_role_creation_validation_failure_does_not_record_success_event(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/admin/roles', [
            'name' => 'A',
        ])->assertUnprocessable();

        $this->assertSame(0, $this->roleAuditEventCount());
    }

    public function test_protected_role_update_does_not_record_success_event(): void
    {
        $this->actingAsSuperAdmin();
        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $this->patchJson("/api/admin/roles/{$adminRole->id}", [
            'name' => 'admin-renamed',
        ])->assertUnprocessable();

        $this->assertSame(0, $this->roleAuditEventCount());
    }

    public function test_protected_role_deletion_does_not_record_success_event(): void
    {
        $this->actingAsSuperAdmin();
        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $this->deleteJson("/api/admin/roles/{$adminRole->id}")
            ->assertUnprocessable();

        $this->assertSame(0, $this->roleAuditEventCount());
    }

    public function test_role_assigned_to_a_user_cannot_be_deleted_or_audited_as_success(): void
    {
        $this->actingAsSuperAdmin();
        $role = $this->createCustomRole('assigned-role');
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->deleteJson("/api/admin/roles/{$role->id}")
            ->assertUnprocessable();

        $this->assertSame(0, $this->roleAuditEventCount());
    }

    public function test_super_admin_role_permission_sync_rejection_does_not_record_success_event(): void
    {
        $this->actingAsSuperAdmin();
        $superAdminRole = Role::where('name', 'super-admin')->firstOrFail();

        $this->putJson("/api/admin/roles/{$superAdminRole->id}/permissions", [
            'permissions' => ['dashboard.overview.view'],
        ])->assertUnprocessable();

        $this->assertSame(0, $this->roleAuditEventCount());
    }

    public function test_unauthorized_admin_role_operations_do_not_record_success_events(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $updateRole = $this->createCustomRole('update-target');
        $deleteRole = $this->createCustomRole('delete-target');
        $syncRole = $this->createCustomRole('sync-target');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/admin/roles', [
            'name' => 'unauthorized-create',
        ])->assertForbidden();

        $this->patchJson("/api/admin/roles/{$updateRole->id}", [
            'name' => 'unauthorized-update',
        ])->assertForbidden();

        $this->deleteJson("/api/admin/roles/{$deleteRole->id}")
            ->assertForbidden();

        $this->putJson("/api/admin/roles/{$syncRole->id}/permissions", [
            'permissions' => ['dashboard.overview.view'],
        ])->assertForbidden();

        $this->assertSame(0, $this->roleAuditEventCount());
    }

    private function assertRoleEventBasics(
        AuditEvent $event,
        User $actor,
        int $targetId,
        string $eventType,
        string $action,
    ): void {
        $this->assertSame($eventType, $event->event_type);
        $this->assertSame('roles', $event->category);
        $this->assertSame('notice', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertEquals($actor->id, $event->actor_id);
        $this->assertSame('super-admin', $event->actor_role);
        $this->assertSame('role', $event->target_type);
        $this->assertEquals($targetId, $event->target_id);
        $this->assertSame($action, $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertNotNull($event->ip_address);
    }

    private function assertSafeMetadata(AuditEvent $event): void
    {
        $this->assertArrayNotHasKey('payload', $event->metadata);
        $this->assertArrayNotHasKey('request', $event->metadata);
        $this->assertArrayNotHasKey('request_body', $event->metadata);

        $storedMetadata = strtolower(json_encode($event->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('password', $storedMetadata);
        $this->assertStringNotContainsString('token', $storedMetadata);
        $this->assertStringNotContainsString('cookie', $storedMetadata);
    }

    private function soleRoleEvent(string $eventType): AuditEvent
    {
        return AuditEvent::query()
            ->where('event_type', $eventType)
            ->sole();
    }

    private function roleAuditEventCount(): int
    {
        return AuditEvent::query()
            ->whereIn('event_type', [
                'role.created',
                'role.updated',
                'role.deleted',
                'role.permissions.synced',
            ])
            ->count();
    }

    private function actingAsSuperAdmin(): User
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');
        Sanctum::actingAs($superAdmin, ['*']);

        return $superAdmin;
    }

    private function createCustomRole(string $name): Role
    {
        return Role::create([
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }
}
