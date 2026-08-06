<?php

declare(strict_types=1);

namespace Tests\Feature\Designer;

use App\Models\AuditEvent;
use App\Models\DesignerProfile;
use App\Models\DesignerProfileFeaturedWork;
use App\Models\User;
use App\Models\Work;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesignerProfileFeaturedWorksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthRolesSeeder::class);
    }

    public function test_guest_non_designer_and_disabled_designer_are_denied(): void
    {
        $this->getJson($this->endpoint())->assertUnauthorized();

        $client = User::factory()->create();
        $client->assignRole('client');
        Sanctum::actingAs($client);
        $this->getJson($this->endpoint())->assertForbidden();

        [$disabled] = $this->designerWithProfile([
            'disabled_at' => now(),
        ]);
        Sanctum::actingAs($disabled);
        $this->getJson($this->endpoint())->assertForbidden();
    }

    public function test_get_returns_empty_selection_and_only_owned_public_eligible_works(): void
    {
        [$designer] = $this->designerWithProfile();
        $older = $this->publicWork($designer, [
            'published_at' => now()->subDay(),
        ]);
        $newer = $this->publicWork($designer, [
            'published_at' => now(),
        ]);

        Work::factory()->published()->create([
            'designer_id' => $designer->id,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
        ]);

        [$foreignDesigner] = $this->designerWithProfile();
        $this->publicWork($foreignDesigner);

        Sanctum::actingAs($designer);

        $response = $this->getJson($this->endpoint())
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.limit', 6)
            ->assertJsonPath('data.selected', []);

        $this->assertSame(
            [$newer->id, $older->id],
            collect($response->json('data.eligible'))->pluck('id')->all(),
        );
    }

    public function test_request_contract_rejects_query_extra_fields_missing_version_limit_and_duplicates(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        $work = $this->publicWork($designer);
        Sanctum::actingAs($designer);

        $this->getJson($this->endpoint().'?public=1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unsupported_request_input');

        $this->putJson($this->endpoint().'?force=1', $this->payload($profile, []))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unsupported_request_input');

        $this->putJson($this->endpoint(), [
            ...$this->payload($profile, []),
            'designer_id' => $designer->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('unsupported_request_input');

        $this->putJson($this->endpoint(), ['work_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expected_updated_at');

        $this->putJson(
            $this->endpoint(),
            $this->payload($profile, range(1, 7)),
        )->assertUnprocessable()
            ->assertJsonValidationErrors('work_ids');

        $this->putJson(
            $this->endpoint(),
            $this->payload($profile, [$work->id, $work->id]),
        )->assertUnprocessable()
            ->assertJsonValidationErrors('work_ids.1');
    }

    public function test_update_saves_manual_order_touches_profile_and_records_allowlisted_audit(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        $older = $this->publicWork($designer, [
            'published_at' => now()->subDays(2),
        ]);
        $middle = $this->publicWork($designer, [
            'published_at' => now()->subDay(),
        ]);
        $newest = $this->publicWork($designer, [
            'published_at' => now(),
        ]);

        Sanctum::actingAs($designer);
        $this->travel(1)->second();

        $response = $this->putJson(
            $this->endpoint(),
            $this->payload($profile, [$older->id, $newest->id]),
        )
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.limit', 6);

        $this->assertSame(
            [$older->id, $newest->id],
            collect($response->json('data.selected'))->pluck('id')->all(),
        );
        $this->assertSame(
            [$newest->id, $middle->id, $older->id],
            collect($response->json('data.eligible'))->pluck('id')->all(),
        );
        $this->assertSame(
            [0, 1],
            DesignerProfileFeaturedWork::query()
                ->where('designer_profile_id', $profile->id)
                ->orderBy('position')
                ->pluck('position')
                ->all(),
        );

        $this->assertFalse(
            $profile->updated_at->equalTo($profile->fresh()->updated_at),
        );

        $event = AuditEvent::query()->sole();

        $this->assertSame(
            'designer.profile.featured_works.updated',
            $event->event_type,
        );
        $this->assertSame('designer_profiles', $event->category);
        $this->assertSame('designer_profile', $event->target_type);
        $this->assertSame('update_featured_works', $event->action);
        $this->assertEqualsCanonicalizing([
            'profile_id',
            'previous_count',
            'current_count',
            'previous_work_ids',
            'current_work_ids',
            'reordered_only',
        ], array_keys($event->metadata));
        $this->assertSame([], $event->metadata['previous_work_ids']);
        $this->assertSame(
            [$older->id, $newest->id],
            $event->metadata['current_work_ids'],
        );
    }

    public function test_foreign_or_non_public_work_is_rejected_without_leaking_or_changing_state(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        [$foreignDesigner] = $this->designerWithProfile();

        $valid = $this->publicWork($designer);
        $foreign = $this->publicWork($foreignDesigner);
        $hidden = Work::factory()->published()->create([
            'designer_id' => $designer->id,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
        ]);

        Sanctum::actingAs($designer);

        foreach ([$foreign->id, $hidden->id, 999999] as $invalidId) {
            $this->putJson(
                $this->endpoint(),
                $this->payload($profile->fresh(), [$valid->id, $invalidId]),
            )
                ->assertUnprocessable()
                ->assertJsonValidationErrors('work_ids');
        }

        $this->assertSame(0, DesignerProfileFeaturedWork::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_version_conflict_changes_nothing_and_creates_no_audit(): void
    {
        [$designer] = $this->designerWithProfile();
        $work = $this->publicWork($designer);
        Sanctum::actingAs($designer);

        $this->putJson($this->endpoint(), [
            'expected_updated_at' => '2000-01-01T00:00:00+00:00',
            'work_ids' => [$work->id],
        ])
            ->assertConflict()
            ->assertJsonPath(
                'data.code',
                'designer_profile_version_conflict',
            );

        $this->assertSame(0, DesignerProfileFeaturedWork::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_no_op_preserves_profile_timestamp_rows_and_audit_count(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        $first = $this->publicWork($designer);
        $second = $this->publicWork($designer);

        Sanctum::actingAs($designer);
        $this->travel(1)->second();

        $this->putJson(
            $this->endpoint(),
            $this->payload($profile, [$second->id, $first->id]),
        )->assertOk();

        $profile = $profile->fresh();
        $before = $profile->updated_at;
        $rowIds = DesignerProfileFeaturedWork::query()
            ->orderBy('position')
            ->pluck('id')
            ->all();
        $auditCount = AuditEvent::query()->count();

        $this->putJson(
            $this->endpoint(),
            $this->payload($profile, [$second->id, $first->id]),
        )
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertTrue($before->equalTo($profile->fresh()->updated_at));
        $this->assertSame(
            $rowIds,
            DesignerProfileFeaturedWork::query()
                ->orderBy('position')
                ->pluck('id')
                ->all(),
        );
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    public function test_empty_selection_clears_all_featured_works_and_updates_profile_version(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        $first = $this->publicWork($designer);
        $second = $this->publicWork($designer);

        Sanctum::actingAs($designer);
        $this->travel(1)->second();

        $this->putJson(
            $this->endpoint(),
            $this->payload($profile, [$first->id, $second->id]),
        )
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $profile = $profile->fresh();
        $beforeClear = $profile->updated_at;

        $this->assertSame(
            [$first->id, $second->id],
            DesignerProfileFeaturedWork::query()
                ->where('designer_profile_id', $profile->id)
                ->orderBy('position')
                ->pluck('work_id')
                ->all(),
        );

        $this->travel(1)->second();

        $response = $this->putJson(
            $this->endpoint(),
            $this->payload($profile, []),
        )
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.selected', []);

        $this->assertSame(
            0,
            DesignerProfileFeaturedWork::query()
                ->where('designer_profile_id', $profile->id)
                ->count(),
        );

        $this->assertFalse(
            $beforeClear->equalTo($profile->fresh()->updated_at),
        );

        $event = AuditEvent::query()
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            'designer.profile.featured_works.updated',
            $event->event_type,
        );
        $this->assertSame(2, $event->metadata['previous_count']);
        $this->assertSame(0, $event->metadata['current_count']);
        $this->assertSame([], $event->metadata['current_work_ids']);

        $this->assertSame([], $response->json('data.selected'));
    }

    public function test_audit_failure_rolls_back_selection_and_profile_version(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        $old = $this->publicWork($designer);
        $next = $this->publicWork($designer);

        DesignerProfileFeaturedWork::query()->create([
            'designer_profile_id' => $profile->id,
            'work_id' => $old->id,
            'position' => 0,
        ]);

        $before = $profile->fresh()->updated_at;

        AuditEvent::creating(static function (): void {
            throw new \RuntimeException('audit unavailable');
        });

        Sanctum::actingAs($designer);
        $this->travel(1)->second();

        $this->putJson(
            $this->endpoint(),
            $this->payload($profile->fresh(), [$next->id]),
        )->assertServerError();

        $this->assertSame(
            [$old->id],
            DesignerProfileFeaturedWork::query()
                ->orderBy('position')
                ->pluck('work_id')
                ->all(),
        );
        $this->assertTrue($before->equalTo($profile->fresh()->updated_at));
    }

    public function test_losing_public_eligibility_or_ownership_removes_selection(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        [$foreignDesigner] = $this->designerWithProfile();

        $visibilityWork = $this->publicWork($designer);
        $statusWork = $this->publicWork($designer);
        $ownershipWork = $this->publicWork($designer);

        foreach (
            [$visibilityWork, $statusWork, $ownershipWork] as $position => $work
        ) {
            DesignerProfileFeaturedWork::query()->create([
                'designer_profile_id' => $profile->id,
                'work_id' => $work->id,
                'position' => $position,
            ]);
        }

        $beforeVisibility = $profile->fresh()->updated_at;
        $this->travel(1)->second();

        $visibilityWork->forceFill([
            'visibility_status' => Work::VISIBILITY_HIDDEN,
        ])->save();

        $afterVisibility = $profile->fresh()->updated_at;

        $this->assertFalse(
            $beforeVisibility->equalTo($afterVisibility),
        );
        $this->assertDatabaseMissing(
            'designer_profile_featured_works',
            ['work_id' => $visibilityWork->id],
        );

        $this->travel(1)->second();

        $statusWork->forceFill([
            'status' => Work::STATUS_ARCHIVED,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
        ])->save();

        $afterStatus = $profile->fresh()->updated_at;

        $this->assertFalse(
            $afterVisibility->equalTo($afterStatus),
        );
        $this->assertDatabaseMissing(
            'designer_profile_featured_works',
            ['work_id' => $statusWork->id],
        );

        $this->travel(1)->second();

        $ownershipWork->forceFill([
            'designer_id' => $foreignDesigner->id,
        ])->save();

        $afterOwnership = $profile->fresh()->updated_at;

        $this->assertFalse(
            $afterStatus->equalTo($afterOwnership),
        );

        $this->assertSame(
            0,
            DesignerProfileFeaturedWork::query()->count(),
        );
        $this->assertSame(0, AuditEvent::query()->count());
    }

    private function endpoint(): string
    {
        return '/api/designer/profile/featured-works';
    }

    /**
     * @param  array<string, mixed>  $userAttributes
     * @return array{User, DesignerProfile}
     */
    private function designerWithProfile(
        array $userAttributes = [],
    ): array {
        $designer = User::factory()->create($userAttributes);
        $designer->assignRole('designer');

        $profile = $designer->designerProfile()->create([
            'display_name' => 'مصمم اختبار',
            'professional_title' => 'مصمم',
            'primary_specialty' => 'تصميم',
            'bio' => 'نبذة اختبارية.',
        ]);

        return [$designer, $profile];
    }

    /** @param array<string, mixed> $attributes */
    private function publicWork(
        User $designer,
        array $attributes = [],
    ): Work {
        return Work::factory()->published()->create([
            'designer_id' => $designer->id,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'published_at' => now(),
            ...$attributes,
        ]);
    }

    /** @param list<int> $workIds */
    private function payload(
        DesignerProfile $profile,
        array $workIds,
    ): array {
        return [
            'expected_updated_at' => $profile->updated_at?->toJSON(),
            'work_ids' => $workIds,
        ];
    }
}
