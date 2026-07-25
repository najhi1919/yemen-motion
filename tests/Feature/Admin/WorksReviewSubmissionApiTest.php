<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkMedia;
use App\Models\WorkSetting;
use App\Models\WorkTag;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksReviewSubmissionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthRolesSeeder::class);
    }

    public function test_complete_draft_is_ready_with_non_blocking_warnings_and_safe_contract(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->readyImageWork();

        $response = $this->getJson($this->readinessUrl($work))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.readiness.ready', true)
            ->assertJsonPath('data.readiness.blockers_count', 0);

        $this->assertGreaterThan(0, $response->json('data.readiness.warnings_count'));
        $this->assertArrayNotHasKey('path', $response->json('data.readiness'));
        $this->assertArrayNotHasKey('disk', $response->json('data.readiness'));
    }

    public function test_required_content_and_media_type_blockers_are_reported_by_stable_codes(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create([
            'title' => ' ',
            'summary' => null,
            'description' => '',
            'media_type' => null,
        ]);

        $codes = $this->readinessCodes($work);

        foreach (['title_missing', 'summary_missing', 'description_missing', 'media_type_missing'] as $code) {
            $this->assertContains($code, $codes);
        }
    }

    public function test_category_missing_inactive_and_invalid_are_blocking(): void
    {
        $this->actingAsRole('super-admin');
        $missing = $this->readyImageWork(['category_id' => null]);
        $this->assertContains('category_missing', $this->readinessCodes($missing));

        $inactive = WorkCategory::factory()->disabled()->create();
        $work = $this->readyImageWork(['category_id' => $inactive->id]);
        $this->assertContains('category_inactive', $this->readinessCodes($work));
    }

    public function test_media_missing_pending_failed_and_mismatch_are_blocking(): void
    {
        $this->actingAsRole('super-admin');
        $missing = $this->baseWork(['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $this->assertContains('media_missing', $this->readinessCodes($missing));

        $pending = $this->baseWork(['media_type' => Work::MEDIA_TYPE_VIDEO]);
        WorkMedia::factory()->video()->create(['work_id' => $pending->id]);
        $this->assertContains('media_processing_pending', $this->readinessCodes($pending));

        $failed = $this->baseWork(['media_type' => Work::MEDIA_TYPE_VIDEO]);
        WorkMedia::factory()->video()->failed()->create(['work_id' => $failed->id]);
        $this->assertContains('media_processing_failed', $this->readinessCodes($failed));

        $mismatch = $this->baseWork(['media_type' => Work::MEDIA_TYPE_VIDEO]);
        WorkMedia::factory()->image()->ready()->create(['work_id' => $mismatch->id]);
        $this->assertContains('media_type_mismatch', $this->readinessCodes($mismatch));
    }

    public function test_image_requires_a_valid_ready_cover_while_video_does_not(): void
    {
        $this->actingAsRole('super-admin');
        $image = $this->baseWork(['media_type' => Work::MEDIA_TYPE_IMAGE]);
        WorkMedia::factory()->image()->ready()->create(['work_id' => $image->id]);
        $this->assertContains('cover_missing', $this->readinessCodes($image));

        $video = $this->baseWork(['media_type' => Work::MEDIA_TYPE_VIDEO]);
        WorkMedia::factory()->video()->ready()->create(['work_id' => $video->id]);
        $this->assertNotContains('cover_missing', $this->readinessCodes($video));

        $invalidCover = $this->baseWork(['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $videoMedia = WorkMedia::factory()->video()->ready()->create(['work_id' => $invalidCover->id]);
        $invalidCover->update(['cover_media_id' => $videoMedia->id]);
        $this->assertContains('cover_invalid', $this->readinessCodes($invalidCover));

        $pendingCover = $this->baseWork(['media_type' => Work::MEDIA_TYPE_IMAGE]);
        $pendingImage = WorkMedia::factory()->image()->create(['work_id' => $pendingCover->id]);
        $pendingCover->update(['cover_media_id' => $pendingImage->id]);
        $this->assertContains('cover_not_ready', $this->readinessCodes($pendingCover));
    }

    public function test_effective_media_item_limit_is_enforced_by_readiness(): void
    {
        $this->actingAsRole('super-admin');
        $setting = WorkSetting::query()
            ->where('scope', WorkSetting::SCOPE_GLOBAL)
            ->first() ?? new WorkSetting(['scope' => WorkSetting::SCOPE_GLOBAL]);

        $setting->forceFill([
            'version' => 2,
            'values' => [
                'review_sla_hours' => null,
                'direct_publish_trust_enabled' => false,
                'media_limits' => [
                    'max_items' => 1,
                    'max_file_size_kb' => null,
                    'allowed_types' => [Work::MEDIA_TYPE_GALLERY],
                ],
            ],
        ])->save();
        $work = $this->baseWork(['media_type' => Work::MEDIA_TYPE_GALLERY]);
        $cover = WorkMedia::factory()->image()->ready()->create(['work_id' => $work->id, 'position' => 1]);
        WorkMedia::factory()->image()->ready()->create(['work_id' => $work->id, 'position' => 2]);
        $work->update(['cover_media_id' => $cover->id]);

        $this->assertContains('media_limit_exceeded', $this->readinessCodes($work));
    }

    public function test_optional_designer_tags_price_and_delivery_are_warnings_not_blockers(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->readyImageWork([
            'designer_id' => null,
            'price_amount' => null,
            'delivery_days' => null,
        ]);

        $response = $this->getJson($this->readinessUrl($work))->assertOk();
        $this->assertTrue($response->json('data.readiness.ready'));
        $codes = collect($response->json('data.readiness.sections'))
            ->flatMap(fn (array $section) => $section['items'])
            ->where('severity', 'warning')
            ->pluck('code')
            ->all();

        foreach (['designer_missing', 'tags_missing', 'price_missing', 'delivery_missing'] as $code) {
            $this->assertContains($code, $codes);
        }
    }

    public function test_zero_price_and_zero_counters_remain_numeric_and_do_not_hide_readiness(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->readyImageWork([
            'price_amount' => 0,
            'views_count' => 0,
            'likes_count' => 0,
            'reports_count' => 0,
        ]);

        $response = $this->getJson($this->readinessUrl($work))->assertOk();
        $this->assertIsInt($response->json('data.readiness.blockers_count'));
        $this->assertIsInt($response->json('data.readiness.warnings_count'));
        $warningCodes = collect($response->json('data.readiness.sections'))
            ->flatMap(fn (array $section) => $section['items'])
            ->where('severity', 'warning')
            ->pluck('code')
            ->all();
        $this->assertNotContains('price_missing', $warningCodes);
    }

    public function test_draft_submission_changes_only_review_lifecycle_fields_and_records_audit_once(): void
    {
        $actor = $this->actingAsRole('super-admin');
        $work = $this->readyImageWork();
        $categoryId = $work->category_id;
        $mediaIds = $work->media()->pluck('id')->all();

        $this->patchJson($this->submitUrl($work), [
            'expected_updated_at' => $work->updated_at->toJSON(),
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'submit')
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.resubmission', false)
            ->assertJsonPath('data.work.status', Work::STATUS_SUBMITTED)
            ->assertJsonPath('data.work.visibility_status', Work::VISIBILITY_HIDDEN)
            ->assertJsonPath('data.readiness.ready', true);

        $work->refresh();
        $this->assertNotNull($work->submitted_at);
        $this->assertSame($categoryId, $work->category_id);
        $this->assertSame($mediaIds, $work->media()->pluck('id')->all());
        $this->assertDatabaseCount('audit_events', 1);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'works.review.submitted',
            'actor_id' => $actor->id,
            'target_id' => $work->id,
            'action' => 'submit',
        ]);
    }

    public function test_changes_requested_resubmission_retains_reviewer_and_notes_and_resets_decision_fields(): void
    {
        $this->actingAsRole('super-admin');
        $reviewer = User::factory()->create();
        $work = $this->readyImageWork([
            'status' => Work::STATUS_CHANGES_REQUESTED,
            'reviewer_id' => $reviewer->id,
            'change_request_notes' => 'Please refine the presentation.',
            'reviewed_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
            'rejected_at' => now()->subDay(),
            'rejection_reason' => 'Old decision',
            'published_at' => now()->subDay(),
        ]);

        $this->patchJson($this->submitUrl($work), [
            'expected_updated_at' => $work->updated_at->toJSON(),
        ])->assertOk()->assertJsonPath('data.resubmission', true);

        $work->refresh();
        $this->assertSame(Work::STATUS_SUBMITTED, $work->status);
        $this->assertSame($reviewer->id, $work->reviewer_id);
        $this->assertSame('Please refine the presentation.', $work->change_request_notes);
        $this->assertNull($work->reviewed_at);
        $this->assertNull($work->approved_at);
        $this->assertNull($work->rejected_at);
        $this->assertNull($work->rejection_reason);
        $this->assertNull($work->published_at);
    }

    public function test_blockers_and_invalid_status_prevent_submission_without_audit(): void
    {
        $this->actingAsRole('super-admin');
        $incomplete = Work::factory()->create(['title' => '', 'summary' => null, 'description' => null]);
        $this->patchJson($this->submitUrl($incomplete), [
            'expected_updated_at' => $incomplete->updated_at->toJSON(),
        ])->assertUnprocessable()->assertJsonPath('data.readiness.ready', false);

        $submitted = $this->readyImageWork(['status' => Work::STATUS_SUBMITTED]);
        $this->patchJson($this->submitUrl($submitted), [
            'expected_updated_at' => $submitted->updated_at->toJSON(),
        ])->assertConflict()
            ->assertJsonPath('data.current_status', Work::STATUS_SUBMITTED)
            ->assertJsonStructure(['data' => ['current_updated_at', 'readiness']]);

        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_stale_expected_timestamp_returns_409_with_current_state_and_readiness(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->readyImageWork();

        $this->patchJson($this->submitUrl($work), [
            'expected_updated_at' => '2000-01-01T00:00:00+00:00',
        ])->assertConflict()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.current_status', Work::STATUS_DRAFT)
            ->assertJsonStructure(['data' => ['current_updated_at', 'readiness']]);
    }

    public function test_exact_permission_and_internal_role_are_required(): void
    {
        $work = $this->readyImageWork();
        $this->actingAsRole('admin', ['admin.works.access']);
        $this->getJson($this->readinessUrl($work))->assertForbidden();

        $this->actingAsRole('staff', ['admin.works.access', 'admin.works.review.submit']);
        $this->getJson($this->readinessUrl($work))->assertOk();

        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, ['admin.works.access', 'admin.works.review.submit']);
            $this->getJson($this->readinessUrl($work))->assertForbidden();
        }
    }

    public function test_extra_body_and_query_parameters_are_rejected(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->readyImageWork();

        $this->patchJson($this->submitUrl($work).'?debug=1', [
            'expected_updated_at' => $work->updated_at->toJSON(),
            'status' => Work::STATUS_SUBMITTED,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['debug', 'status']);
    }

    public function test_second_submission_request_cannot_create_an_additional_transition(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->readyImageWork();
        $expected = $work->updated_at->toJSON();

        $this->patchJson($this->submitUrl($work), ['expected_updated_at' => $expected])->assertOk();
        $this->patchJson($this->submitUrl($work), ['expected_updated_at' => $expected])->assertConflict();
        $this->assertSame(1, AuditEvent::query()->where('event_type', 'works.review.submitted')->count());
    }

    private function baseWork(array $overrides = []): Work
    {
        return Work::factory()->create([
            'title' => 'Complete work title',
            'summary' => str_repeat('Summary ', 12),
            'description' => str_repeat('Description ', 25),
            'status' => Work::STATUS_DRAFT,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'media_type' => Work::MEDIA_TYPE_IMAGE,
            'category_id' => WorkCategory::factory(),
            ...$overrides,
        ]);
    }

    private function readyImageWork(array $overrides = []): Work
    {
        $work = $this->baseWork($overrides);
        $media = WorkMedia::factory()->image()->ready()->create(['work_id' => $work->id]);
        if (in_array($work->media_type, [Work::MEDIA_TYPE_IMAGE, Work::MEDIA_TYPE_GALLERY], true)) {
            $work->update(['cover_media_id' => $media->id]);
        }
        if (! array_key_exists('designer_id', $overrides)) {
            $work->update(['designer_id' => User::factory()->create()->id]);
        }
        if (! array_key_exists('price_amount', $overrides)) $work->update(['price_amount' => 125]);
        if (! array_key_exists('delivery_days', $overrides)) $work->update(['delivery_days' => 7]);
        if (($overrides['with_tag'] ?? false) === true) {
            $work->tags()->attach(WorkTag::factory()->create());
        }

        return $work->fresh();
    }

    /** @return list<string> */
    private function readinessCodes(Work $work): array
    {
        return collect($this->getJson($this->readinessUrl($work))->assertOk()->json('data.readiness.sections'))
            ->flatMap(fn (array $section) => $section['items'])
            ->pluck('code')
            ->all();
    }

    private function readinessUrl(Work $work): string
    {
        return '/api/admin/works/'.$work->id.'/review/readiness';
    }

    private function submitUrl(Work $work): string
    {
        return '/api/admin/works/'.$work->id.'/review/submit';
    }

    /** @param list<string> $permissions */
    private function actingAsRole(string $role, array $permissions = []): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);
        if ($permissions !== []) $user->givePermissionTo($permissions);
        Sanctum::actingAs($user);

        return $user;
    }
}
