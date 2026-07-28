<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminStaffActivityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_cannot_read_staff_activity(): void
    {
        $staff = $this->userWithRole('staff');

        $this->getJson("/api/admin/staff/{$staff->id}/activity")
            ->assertUnauthorized();
    }

    public function test_view_permission_alone_does_not_grant_activity_access(): void
    {
        $viewer = $this->userWithRole('admin');
        $viewer->givePermissionTo('admin.staff.view');
        $staff = $this->userWithRole('staff');
        Sanctum::actingAs($viewer, ['*']);

        $this->getJson("/api/admin/staff/{$staff->id}/activity")
            ->assertForbidden();
    }

    public function test_delegated_internal_user_can_read_target_and_actor_events_only(): void
    {
        $viewer = $this->userWithRole('staff');
        $viewer->givePermissionTo('admin.staff.activity.view');
        $staff = $this->userWithRole('staff');

        $targetEvent = $this->createEvent([
            'event_type' => 'staff.created',
            'target_id' => $staff->id,
        ]);
        $actorEvent = $this->createEvent([
            'event_type' => 'user.login',
            'actor_id' => $staff->id,
            'target_id' => null,
        ]);
        $this->createEvent([
            'event_type' => 'unrelated.event',
            'actor_id' => 999,
            'target_id' => 998,
        ]);

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->getJson("/api/admin/staff/{$staff->id}/activity")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'تم جلب سجل عمليات الحساب بنجاح.')
            ->assertJsonPath('meta.staff.id', $staff->id);

        $ids = collect($response->json('data.data'))->pluck('id')->all();

        $this->assertContains($targetEvent->id, $ids);
        $this->assertContains($actorEvent->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_activity_response_exposes_only_account_workspace_fields(): void
    {
        $viewer = $this->userWithRole('admin');
        $viewer->givePermissionTo('admin.staff.activity.view');
        $staff = $this->userWithRole('staff');
        $event = $this->createEvent([
            'target_id' => $staff->id,
            'metadata' => ['source' => 'staff_activity_test'],
        ]);

        Sanctum::actingAs($viewer, ['*']);

        $item = $this->getJson("/api/admin/staff/{$staff->id}/activity")
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $event->id)
            ->json('data.data.0');

        $this->assertSame([
            'id',
            'event_type',
            'category',
            'severity',
            'actor_id',
            'actor_role',
            'target_id',
            'action',
            'outcome',
            'request_id',
            'correlation_id',
            'metadata',
            'occurred_at',
        ], array_keys($item));
        $this->assertArrayNotHasKey('ip_address', $item);
        $this->assertArrayNotHasKey('user_agent', $item);
        $this->assertArrayNotHasKey('created_at', $item);
    }

    public function test_activity_filters_and_pagination_are_validated(): void
    {
        $viewer = $this->userWithRole('admin');
        $viewer->givePermissionTo('admin.staff.activity.view');
        $staff = $this->userWithRole('staff');

        foreach (range(1, 12) as $index) {
            $this->createEvent([
                'event_type' => $index % 2 === 0 ? 'staff.created' : 'user.login',
                'target_id' => $staff->id,
                'occurred_at' => now()->subMinutes($index),
            ]);
        }

        Sanctum::actingAs($viewer, ['*']);

        $this->getJson("/api/admin/staff/{$staff->id}/activity?event_type=staff.created&per_page=5")
            ->assertOk()
            ->assertJsonCount(5, 'data.data')
            ->assertJsonPath('data.total', 6);

        $this->getJson("/api/admin/staff/{$staff->id}/activity?payload=x")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payload']);

        $this->getJson("/api/admin/staff/{$staff->id}/activity?per_page=31")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_external_role_remains_forbidden_with_activity_permission(): void
    {
        $client = $this->userWithRole('client');
        $client->givePermissionTo('admin.staff.activity.view');
        $staff = $this->userWithRole('staff');
        Sanctum::actingAs($client, ['*']);

        $this->getJson("/api/admin/staff/{$staff->id}/activity")
            ->assertForbidden();
    }

    public function test_non_team_target_and_super_admin_target_are_not_exposed(): void
    {
        $viewer = $this->userWithRole('admin');
        $viewer->givePermissionTo('admin.staff.activity.view');
        Sanctum::actingAs($viewer, ['*']);

        $client = $this->userWithRole('client');
        $superAdmin = $this->userWithRole('super-admin');

        $this->getJson("/api/admin/staff/{$client->id}/activity")
            ->assertNotFound();

        $this->getJson("/api/admin/staff/{$superAdmin->id}/activity")
            ->assertNotFound();
    }

    public function test_super_admin_can_read_activity_without_permission_pivots(): void
    {
        $role = Role::findByName(User::superAdminRoleName(), 'web');
        $role->syncPermissions([]);

        $superAdmin = $this->userWithRole('super-admin');
        $staff = $this->userWithRole('staff');
        $this->createEvent(['target_id' => $staff->id]);

        Sanctum::actingAs($superAdmin, ['*']);

        $this->getJson("/api/admin/staff/{$staff->id}/activity")
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    private function createEvent(array $overrides = []): AuditEvent
    {
        return AuditEvent::query()->create(array_merge([
            'event_type' => 'test.event',
            'category' => 'staff',
            'severity' => 'info',
            'actor_type' => 'user',
            'actor_id' => 1,
            'actor_role' => 'admin',
            'target_type' => 'user',
            'target_id' => 2,
            'action' => 'test',
            'outcome' => 'success',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Staff activity test agent',
            'request_id' => 'staff-activity-request',
            'correlation_id' => 'staff-activity-correlation',
            'metadata' => ['source' => 'staff_activity_test'],
            'occurred_at' => now(),
        ], $overrides));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
