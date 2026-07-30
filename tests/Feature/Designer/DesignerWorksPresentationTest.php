<?php

namespace Tests\Feature\Designer;

use App\Http\Controllers\Api\DesignerWorksPresentationController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DesignerWorksPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_routes_resolve_to_expected_controller_and_methods(): void
    {
        $show = Route::getRoutes()->getByName('designer.works.presentation.show');
        $update = Route::getRoutes()->getByName('designer.works.presentation.update');

        $this->assertSame(DesignerWorksPresentationController::class.'@show', $show?->getActionName());
        $this->assertSame(['GET', 'HEAD'], $show?->methods());
        $this->assertSame(DesignerWorksPresentationController::class.'@update', $update?->getActionName());
        $this->assertSame(['PATCH'], $update?->methods());
    }

    public function test_put_and_delete_are_not_supported(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer);
        Sanctum::actingAs($designer);

        $this->putJson($this->endpoint($work), $this->payload())->assertMethodNotAllowed();
        $this->deleteJson($this->endpoint($work))->assertMethodNotAllowed();
    }

    public function test_guest_client_staff_and_disabled_designer_are_denied(): void
    {
        $owner = $this->designer();
        $work = $this->work($owner);
        $this->getJson($this->endpoint($work))->assertUnauthorized();

        foreach (['client', 'staff'] as $role) {
            Sanctum::actingAs($this->userWithRole($role));
            $this->getJson($this->endpoint($work))->assertForbidden();
        }

        $disabled = $this->designer(['disabled_at' => now()]);
        Sanctum::actingAs($disabled);
        $this->getJson($this->endpoint($this->work($disabled)))->assertForbidden();
    }

    public function test_designer_reads_only_owned_work_and_multi_role_does_not_bypass_ownership(): void
    {
        $designer = $this->designer();
        $designer->assignRole('super-admin');
        $owned = $this->work($designer);
        $foreign = $this->work($this->designer());
        Sanctum::actingAs($designer);

        $this->getJson($this->endpoint($owned))
            ->assertOk()
            ->assertJsonPath('data.work.id', $owned->id)
            ->assertJsonMissingPath('data.work.designer_id');
        $this->getJson($this->endpoint($foreign))->assertNotFound();
        $this->patchJson($this->endpoint($foreign), $this->payload())->assertNotFound();
    }

    public function test_get_is_available_in_all_states_and_reports_editability(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);

        foreach ($this->statuses() as $status) {
            $work = $this->work($designer, ['status' => $status]);
            $this->getJson($this->endpoint($work))
                ->assertOk()
                ->assertJsonPath(
                    'data.presentation_state.editable',
                    in_array($status, [Work::STATUS_DRAFT, Work::STATUS_CHANGES_REQUESTED], true),
                );
        }
    }

    public function test_defaults_and_response_are_allowlisted(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer);
        Sanctum::actingAs($designer);

        $response = $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.work.cover_display_mode', 'fill')
            ->assertJsonPath('data.work.cover_focal_point.x', 50)
            ->assertJsonPath('data.work.cover_focal_point.y', 50)
            ->assertJsonPath('data.presentation_state.available_modes', ['fill', 'fit'])
            ->assertJsonMissingPath('data.work.designer_id')
            ->assertJsonMissingPath('data.work.cover_media')
            ->assertJsonMissingPath('data.work.path');

        $this->assertSame([
            'id',
            'public_code',
            'title',
            'status',
            'media_type',
            'cover_display_mode',
            'cover_focal_point',
        ], array_keys($response->json('data.work')));
    }

    public function test_draft_and_changes_requested_accept_fill_fit_and_boundaries(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);

        foreach ([Work::STATUS_DRAFT, Work::STATUS_CHANGES_REQUESTED] as $status) {
            $work = $this->work($designer, ['status' => $status]);
            $this->patchJson($this->endpoint($work), $this->payload('fit', 0, 100))
                ->assertOk()
                ->assertJsonPath('data.changed', true)
                ->assertJsonPath('data.work.cover_display_mode', 'fit')
                ->assertJsonPath('data.work.cover_focal_point.x', 0)
                ->assertJsonPath('data.work.cover_focal_point.y', 100);
            $this->patchJson($this->endpoint($work), $this->payload('fill', 100, 0))
                ->assertOk()
                ->assertJsonPath('data.work.cover_display_mode', 'fill');
        }
    }

    public function test_validation_rejects_invalid_modes_ranges_types_and_missing_fields(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer);
        Sanctum::actingAs($designer);

        $this->patchJson($this->endpoint($work), $this->payload('unknown'))
            ->assertUnprocessable()->assertJsonValidationErrors('cover_display_mode');
        $this->patchJson($this->endpoint($work), $this->payload('fill', -1, 50))
            ->assertUnprocessable()->assertJsonValidationErrors('cover_focal_point.x');
        $this->patchJson($this->endpoint($work), $this->payload('fill', 50, 101))
            ->assertUnprocessable()->assertJsonValidationErrors('cover_focal_point.y');
        $this->patchJson($this->endpoint($work), $this->payload('fill', 1.5, 50))
            ->assertUnprocessable()->assertJsonValidationErrors('cover_focal_point.x');
        $this->patchJson($this->endpoint($work), $this->payload('fill', '50', 50))
            ->assertUnprocessable()->assertJsonValidationErrors('cover_focal_point.x');
        $this->patchJson($this->endpoint($work), ['cover_display_mode' => 'fill'])
            ->assertUnprocessable()->assertJsonValidationErrors('cover_focal_point');
        $this->patchJson($this->endpoint($work), ['cover_focal_point' => ['x' => 50, 'y' => 50]])
            ->assertUnprocessable()->assertJsonValidationErrors('cover_display_mode');
    }

    public function test_unknown_top_level_nested_and_query_input_are_rejected(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer);
        Sanctum::actingAs($designer);

        $this->patchJson($this->endpoint($work), [...$this->payload(), 'extra' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('request');
        $this->patchJson($this->endpoint($work), [
            'cover_display_mode' => 'fill',
            'cover_focal_point' => ['x' => 50, 'y' => 50, 'extra' => true],
        ])->assertUnprocessable()->assertJsonValidationErrors('cover_focal_point.request');
        $this->patchJson($this->endpoint($work).'?extra=1', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('request');
        $this->getJson($this->endpoint($work).'?extra=1')
            ->assertUnprocessable()->assertJsonValidationErrors('request');
    }

    public function test_fit_preserves_the_last_focal_point(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, ['cover_focal_x' => 23, 'cover_focal_y' => 77]);
        Sanctum::actingAs($designer);

        $this->patchJson($this->endpoint($work), $this->payload('fit', 23, 77))
            ->assertOk();
        $fresh = $work->fresh();
        $this->assertSame('fit', $fresh->cover_display_mode);
        $this->assertSame(23, $fresh->cover_focal_x);
        $this->assertSame(77, $fresh->cover_focal_y);
    }

    public function test_non_editable_states_return_safe_conflict(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);

        foreach (array_diff($this->statuses(), [Work::STATUS_DRAFT, Work::STATUS_CHANGES_REQUESTED]) as $status) {
            $work = $this->work($designer, ['status' => $status]);
            $this->patchJson($this->endpoint($work), $this->payload('fit'))
                ->assertStatus(409)
                ->assertJsonPath('data.code', 'work_state_not_editable')
                ->assertJsonPath('data.current_status', $status);
        }
    }

    public function test_no_op_preserves_timestamp_and_does_not_write_audit(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer);
        $updatedAt = $work->updated_at;
        Sanctum::actingAs($designer);

        $this->patchJson($this->endpoint($work), $this->payload())
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.changed_keys', []);

        $this->assertTrue($updatedAt->equalTo($work->fresh()->updated_at));
        $this->assertSame(0, AuditEvent::query()
            ->where('event_type', 'works.designer.cover_presentation.updated')
            ->count());
    }

    public function test_change_reports_precise_keys_and_writes_safe_audit(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer);
        Sanctum::actingAs($designer);

        $this->patchJson($this->endpoint($work), $this->payload('fit', 20, 80))
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.changed_keys', [
                'cover_display_mode',
                'cover_focal_point',
            ]);

        $event = AuditEvent::query()
            ->where('event_type', 'works.designer.cover_presentation.updated')
            ->sole();
        $this->assertSame('works', $event->category);
        $this->assertSame('notice', $event->severity);
        $this->assertSame('success', $event->outcome);
        $this->assertSame('update_cover_presentation', $event->action);
        $this->assertSame([
            'work_id',
            'changed_keys',
            'previous_display_mode',
            'display_mode',
            'previous_focal_point',
            'focal_point',
        ], array_keys($event->metadata));
    }

    public function test_audit_failure_rolls_back_all_presentation_values(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, [
            'cover_display_mode' => 'fill',
            'cover_focal_x' => 35,
            'cover_focal_y' => 65,
        ]);
        AuditEvent::creating(static function (): void {
            throw new \RuntimeException('audit unavailable');
        });
        Sanctum::actingAs($designer);

        $this->patchJson($this->endpoint($work), $this->payload('fit', 10, 90))
            ->assertServerError();

        $fresh = $work->fresh();
        $this->assertSame('fill', $fresh->cover_display_mode);
        $this->assertSame(35, $fresh->cover_focal_x);
        $this->assertSame(65, $fresh->cover_focal_y);
    }

    private function endpoint(Work $work): string
    {
        return "/api/designer/works/{$work->id}/presentation";
    }

    private function payload(
        string $mode = 'fill',
        int|float|string $x = 50,
        int|float|string $y = 50,
    ): array {
        return [
            'cover_display_mode' => $mode,
            'cover_focal_point' => ['x' => $x, 'y' => $y],
        ];
    }

    private function work(User $designer, array $attributes = []): Work
    {
        return Work::factory()->create([
            'designer_id' => $designer->id,
            'status' => Work::STATUS_DRAFT,
            ...$attributes,
        ]);
    }

    private function designer(array $attributes = []): User
    {
        return $this->userWithRole('designer', $attributes);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        Role::query()->firstOrCreate(['name' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function statuses(): array
    {
        return [
            Work::STATUS_DRAFT,
            Work::STATUS_SUBMITTED,
            Work::STATUS_IN_REVIEW,
            Work::STATUS_CHANGES_REQUESTED,
            Work::STATUS_APPROVED,
            Work::STATUS_PUBLISHED,
            Work::STATUS_REJECTED,
            Work::STATUS_HIDDEN,
            Work::STATUS_ARCHIVED,
        ];
    }
}
