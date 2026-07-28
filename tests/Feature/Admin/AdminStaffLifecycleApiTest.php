<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Work;
use App\Models\WorkMedia;
use App\Models\WorkReport;
use App\Models\WorkSetting;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminStaffLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_disabled_at_column_and_named_index_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'disabled_at'));
        $this->assertContains(
            'users_disabled_at_id_index',
            collect(Schema::getIndexes('users'))->pluck('name')->all(),
        );
    }

    public function test_list_payload_filters_and_summary_include_account_status(): void
    {
        $actor = $this->userWithRole('super-admin');
        $active = $this->userWithRole('staff');
        $disabled = $this->userWithRole('admin', ['disabled_at' => now()]);
        Sanctum::actingAs($actor);

        $this->getJson('/api/admin/staff?status=all')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $active->id,
                'disabled_at' => null,
                'is_disabled' => false,
                'status' => 'active',
            ])
            ->assertJsonFragment([
                'id' => $disabled->id,
                'is_disabled' => true,
                'status' => 'disabled',
            ])
            ->assertJsonPath('meta.summary.total', 2)
            ->assertJsonPath('meta.summary.active', 1)
            ->assertJsonPath('meta.summary.disabled', 1);

        $this->getJson('/api/admin/staff?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $active->id);

        $this->getJson('/api/admin/staff?status=disabled')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $disabled->id);
    }

    public function test_super_admin_can_disable_staff_and_revoke_tokens_and_sessions(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff');
        $target->createToken('first');
        $target->createToken('second');
        $this->createSession($target);

        $this->patchJson("/api/admin/staff/{$target->id}/disable")
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.revoked_tokens', 2)
            ->assertJsonPath('data.revoked_sessions', 1)
            ->assertJsonPath('data.user.status', 'disabled');

        $this->assertNotNull($target->fresh()->disabled_at);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $target->id,
        ]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
    }

    public function test_internal_admin_needs_disable_permission(): void
    {
        $actor = $this->userWithRole('admin');
        $target = $this->userWithRole('staff');
        Sanctum::actingAs($actor);

        $this->patchJson("/api/admin/staff/{$target->id}/disable")
            ->assertForbidden();

        $actor->givePermissionTo('admin.staff.disable');

        $this->patchJson("/api/admin/staff/{$target->id}/disable")
            ->assertOk();
    }

    public function test_staff_without_lifecycle_permission_is_forbidden(): void
    {
        Sanctum::actingAs($this->userWithRole('staff'));
        $target = $this->userWithRole('staff');

        $this->patchJson("/api/admin/staff/{$target->id}/disable")
            ->assertForbidden();
    }

    #[DataProvider('externalRoleProvider')]
    public function test_external_actor_is_forbidden_even_with_direct_permission(string $role): void
    {
        $actor = $this->userWithRole($role);
        $actor->givePermissionTo('admin.staff.disable');
        Sanctum::actingAs($actor);
        $target = $this->userWithRole('staff');

        $this->patchJson("/api/admin/staff/{$target->id}/disable")
            ->assertForbidden();
    }

    public static function externalRoleProvider(): array
    {
        return [
            'client' => ['client'],
            'designer' => ['designer'],
        ];
    }

    public function test_repeated_disable_is_idempotent_but_revokes_remaining_access(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff', ['disabled_at' => now()->subDay()]);
        $originalDisabledAt = $target->disabled_at->toJSON();
        $target->createToken('late-token');
        $this->createSession($target);

        $this->patchJson("/api/admin/staff/{$target->id}/disable")
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.revoked_tokens', 1)
            ->assertJsonPath('data.revoked_sessions', 1);

        $this->assertSame($originalDisabledAt, $target->fresh()->disabled_at->toJSON());
    }

    public function test_restore_is_idempotent_and_does_not_create_access(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff', ['disabled_at' => now()]);

        $this->patchJson("/api/admin/staff/{$target->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.user.status', 'active');

        $this->patchJson("/api/admin/staff/{$target->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertNull($target->fresh()->disabled_at);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $target->id,
        ]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
    }

    public function test_self_target_is_rejected_for_disable_and_delete(): void
    {
        $actor = $this->userWithRole('admin');
        $actor->givePermissionTo([
            'admin.staff.disable',
            'admin.staff.delete',
        ]);
        Sanctum::actingAs($actor);

        $this->patchJson("/api/admin/staff/{$actor->id}/disable")
            ->assertUnprocessable();

        $this->deleteJson("/api/admin/staff/{$actor->id}", [
            'confirmation' => 'DELETE',
        ])->assertUnprocessable();
    }

    public function test_super_admin_and_external_targets_are_not_found(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $superAdmin = $this->userWithRole('super-admin');
        $client = $this->userWithRole('client');

        $this->patchJson("/api/admin/staff/{$superAdmin->id}/disable")
            ->assertNotFound();
        $this->patchJson("/api/admin/staff/{$client->id}/disable")
            ->assertNotFound();
    }

    #[DataProvider('bodylessActionProvider')]
    public function test_disable_and_restore_reject_body_and_query_parameters(string $action): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff', [
            'disabled_at' => $action === 'restore' ? now() : null,
        ]);

        $this->patchJson("/api/admin/staff/{$target->id}/{$action}?force=1", [
            'reason' => 'not allowed',
        ])->assertUnprocessable();
    }

    public static function bodylessActionProvider(): array
    {
        return [
            'disable' => ['disable'],
            'restore' => ['restore'],
        ];
    }

    public function test_delete_requires_exact_confirmation_and_rejects_extra_input_and_query(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff', ['disabled_at' => now()]);

        $this->deleteJson("/api/admin/staff/{$target->id}", [
            'confirmation' => 'delete',
        ])->assertUnprocessable()->assertJsonValidationErrors('confirmation');

        $this->deleteJson("/api/admin/staff/{$target->id}?force=1", [
            'confirmation' => 'DELETE',
            'reason' => 'not allowed',
        ])->assertUnprocessable();
    }

    public function test_active_account_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff');

        $this->deleteJson("/api/admin/staff/{$target->id}", [
            'confirmation' => 'DELETE',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_disabled_unreferenced_account_is_hard_deleted_with_access_data(): void
    {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff', ['disabled_at' => now()]);
        $target->givePermissionTo('admin.staff.view');
        $target->createToken('delete-me');
        $this->createSession($target);
        DB::table('password_reset_tokens')->insert([
            'email' => $target->email,
            'token' => 'hashed-token',
            'created_at' => now(),
        ]);

        $this->deleteJson("/api/admin/staff/{$target->id}", [
            'confirmation' => 'DELETE',
        ])
            ->assertOk()
            ->assertJsonPath('data.deleted_user_id', $target->id);

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $target->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $target->id]);
        $this->assertDatabaseMissing('model_has_permissions', ['model_id' => $target->id]);
    }

    #[DataProvider('deletionBlockerProvider')]
    public function test_each_operational_reference_blocks_delete(
        string $blocker,
    ): void {
        Sanctum::actingAs($this->userWithRole('super-admin'));
        $target = $this->userWithRole('staff', ['disabled_at' => now()]);
        $this->createOperationalReference($blocker, $target->id);

        $response = $this->deleteJson("/api/admin/staff/{$target->id}", [
            'confirmation' => 'DELETE',
        ])->assertStatus(409);

        $this->assertSame(1, $response->json("data.deletion_blockers.{$blocker}"));
        $this->assertSame([
            'assigned_works',
            'reviewed_works',
            'submitted_reports',
            'reviewed_reports',
            'uploaded_media',
            'settings_updates',
        ], array_keys($response->json('data.deletion_blockers')));
        $response
            ->assertJsonMissing(['name' => $target->name])
            ->assertJsonMissing(['email' => $target->email]);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public static function deletionBlockerProvider(): array
    {
        return [
            'assigned work' => ['assigned_works'],
            'reviewed work' => ['reviewed_works'],
            'submitted report' => ['submitted_reports'],
            'reviewed report' => ['reviewed_reports'],
            'uploaded media' => ['uploaded_media'],
            'settings update' => ['settings_updates'],
        ];
    }

    public function test_lifecycle_routes_use_expected_methods_and_numeric_constraints(): void
    {
        foreach ([
            ['PATCH', '/api/admin/staff/12/disable', 'disable'],
            ['PATCH', '/api/admin/staff/12/restore', 'restore'],
            ['DELETE', '/api/admin/staff/12', 'destroy'],
        ] as [$method, $uri, $action]) {
            $route = Route::getRoutes()->match(Request::create($uri, $method));

            $this->assertSame($action, $route->getActionMethod());
            $this->assertSame('[0-9]+', $route->wheres['staff']);
        }
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

    private function createOperationalReference(string $blocker, int $userId): void
    {
        match ($blocker) {
            'assigned_works' => Work::factory()->create([
                'designer_id' => $userId,
                'reviewer_id' => null,
            ]),
            'reviewed_works' => Work::factory()->create([
                'reviewer_id' => $userId,
            ]),
            'submitted_reports' => WorkReport::factory()->create([
                'work_id' => Work::factory(),
                'reporter_id' => $userId,
                'reviewed_by' => null,
            ]),
            'reviewed_reports' => WorkReport::factory()->create([
                'work_id' => Work::factory(),
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]),
            'uploaded_media' => WorkMedia::factory()->create([
                'work_id' => Work::factory(),
                'uploaded_by' => $userId,
            ]),
            'settings_updates' => WorkSetting::query()->create([
                'scope' => "staff-lifecycle-{$userId}",
                'values' => ['lifecycle_test' => true],
                'version' => 1,
                'updated_by' => $userId,
            ]),
        };
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
