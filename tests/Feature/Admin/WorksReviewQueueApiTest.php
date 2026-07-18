<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksReviewQueueController;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkSetting;
use Carbon\Carbon;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorksReviewQueueApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/admin/works/review')
            ->assertUnauthorized();
    }

    public function test_super_admin_can_list_review_queue(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->submitted()->count(2)->create();

        $this->getJson('/api/admin/works/review')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_admin_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/admin/works/review')
            ->assertForbidden();
    }

    public function test_staff_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/admin/works/review')
            ->assertForbidden();
    }

    public function test_admin_with_access_only_gets_403(): void
    {
        $this->actingAsRole('admin', ['admin.works.access']);

        $this->getJson('/api/admin/works/review')
            ->assertForbidden();
    }

    public function test_admin_and_staff_with_required_permissions_can_list(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, $this->reviewPermissions());

            $this->getJson('/api/admin/works/review')
                ->assertOk()
                ->assertJsonPath('success', true);
        }
    }

    public function test_client_and_designer_with_accidental_permissions_get_403(): void
    {
        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $this->reviewPermissions());

            $this->getJson('/api/admin/works/review')
                ->assertForbidden();
        }
    }

    public function test_queue_contains_only_the_three_review_statuses(): void
    {
        $this->actingAsRole('super-admin');
        $included = [
            Work::factory()->submitted()->create(),
            Work::factory()->inReview()->create(),
            Work::factory()->create([
                'status' => Work::STATUS_CHANGES_REQUESTED,
                'submitted_at' => now()->subDay(),
            ]),
        ];

        Work::factory()->create();
        Work::factory()->approved()->create();
        Work::factory()->published()->create();
        Work::factory()->rejected()->create();
        Work::factory()->hidden()->create();
        Work::factory()->archived()->create();

        $response = $this->getJson($this->endpoint(['per_page' => 50]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonCount(3, 'data.items');

        $this->assertSame(
            collect($included)->pluck('id')->sort()->values()->all(),
            collect($response->json('data.items'))->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_response_uses_safe_paginated_summary_and_filter_shape(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create(['name' => 'Review Designer']);
        $reviewer = User::factory()->create(['name' => 'Review Assignee']);
        Work::factory()->create([
            'title' => 'Review Queue Work',
            'slug' => 'review-queue-work',
            'summary' => 'Safe review summary.',
            'status' => Work::STATUS_IN_REVIEW,
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
            'submitted_at' => now()->subHours(12),
            'reviewed_at' => null,
        ]);

        $response = $this->getJson('/api/admin/works/review')
            ->assertOk()
            ->assertJsonPath('message', 'تم جلب طلبات مراجعة الأعمال بنجاح')
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.pagination', [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 1,
                'last_page' => 1,
            ])
            ->assertJsonPath('data.summary', [
                'total' => 1,
                'submitted' => 0,
                'in_review' => 1,
                'changes_requested' => 0,
                'assigned' => 1,
                'unassigned' => 0,
                'overdue' => 0,
                'reported' => 0,
            ])
            ->assertJsonPath('data.filters', [
                'q' => null,
                'status' => null,
                'media_type' => null,
                'designer_id' => null,
                'reviewer_id' => null,
                'assigned' => null,
                'overdue' => null,
                'from' => null,
                'to' => null,
                'sort' => 'submitted_at',
                'direction' => 'asc',
            ]);

        $item = $response->json('data.items.0');

        $this->assertSame([
            'category_id',
            'created_at',
            'designer',
            'id',
            'likes_count',
            'media_type',
            'reports_count',
            'review_flags',
            'reviewed_at',
            'reviewer',
            'slug',
            'status',
            'submitted_at',
            'summary',
            'title',
            'updated_at',
            'views_count',
            'visibility_status',
        ], collect(array_keys($item))->sort()->values()->all());
        $this->assertSame(
            ['assigned', 'needs_attention', 'overdue'],
            collect(array_keys($item['review_flags']))->sort()->values()->all(),
        );
        $this->assertSame(
            ['filters', 'items', 'pagination', 'review_policy', 'summary'],
            collect(array_keys($response->json('data')))->sort()->values()->all(),
        );
    }

    public function test_response_does_not_expose_sensitive_work_or_user_fields(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create([
            'name' => 'Visible Review Designer',
            'email' => 'private-review-designer@example.test',
            'password' => 'private-password-marker',
        ]);
        Work::factory()->submitted()->create([
            'designer_id' => $designer->id,
            'description' => 'private-description-marker',
            'internal_notes' => 'private-internal-marker',
            'rejection_reason' => 'private-rejection-marker',
            'change_request_notes' => 'private-change-marker',
        ]);

        $response = $this->getJson('/api/admin/works/review')
            ->assertOk();
        $keys = $this->recursiveKeys($response->json());
        $json = strtolower($response->getContent());

        foreach ([
            'description',
            'internal_notes',
            'rejection_reason',
            'change_request_notes',
            'email',
            'password',
            'token',
            'cookie',
            'metadata',
            'payload',
            'model',
            'rows',
            'user',
            'users',
        ] as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $keys);
        }

        foreach ([
            'private-review-designer@example.test',
            'private-password-marker',
            'private-description-marker',
            'private-internal-marker',
            'private-rejection-marker',
            'private-change-marker',
        ] as $forbiddenValue) {
            $this->assertStringNotContainsString($forbiddenValue, $json);
        }
    }

    public function test_designer_and_reviewer_expose_only_id_and_name(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create([
            'name' => 'Limited Designer',
            'email' => 'limited-designer@example.test',
        ]);
        $reviewer = User::factory()->create([
            'name' => 'Limited Reviewer',
            'email' => 'limited-reviewer@example.test',
        ]);
        Work::factory()->inReview()->create([
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
        ]);

        $response = $this->getJson('/api/admin/works/review')
            ->assertOk()
            ->assertJsonPath('data.items.0.designer', [
                'id' => $designer->id,
                'name' => $designer->name,
            ])
            ->assertJsonPath('data.items.0.reviewer', [
                'id' => $reviewer->id,
                'name' => $reviewer->name,
            ]);

        $this->assertSame(['id', 'name'], array_keys($response->json('data.items.0.designer')));
        $this->assertSame(['id', 'name'], array_keys($response->json('data.items.0.reviewer')));
        $this->assertStringNotContainsString($designer->email, $response->getContent());
        $this->assertStringNotContainsString($reviewer->email, $response->getContent());
    }

    public function test_missing_designer_and_reviewer_are_returned_as_null(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->submitted()->create([
            'designer_id' => null,
            'reviewer_id' => null,
        ]);

        $this->getJson('/api/admin/works/review')
            ->assertOk()
            ->assertJsonPath('data.items.0.designer', null)
            ->assertJsonPath('data.items.0.reviewer', null);
    }

    public function test_search_matches_title_slug_and_summary_only(): void
    {
        $this->actingAsRole('super-admin');
        $titleWork = Work::factory()->submitted()->create([
            'title' => 'Nebula Review Work',
            'slug' => 'ordinary-review-title',
            'summary' => 'Standard summary.',
        ]);
        $slugWork = Work::factory()->submitted()->create([
            'title' => 'Ordinary Slug Review',
            'slug' => 'kinetic-review-target',
            'summary' => 'Another standard summary.',
        ]);
        $summaryWork = Work::factory()->submitted()->create([
            'title' => 'Ordinary Summary Review',
            'slug' => 'ordinary-summary-review',
            'summary' => 'Includes the Mosaic review marker.',
        ]);

        foreach ([
            'Nebula' => $titleWork->id,
            'review-target' => $slugWork->id,
            'Mosaic' => $summaryWork->id,
        ] as $query => $expectedId) {
            $this->getJson($this->endpoint(['q' => $query]))
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 1)
                ->assertJsonPath('data.summary.total', 1)
                ->assertJsonPath('data.items.0.id', $expectedId)
                ->assertJsonPath('data.filters.q', $query);
        }
    }

    public function test_search_does_not_match_description_private_notes_or_user_fields(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create(['email' => 'email-secret-needle@example.test']);
        Work::factory()->submitted()->create([
            'designer_id' => $designer->id,
            'title' => 'Public review title',
            'slug' => 'public-review-title',
            'summary' => 'Public review summary.',
            'description' => 'description-secret-needle',
            'internal_notes' => 'internal-secret-needle',
            'rejection_reason' => 'rejection-secret-needle',
            'change_request_notes' => 'changes-secret-needle',
        ]);

        foreach ([
            'description-secret-needle',
            'internal-secret-needle',
            'rejection-secret-needle',
            'changes-secret-needle',
            'email-secret-needle',
        ] as $query) {
            $this->getJson($this->endpoint(['q' => $query]))
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 0)
                ->assertJsonPath('data.summary.total', 0)
                ->assertJsonPath('data.items', []);
        }
    }

    public function test_status_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->inReview()->create();
        Work::factory()->submitted()->create();
        Work::factory()->create([
            'status' => Work::STATUS_CHANGES_REQUESTED,
            'submitted_at' => now()->subDay(),
        ]);

        $this->assertSingleFilteredWork(['status' => Work::STATUS_IN_REVIEW], $expected);
    }

    public function test_short_search_and_invalid_status_return_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/review?q=x&status=published')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q', 'status']);
    }

    public function test_media_type_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->submitted()->create(['media_type' => 'video']);
        Work::factory()->submitted()->create(['media_type' => 'image']);

        $this->assertSingleFilteredWork(['media_type' => 'video'], $expected);
    }

    public function test_designer_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create();
        $otherDesigner = User::factory()->create();
        $expected = Work::factory()->submitted()->create(['designer_id' => $designer->id]);
        Work::factory()->submitted()->create(['designer_id' => $otherDesigner->id]);

        $this->assertSingleFilteredWork(['designer_id' => $designer->id], $expected);
    }

    public function test_reviewer_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $reviewer = User::factory()->create();
        $otherReviewer = User::factory()->create();
        $expected = Work::factory()->inReview()->create(['reviewer_id' => $reviewer->id]);
        Work::factory()->inReview()->create(['reviewer_id' => $otherReviewer->id]);

        $this->assertSingleFilteredWork(['reviewer_id' => $reviewer->id], $expected);
    }

    public function test_assigned_true_and_false_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        $assigned = Work::factory()->inReview()->create();
        $unassigned = Work::factory()->submitted()->create(['reviewer_id' => null]);

        $this->assertSingleFilteredWork(['assigned' => 1], $assigned);
        $this->assertSingleFilteredWork(['assigned' => 0], $unassigned);
    }

    public function test_overdue_true_and_false_filters_follow_the_full_definition(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $this->actingAsRole('super-admin');
        $this->setReviewSla(48);
        $overdue = Work::factory()->submitted()->create([
            'submitted_at' => now()->subHours(49),
        ]);
        $fresh = Work::factory()->submitted()->create([
            'submitted_at' => now()->subHours(47),
        ]);
        $reviewed = Work::factory()->submitted()->create([
            'submitted_at' => now()->subHours(72),
            'reviewed_at' => now()->subHour(),
        ]);
        $published = Work::factory()->submitted()->create([
            'submitted_at' => now()->subHours(72),
            'published_at' => now()->subHour(),
        ]);
        $rejected = Work::factory()->submitted()->create([
            'submitted_at' => now()->subHours(72),
            'rejected_at' => now()->subHour(),
        ]);
        $archived = Work::factory()->inReview()->create([
            'submitted_at' => now()->subHours(72),
            'archived_at' => now()->subHour(),
        ]);
        $changesRequested = Work::factory()->create([
            'status' => Work::STATUS_CHANGES_REQUESTED,
            'submitted_at' => now()->subHours(72),
        ]);

        $this->assertSingleFilteredWork(['overdue' => 1], $overdue);

        $notOverdue = $this->getJson($this->endpoint([
            'overdue' => 0,
            'per_page' => 50,
        ]))->assertOk();

        $this->assertSame(
            collect([$fresh, $reviewed, $published, $rejected, $archived, $changesRequested])
                ->pluck('id')
                ->sort()
                ->values()
                ->all(),
            collect($notOverdue->json('data.items'))->pluck('id')->sort()->values()->all(),
        );
        $notOverdue->assertJsonPath('data.summary.overdue', 0);
    }

    public function test_from_and_to_filter_submitted_at_and_exclude_null_timestamps(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->submitted()->create(['submitted_at' => '2026-06-30 23:59:59']);
        $expected = Work::factory()->submitted()->create(['submitted_at' => '2026-07-15 10:00:00']);
        Work::factory()->submitted()->create(['submitted_at' => '2026-08-01 00:00:00']);
        Work::factory()->create([
            'status' => Work::STATUS_CHANGES_REQUESTED,
            'submitted_at' => null,
        ]);

        $this->assertSingleFilteredWork([
            'from' => '2026-07-01',
            'to' => '2026-07-31',
        ], $expected);
    }

    public function test_default_sort_is_submitted_at_asc_with_stable_id_tie_breaker(): void
    {
        $this->actingAsRole('super-admin');
        $older = Work::factory()->submitted()->create(['submitted_at' => '2026-07-01 10:00:00']);
        $newerFirst = Work::factory()->submitted()->create(['submitted_at' => '2026-07-10 10:00:00']);
        $newerSecond = Work::factory()->submitted()->create(['submitted_at' => '2026-07-10 10:00:00']);

        $response = $this->getJson('/api/admin/works/review')
            ->assertOk()
            ->assertJsonPath('data.filters.sort', 'submitted_at')
            ->assertJsonPath('data.filters.direction', 'asc');

        $this->assertSame(
            [$older->id, $newerFirst->id, $newerSecond->id],
            collect($response->json('data.items'))->pluck('id')->all(),
        );
    }

    public function test_updated_at_desc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $old = Work::factory()->submitted()->create(['updated_at' => '2026-07-01 10:00:00']);
        $middle = Work::factory()->submitted()->create(['updated_at' => '2026-07-10 10:00:00']);
        $new = Work::factory()->submitted()->create(['updated_at' => '2026-07-14 10:00:00']);

        $response = $this->getJson($this->endpoint([
            'sort' => 'updated_at',
            'direction' => 'desc',
        ]))->assertOk();

        $this->assertSame(
            [$new->id, $middle->id, $old->id],
            collect($response->json('data.items'))->pluck('id')->all(),
        );
    }

    public function test_reports_count_desc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $low = Work::factory()->submitted()->create(['reports_count' => 1]);
        $high = Work::factory()->submitted()->create(['reports_count' => 8]);
        $middle = Work::factory()->submitted()->create(['reports_count' => 3]);

        $response = $this->getJson($this->endpoint([
            'sort' => 'reports_count',
            'direction' => 'desc',
        ]))->assertOk();

        $this->assertSame(
            [$high->id, $middle->id, $low->id],
            collect($response->json('data.items'))->pluck('id')->all(),
        );
    }

    public function test_title_asc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->submitted()->create(['title' => 'Bravo Review']);
        Work::factory()->submitted()->create(['title' => 'Alpha Review']);
        Work::factory()->submitted()->create(['title' => 'Charlie Review']);

        $response = $this->getJson($this->endpoint([
            'sort' => 'title',
            'direction' => 'asc',
        ]))->assertOk();

        $this->assertSame(
            ['Alpha Review', 'Bravo Review', 'Charlie Review'],
            collect($response->json('data.items'))->pluck('title')->all(),
        );
    }

    public function test_pagination_accepts_fifty_and_rejects_values_above_the_limit(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->submitted()->count(55)->create(['designer_id' => null]);

        $this->getJson('/api/admin/works/review?per_page=50')
            ->assertOk()
            ->assertJsonCount(50, 'data.items')
            ->assertJsonPath('data.pagination.per_page', 50)
            ->assertJsonPath('data.pagination.total', 55)
            ->assertJsonPath('data.pagination.last_page', 2);

        $this->getJson('/api/admin/works/review?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_page_parameter_returns_requested_page(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->submitted()->count(5)->create();

        $this->getJson('/api/admin/works/review?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.total', 5)
            ->assertJsonPath('data.pagination.last_page', 3);
    }

    public function test_invalid_sort_and_direction_return_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/review?sort=email&direction=sideways')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'direction']);
    }

    public function test_to_before_from_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/review?from=2026-07-10&to=2026-07-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_range_over_ten_years_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/review?from=2016-07-14&to=2026-07-15')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_unknown_and_sensitive_query_parameters_return_422(): void
    {
        $this->actingAsRole('super-admin');
        $parameters = [
            'unexpected' => 'value',
            'email' => 'private@example.test',
            'password' => 'secret',
            'token' => 'secret',
            'cookie' => 'secret',
            'internal_notes' => 'secret',
            'rejection_reason' => 'secret',
            'change_request_notes' => 'secret',
            'payload' => 'secret',
            'metadata' => 'secret',
            'description' => 'secret',
        ];

        $this->getJson($this->endpoint($parameters))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys($parameters));
    }

    public function test_summary_counts_review_statuses_assignment_overdue_and_reports(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $this->actingAsRole('super-admin');
        $this->setReviewSla(48);
        $reviewer = User::factory()->create();

        Work::factory()->submitted()->create([
            'reviewer_id' => null,
            'submitted_at' => now()->subHours(72),
            'reports_count' => 2,
        ]);
        Work::factory()->submitted()->create([
            'reviewer_id' => $reviewer->id,
            'submitted_at' => now()->subHours(12),
        ]);
        Work::factory()->inReview()->create([
            'reviewer_id' => $reviewer->id,
            'submitted_at' => now()->subHours(72),
        ]);
        Work::factory()->create([
            'status' => Work::STATUS_CHANGES_REQUESTED,
            'reviewer_id' => null,
            'submitted_at' => now()->subHours(96),
            'reports_count' => 1,
        ]);
        Work::factory()->published()->create();

        $this->getJson('/api/admin/works/review')
            ->assertOk()
            ->assertJsonPath('data.summary', [
                'total' => 4,
                'submitted' => 2,
                'in_review' => 1,
                'changes_requested' => 1,
                'assigned' => 2,
                'unassigned' => 2,
                'overdue' => 2,
                'reported' => 2,
            ]);
    }

    public function test_summary_honors_the_same_active_filters_as_items(): void
    {
        $this->actingAsRole('super-admin');
        $reviewer = User::factory()->create();
        $expected = Work::factory()->inReview()->create([
            'reviewer_id' => $reviewer->id,
            'media_type' => 'video',
            'reports_count' => 3,
        ]);
        Work::factory()->inReview()->create([
            'reviewer_id' => $reviewer->id,
            'media_type' => 'image',
        ]);
        Work::factory()->submitted()->create([
            'reviewer_id' => null,
            'media_type' => 'video',
        ]);

        $response = $this->getJson($this->endpoint([
            'status' => Work::STATUS_IN_REVIEW,
            'media_type' => 'video',
            'assigned' => 1,
        ]))->assertOk();

        $this->assertSame($expected->id, $response->json('data.items.0.id'));
        $response->assertJsonPath('data.summary', [
            'total' => 1,
            'submitted' => 0,
            'in_review' => 1,
            'changes_requested' => 0,
            'assigned' => 1,
            'unassigned' => 0,
            'overdue' => 0,
            'reported' => 1,
        ]);
    }

    public function test_review_flags_are_calculated_correctly(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $this->actingAsRole('super-admin');
        $this->setReviewSla(48);
        $reviewer = User::factory()->create();
        $normal = Work::factory()->inReview()->create([
            'reviewer_id' => $reviewer->id,
            'submitted_at' => now()->subHours(12),
            'reports_count' => 0,
        ]);
        $overdue = Work::factory()->submitted()->create([
            'reviewer_id' => null,
            'submitted_at' => now()->subHours(72),
            'reports_count' => 0,
        ]);
        $reported = Work::factory()->submitted()->create([
            'submitted_at' => now()->subHours(12),
            'reports_count' => 1,
        ]);
        $changes = Work::factory()->create([
            'status' => Work::STATUS_CHANGES_REQUESTED,
            'submitted_at' => now()->subHours(12),
            'reports_count' => 0,
        ]);

        $response = $this->getJson('/api/admin/works/review?per_page=50')
            ->assertOk();
        $items = collect($response->json('data.items'))->keyBy('id');

        $this->assertSame([
            'assigned' => true,
            'overdue' => false,
            'needs_attention' => false,
        ], $items->get($normal->id)['review_flags']);
        $this->assertSame([
            'assigned' => false,
            'overdue' => true,
            'needs_attention' => true,
        ], $items->get($overdue->id)['review_flags']);
        $this->assertSame([
            'assigned' => false,
            'overdue' => false,
            'needs_attention' => true,
        ], $items->get($reported->id)['review_flags']);
        $this->assertSame([
            'assigned' => false,
            'overdue' => false,
            'needs_attention' => true,
        ], $items->get($changes->id)['review_flags']);
    }

    public function test_review_policy_exposes_only_the_approved_settings_contract(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $this->actingAsRole('super-admin');
        $this->setReviewSla(24, 3);

        $response = $this->getJson('/api/admin/works/review')
            ->assertOk()
            ->assertJsonPath('data.review_policy', [
                'source' => 'work_settings',
                'enabled' => true,
                'review_sla_hours' => 24,
                'overdue_cutoff' => '2026-07-14T12:00:00+00:00',
                'settings_version' => 3,
            ]);

        $policy = $response->json('data.review_policy');

        $this->assertSame([
            'enabled',
            'overdue_cutoff',
            'review_sla_hours',
            'settings_version',
            'source',
        ], collect(array_keys($policy))->sort()->values()->all());

        foreach ([
            'direct_publish_trust_enabled',
            'media_limits',
            'updated_by',
            'values',
            'metadata',
        ] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $policy);
        }
    }

    public function test_disabled_review_sla_disables_time_based_overdue_behavior_but_preserves_attention_reasons(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $this->actingAsRole('super-admin');
        $normal = Work::factory()->submitted()->create([
            'submitted_at' => now()->subHours(96),
            'reports_count' => 0,
        ]);
        $reported = Work::factory()->submitted()->create([
            'submitted_at' => now()->subHours(96),
            'reports_count' => 2,
        ]);
        $changesRequested = Work::factory()->create([
            'status' => Work::STATUS_CHANGES_REQUESTED,
            'submitted_at' => now()->subHours(96),
            'reports_count' => 0,
        ]);

        $response = $this->getJson($this->endpoint(['per_page' => 50]))
            ->assertOk()
            ->assertJsonPath('data.review_policy.enabled', false)
            ->assertJsonPath('data.review_policy.review_sla_hours', null)
            ->assertJsonPath('data.review_policy.overdue_cutoff', null)
            ->assertJsonPath('data.summary.overdue', 0);
        $items = collect($response->json('data.items'))->keyBy('id');

        $this->assertFalse($items->get($normal->id)['review_flags']['overdue']);
        $this->assertFalse($items->get($reported->id)['review_flags']['overdue']);
        $this->assertFalse($items->get($changesRequested->id)['review_flags']['overdue']);
        $this->assertFalse($items->get($normal->id)['review_flags']['needs_attention']);
        $this->assertTrue($items->get($reported->id)['review_flags']['needs_attention']);
        $this->assertTrue($items->get($changesRequested->id)['review_flags']['needs_attention']);

        $this->getJson($this->endpoint(['overdue' => 1, 'per_page' => 50]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0)
            ->assertJsonPath('data.summary.total', 0)
            ->assertJsonPath('data.summary.overdue', 0)
            ->assertJsonPath('data.items', []);

        $notOverdue = $this->getJson($this->endpoint([
            'overdue' => 0,
            'per_page' => 50,
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 3);

        $this->assertSame(
            collect([$normal, $reported, $changesRequested])->pluck('id')->sort()->values()->all(),
            collect($notOverdue->json('data.items'))->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_twenty_four_hour_sla_uses_a_strict_cutoff_for_items_flags_and_summary(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $this->actingAsRole('super-admin');
        $this->setReviewSla(24, 2);
        $overdue = Work::factory()->submitted()->create([
            'submitted_at' => now()->subHours(25),
        ]);
        $fresh = Work::factory()->submitted()->create([
            'submitted_at' => now()->subHours(23),
        ]);
        $atCutoff = Work::factory()->inReview()->create([
            'submitted_at' => now()->subHours(24),
        ]);

        $response = $this->getJson($this->endpoint(['per_page' => 50]))
            ->assertOk()
            ->assertJsonPath('data.review_policy.review_sla_hours', 24)
            ->assertJsonPath('data.summary.overdue', 1);
        $items = collect($response->json('data.items'))->keyBy('id');

        $this->assertTrue($items->get($overdue->id)['review_flags']['overdue']);
        $this->assertFalse($items->get($fresh->id)['review_flags']['overdue']);
        $this->assertFalse($items->get($atCutoff->id)['review_flags']['overdue']);
        $this->assertSame(
            $response->json('data.summary.overdue'),
            $items->filter(
                fn (array $item): bool => $item['review_flags']['overdue'],
            )->count(),
        );

        $this->assertSingleFilteredWork(['overdue' => 1], $overdue);
    }

    public function test_changed_review_sla_is_used_by_the_next_request_without_a_cache(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $this->actingAsRole('super-admin');
        $work = Work::factory()->submitted()->create([
            'submitted_at' => now()->subHours(60),
        ]);
        $this->setReviewSla(72, 4);

        $this->getJson('/api/admin/works/review')
            ->assertOk()
            ->assertJsonPath('data.review_policy.review_sla_hours', 72)
            ->assertJsonPath('data.review_policy.settings_version', 4)
            ->assertJsonPath('data.summary.overdue', 0)
            ->assertJsonPath('data.items.0.review_flags.overdue', false);

        $this->setReviewSla(48, 5);

        $this->getJson('/api/admin/works/review')
            ->assertOk()
            ->assertJsonPath('data.review_policy.review_sla_hours', 48)
            ->assertJsonPath('data.review_policy.settings_version', 5)
            ->assertJsonPath('data.summary.overdue', 1)
            ->assertJsonPath('data.items.0.id', $work->id)
            ->assertJsonPath('data.items.0.review_flags.overdue', true);
    }

    public function test_corrupt_stored_review_sla_is_normalized_to_disabled_without_falling_back_to_forty_eight_hours(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $this->actingAsRole('super-admin');
        Work::factory()->submitted()->create([
            'submitted_at' => now()->subHours(96),
        ]);
        WorkSetting::query()
            ->where('scope', WorkSetting::SCOPE_GLOBAL)
            ->sole()
            ->forceFill([
                'values' => ['review_sla_hours' => '48'],
                'version' => 7,
                'updated_by' => null,
            ])
            ->save();

        $this->getJson('/api/admin/works/review')
            ->assertOk()
            ->assertJsonPath('data.review_policy.enabled', false)
            ->assertJsonPath('data.review_policy.review_sla_hours', null)
            ->assertJsonPath('data.review_policy.overdue_cutoff', null)
            ->assertJsonPath('data.review_policy.settings_version', 7)
            ->assertJsonPath('data.summary.overdue', 0)
            ->assertJsonPath('data.items.0.review_flags.overdue', false);
    }

    public function test_static_review_route_resolves_to_review_controller(): void
    {
        $this->actingAsRole('super-admin');

        $route = Route::getRoutes()->match(Request::create('/api/admin/works/review', 'GET'));

        $this->assertSame(
            WorksReviewQueueController::class.'@index',
            $route->getActionName(),
        );
        $this->getJson('/api/admin/works/review')
            ->assertOk()
            ->assertJsonPath('message', 'تم جلب طلبات مراجعة الأعمال بنجاح');
    }

    /**
     * @param array<string, int|string> $filters
     */
    private function assertSingleFilteredWork(array $filters, Work $expected): void
    {
        $response = $this->getJson($this->endpoint($filters))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonCount(1, 'data.items');

        $this->assertSame($expected->id, $response->json('data.items.0.id'));
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function endpoint(array $parameters): string
    {
        return '/api/admin/works/review?'.http_build_query($parameters);
    }

    private function setReviewSla(?int $hours, int $version = 1): void
    {
        WorkSetting::query()->updateOrCreate(
            ['scope' => WorkSetting::SCOPE_GLOBAL],
            [
                'values' => ['review_sla_hours' => $hours],
                'version' => $version,
                'updated_by' => null,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function reviewPermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.review.view',
        ];
    }

    /**
     * @param list<string> $permissions
     */
    private function actingAsRole(string $role, array $permissions = []): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /**
     * @return list<string>
     */
    private function recursiveKeys(array $payload): array
    {
        $keys = [];

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $keys[] = $key;
            }

            if (is_array($value)) {
                $keys = [...$keys, ...$this->recursiveKeys($value)];
            }
        }

        return array_values(array_unique($keys));
    }
}
