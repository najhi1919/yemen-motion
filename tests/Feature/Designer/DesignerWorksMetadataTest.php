<?php

namespace Tests\Feature\Designer;

use App\Http\Controllers\Api\DesignerWorksMetadataController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkTag;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DesignerWorksMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_metadata_routes_resolve_to_expected_controller_and_methods(): void
    {
        $show = Route::getRoutes()->getByName('designer.works.metadata.show');
        $update = Route::getRoutes()->getByName('designer.works.metadata.update');

        $this->assertSame(DesignerWorksMetadataController::class.'@show', $show?->getActionName());
        $this->assertSame(['GET', 'HEAD'], $show?->methods());
        $this->assertSame(DesignerWorksMetadataController::class.'@update', $update?->getActionName());
        $this->assertSame(['PATCH'], $update?->methods());
    }

    public function test_put_and_delete_are_not_supported(): void
    {
        $designer = $this->designer();
        $work = Work::factory()->create(['designer_id' => $designer->id]);
        Sanctum::actingAs($designer);

        $this->putJson($this->endpoint($work), ['category_id' => null, 'tag_ids' => []])
            ->assertMethodNotAllowed();
        $this->deleteJson($this->endpoint($work))->assertMethodNotAllowed();
    }

    public function test_guest_client_staff_and_disabled_designer_are_denied(): void
    {
        $owner = $this->designer();
        $work = Work::factory()->create(['designer_id' => $owner->id]);
        $this->getJson($this->endpoint($work))->assertUnauthorized();

        foreach (['client', 'staff'] as $role) {
            Sanctum::actingAs($this->userWithRole($role));
            $this->getJson($this->endpoint($work))->assertForbidden();
        }

        $disabled = $this->designer(['disabled_at' => now()]);
        $disabledWork = Work::factory()->create(['designer_id' => $disabled->id]);
        Sanctum::actingAs($disabled);
        $this->getJson($this->endpoint($disabledWork))->assertForbidden();
    }

    public function test_designer_reads_only_owned_metadata_and_multi_role_does_not_bypass_ownership(): void
    {
        $designer = $this->designer();
        $designer->assignRole('super-admin');
        $owned = Work::factory()->create(['designer_id' => $designer->id]);
        $foreign = Work::factory()->create(['designer_id' => $this->designer()->id]);
        Sanctum::actingAs($designer);

        $this->getJson($this->endpoint($owned))
            ->assertOk()
            ->assertJsonPath('data.work.public_code', $owned->public_code)
            ->assertJsonMissingPath('data.work.designer_id')
            ->assertJsonMissingPath('data.work.reviewer_id');
        $this->getJson($this->endpoint($foreign))->assertNotFound();
        $this->patchJson($this->endpoint($foreign), ['category_id' => null, 'tag_ids' => []])
            ->assertNotFound();
    }

    public function test_reading_is_available_in_all_states_and_editable_tracks_state(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);

        foreach ($this->statuses() as $status) {
            $work = Work::factory()->create(['designer_id' => $designer->id, 'status' => $status]);
            $this->getJson($this->endpoint($work))
                ->assertOk()
                ->assertJsonPath(
                    'data.metadata_state.editable',
                    in_array($status, [Work::STATUS_DRAFT, Work::STATUS_CHANGES_REQUESTED], true),
                );
        }
    }

    public function test_options_are_active_sorted_and_include_assigned_disabled_values(): void
    {
        $designer = $this->designer();
        $active = $this->category('active', true, 20);
        $first = $this->category('first', true, 10);
        $disabled = $this->category('disabled', false, 1);
        $activeTag = $this->tag('active-tag', true, 20);
        $disabledTag = $this->tag('disabled-tag', false, 1);
        $work = Work::factory()->create([
            'designer_id' => $designer->id,
            'category_id' => $disabled->id,
        ]);
        $work->tags()->sync([$activeTag->id, $disabledTag->id]);
        Sanctum::actingAs($designer);

        $response = $this->getJson($this->endpoint($work))->assertOk();
        $this->assertSame(
            [$disabled->id, $first->id, $active->id],
            $response->json('data.options.categories.*.id'),
        );
        $this->assertContains($disabledTag->id, $response->json('data.options.tags.*.id'));
        $response->assertJsonPath('data.metadata_state.category_tracking.is_disabled', true);
    }

    public function test_category_tracking_handles_uncategorized_and_legacy_unmapped_values(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);
        $uncategorized = Work::factory()->create(['designer_id' => $designer->id, 'category_id' => null]);
        $legacy = Work::factory()->create(['designer_id' => $designer->id, 'category_id' => 999999]);

        $this->getJson($this->endpoint($uncategorized))
            ->assertJsonPath('data.metadata_state.category_tracking.is_uncategorized', true);
        $this->getJson($this->endpoint($legacy))
            ->assertJsonPath('data.work.category', null)
            ->assertJsonPath('data.metadata_state.category_tracking.is_legacy_unmapped', true);
    }

    public function test_draft_and_changes_requested_can_update_category_and_tags(): void
    {
        $designer = $this->designer();
        $category = $this->category('motion');
        $tagA = $this->tag('branding');
        $tagB = $this->tag('animation');
        Sanctum::actingAs($designer);

        foreach ([Work::STATUS_DRAFT, Work::STATUS_CHANGES_REQUESTED] as $status) {
            $work = Work::factory()->create(['designer_id' => $designer->id, 'status' => $status]);
            $this->patchJson($this->endpoint($work), [
                'category_id' => $category->id,
                'tag_ids' => [$tagA->id, $tagB->id],
            ])->assertOk()
                ->assertJsonPath('data.changed', true)
                ->assertJsonPath('data.work.category_id', $category->id);
            $this->assertEqualsCanonicalizing(
                [$tagA->id, $tagB->id],
                $work->fresh()->tags()->pluck('work_tags.id')->all(),
            );
        }
    }

    public function test_non_editable_states_return_safe_conflict(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);

        foreach (array_diff($this->statuses(), [Work::STATUS_DRAFT, Work::STATUS_CHANGES_REQUESTED]) as $status) {
            $work = Work::factory()->create(['designer_id' => $designer->id, 'status' => $status]);
            $this->patchJson($this->endpoint($work), ['category_id' => null, 'tag_ids' => []])
                ->assertStatus(409);
        }
    }

    public function test_category_can_be_removed_and_legacy_value_can_be_kept_or_replaced(): void
    {
        $designer = $this->designer();
        $active = $this->category('replacement');
        Sanctum::actingAs($designer);

        $work = Work::factory()->create(['designer_id' => $designer->id, 'category_id' => $active->id]);
        $this->patchJson($this->endpoint($work), ['category_id' => null, 'tag_ids' => []])
            ->assertOk();
        $this->assertNull($work->fresh()->category_id);

        $legacy = Work::factory()->create(['designer_id' => $designer->id, 'category_id' => 999999]);
        $this->patchJson($this->endpoint($legacy), ['category_id' => 999999, 'tag_ids' => []])
            ->assertOk()->assertJsonPath('data.changed', false);
        $this->patchJson($this->endpoint($legacy), ['category_id' => $active->id, 'tag_ids' => []])
            ->assertOk();
    }

    public function test_disabled_new_taxonomy_is_rejected_but_assigned_disabled_tag_can_remain_or_be_removed(): void
    {
        $designer = $this->designer();
        $disabledCategory = $this->category('disabled-category', false);
        $disabledTag = $this->tag('disabled-tag', false);
        $work = Work::factory()->create(['designer_id' => $designer->id]);
        Sanctum::actingAs($designer);

        $this->patchJson($this->endpoint($work), [
            'category_id' => $disabledCategory->id,
            'tag_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->patchJson($this->endpoint($work), [
            'category_id' => null,
            'tag_ids' => [$disabledTag->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('tag_ids');

        $work->tags()->sync([$disabledTag->id]);
        $this->patchJson($this->endpoint($work), [
            'category_id' => null,
            'tag_ids' => [$disabledTag->id],
        ])->assertOk();
        $this->patchJson($this->endpoint($work), ['category_id' => null, 'tag_ids' => []])
            ->assertOk();
    }

    public function test_validation_rejects_duplicates_limits_unknown_input_query_and_public_code(): void
    {
        $designer = $this->designer();
        $work = Work::factory()->create(['designer_id' => $designer->id]);
        $tags = collect(range(1, 11))->map(fn (int $number) => $this->tag("tag-{$number}"));
        Sanctum::actingAs($designer);

        $this->patchJson($this->endpoint($work), [
            'category_id' => null,
            'tag_ids' => [$tags[0]->id, $tags[0]->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('tag_ids.1');
        $this->patchJson($this->endpoint($work), [
            'category_id' => null,
            'tag_ids' => $tags->pluck('id')->all(),
        ])->assertUnprocessable()->assertJsonValidationErrors('tag_ids');
        $this->patchJson($this->endpoint($work), [
            'category_id' => null,
            'tag_ids' => [],
            'public_code' => 'YM-W-AAAAAAAAAA',
        ])->assertUnprocessable()->assertJsonValidationErrors('request');
        $this->patchJson($this->endpoint($work).'?extra=1', [
            'category_id' => null,
            'tag_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('request');
    }

    public function test_no_op_preserves_timestamp_and_audit_but_change_writes_one_safe_event(): void
    {
        $designer = $this->designer();
        $work = Work::factory()->create(['designer_id' => $designer->id]);
        $updatedAt = $work->updated_at;
        Sanctum::actingAs($designer);

        $this->patchJson($this->endpoint($work), ['category_id' => null, 'tag_ids' => []])
            ->assertOk()->assertJsonPath('data.changed', false);
        $this->assertTrue($updatedAt->equalTo($work->fresh()->updated_at));
        $this->assertSame(0, AuditEvent::query()->where('event_type', 'works.designer.metadata.updated')->count());

        $category = $this->category('changed');
        $this->patchJson($this->endpoint($work), ['category_id' => $category->id, 'tag_ids' => []])
            ->assertOk();
        $event = AuditEvent::query()->where('event_type', 'works.designer.metadata.updated')->sole();
        $this->assertSame('works', $event->category);
        $this->assertSame($work->id, $event->metadata['work_id']);
        $this->assertArrayNotHasKey('payload', $event->metadata);
    }

    public function test_audit_failure_rolls_back_category_and_tag_changes(): void
    {
        $designer = $this->designer();
        $category = $this->category('rollback-category');
        $tag = $this->tag('rollback-tag');
        $work = Work::factory()->create(['designer_id' => $designer->id]);
        AuditEvent::creating(static function (): void {
            throw new \RuntimeException('audit unavailable');
        });
        Sanctum::actingAs($designer);

        $this->patchJson($this->endpoint($work), [
            'category_id' => $category->id,
            'tag_ids' => [$tag->id],
        ])->assertServerError();

        $this->assertNull($work->fresh()->category_id);
        $this->assertSame([], $work->fresh()->tags()->pluck('work_tags.id')->all());
    }

    private function endpoint(Work $work): string
    {
        return "/api/designer/works/{$work->id}/metadata";
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

    private function category(string $slug, bool $active = true, int $sort = 0): WorkCategory
    {
        return WorkCategory::query()->create([
            'name_ar' => "تصنيف {$slug}",
            'name_en' => $slug,
            'slug' => $slug,
            'disabled_at' => $active ? null : now(),
            'sort_order' => $sort,
        ]);
    }

    private function tag(string $slug, bool $active = true, int $sort = 0): WorkTag
    {
        return WorkTag::query()->create([
            'name_ar' => "وسم {$slug}",
            'name_en' => $slug,
            'slug' => $slug,
            'disabled_at' => $active ? null : now(),
            'sort_order' => $sort,
        ]);
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
