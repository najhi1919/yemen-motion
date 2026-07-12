<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessManagementPermissionAuditEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_super_admin_permission_creation_records_a_safe_audit_event(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();

        $response = $this->withHeaders([
            'User-Agent' => 'Access permission audit test agent',
            'X-Request-ID' => 'permission-create-request-123',
            'X-Correlation-ID' => 'permission-create-correlation-456',
        ])->postJson('/api/admin/permissions', [
            'name' => 'custom.reports.export',
        ])->assertCreated();

        $event = $this->solePermissionEvent('permission.created');

        $this->assertPermissionEventBasics(
            $event,
            $superAdmin,
            (int) $response->json('data.id'),
            'permission.created',
            'create',
        );
        $this->assertSame('Access permission audit test agent', $event->user_agent);
        $this->assertSame('permission-create-request-123', $event->request_id);
        $this->assertSame('permission-create-correlation-456', $event->correlation_id);
        $this->assertSame([
            'permission_name' => 'custom.reports.export',
            'source' => 'access_management_permission_create',
        ], $event->metadata);
        $this->assertSafeMetadata($event);
    }

    public function test_super_admin_permission_name_update_records_old_and_new_names(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();
        $permission = $this->createCustomPermission('custom.reports.export');

        $this->patchJson("/api/admin/permissions/{$permission->id}", [
            'name' => 'custom.reports.download',
        ])->assertOk();

        $event = $this->solePermissionEvent('permission.updated');

        $this->assertPermissionEventBasics(
            $event,
            $superAdmin,
            $permission->id,
            'permission.updated',
            'update',
        );
        $this->assertSame([
            'changed_fields' => ['name'],
            'old_permission_name' => 'custom.reports.export',
            'new_permission_name' => 'custom.reports.download',
            'source' => 'access_management_permission_update',
        ], $event->metadata);
        $this->assertSafeMetadata($event);
    }

    public function test_super_admin_allowed_permission_deletion_records_deleted_identity(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();
        $permission = $this->createCustomPermission('custom.temporary.access');
        Role::where('name', 'super-admin')
            ->firstOrFail()
            ->givePermissionTo($permission);
        $permissionId = $permission->id;

        $this->deleteJson("/api/admin/permissions/{$permissionId}")
            ->assertOk();

        $event = $this->solePermissionEvent('permission.deleted');

        $this->assertPermissionEventBasics(
            $event,
            $superAdmin,
            $permissionId,
            'permission.deleted',
            'delete',
        );
        $this->assertSame([
            'permission_name' => 'custom.temporary.access',
            'source' => 'access_management_permission_delete',
        ], $event->metadata);
        $this->assertSafeMetadata($event);
    }

    public function test_permission_creation_validation_failure_does_not_record_success_event(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/admin/permissions', [
            'name' => 'A',
        ])->assertUnprocessable();

        $this->assertSame(0, $this->permissionAuditEventCount());
    }

    public function test_system_permission_update_does_not_record_success_event(): void
    {
        $this->actingAsSuperAdmin();
        $systemPermission = Permission::where('name', 'admin.users.view')->firstOrFail();

        $this->patchJson("/api/admin/permissions/{$systemPermission->id}", [
            'name' => 'custom.users.view',
        ])->assertUnprocessable();

        $this->assertSame(0, $this->permissionAuditEventCount());
    }

    public function test_system_permission_deletion_does_not_record_success_event(): void
    {
        $this->actingAsSuperAdmin();
        $systemPermission = Permission::where('name', 'admin.users.view')->firstOrFail();

        $this->deleteJson("/api/admin/permissions/{$systemPermission->id}")
            ->assertUnprocessable();

        $this->assertSame(0, $this->permissionAuditEventCount());
    }

    public function test_permission_assigned_to_non_super_admin_role_cannot_be_deleted_or_audited_as_success(): void
    {
        $this->actingAsSuperAdmin();
        $permission = $this->createCustomPermission('custom.reports.export');
        Role::where('name', 'super-admin')
            ->firstOrFail()
            ->givePermissionTo($permission);
        Role::where('name', 'staff')
            ->firstOrFail()
            ->givePermissionTo($permission);

        $this->deleteJson("/api/admin/permissions/{$permission->id}")
            ->assertUnprocessable();

        $this->assertSame(0, $this->permissionAuditEventCount());
    }

    public function test_unauthorized_admin_permission_operations_do_not_record_success_events(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $updatePermission = $this->createCustomPermission('custom.update.target');
        $deletePermission = $this->createCustomPermission('custom.delete.target');
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/admin/permissions', [
            'name' => 'custom.unauthorized.create',
        ])->assertForbidden();

        $this->patchJson("/api/admin/permissions/{$updatePermission->id}", [
            'name' => 'custom.unauthorized.update',
        ])->assertForbidden();

        $this->deleteJson("/api/admin/permissions/{$deletePermission->id}")
            ->assertForbidden();

        $this->assertSame(0, $this->permissionAuditEventCount());
    }

    private function assertPermissionEventBasics(
        AuditEvent $event,
        User $actor,
        int $targetId,
        string $eventType,
        string $action,
    ): void {
        $this->assertSame($eventType, $event->event_type);
        $this->assertSame('permissions', $event->category);
        $this->assertSame('notice', $event->severity);
        $this->assertSame('user', $event->actor_type);
        $this->assertEquals($actor->id, $event->actor_id);
        $this->assertSame('super-admin', $event->actor_role);
        $this->assertSame('permission', $event->target_type);
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

    private function solePermissionEvent(string $eventType): AuditEvent
    {
        return AuditEvent::query()
            ->where('event_type', $eventType)
            ->sole();
    }

    private function permissionAuditEventCount(): int
    {
        return AuditEvent::query()
            ->whereIn('event_type', [
                'permission.created',
                'permission.updated',
                'permission.deleted',
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

    private function createCustomPermission(string $name): Permission
    {
        return Permission::create([
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }
}
