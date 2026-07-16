<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksReportsController;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkReport;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorksReportsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/admin/works/reports')
            ->assertUnauthorized();
    }

    public function test_super_admin_can_list_reported_works(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(2)->create(['reports_count' => 2]);

        $this->getJson('/api/admin/works/reports')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_admin_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/admin/works/reports')
            ->assertForbidden();
    }

    public function test_staff_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/admin/works/reports')
            ->assertForbidden();
    }

    public function test_admin_with_access_only_gets_403(): void
    {
        $this->actingAsRole('admin', ['admin.works.access']);

        $this->getJson('/api/admin/works/reports')
            ->assertForbidden();
    }

    public function test_admin_with_access_and_reports_view_only_gets_403(): void
    {
        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.reports.view',
        ]);

        $this->getJson('/api/admin/works/reports')
            ->assertForbidden();
    }

    public function test_admin_and_staff_with_required_permissions_can_list(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, $this->reportsPermissions());

            $this->getJson('/api/admin/works/reports')
                ->assertOk()
                ->assertJsonPath('success', true);
        }
    }

    public function test_client_and_designer_with_accidental_permissions_get_403(): void
    {
        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $this->reportsPermissions());

            $this->getJson('/api/admin/works/reports')
                ->assertForbidden();
        }
    }

    public function test_default_scope_contains_only_works_with_reports(): void
    {
        $this->actingAsRole('super-admin');
        $first = Work::factory()->create(['reports_count' => 1]);
        $second = Work::factory()->create(['reports_count' => 7]);
        Work::factory()->create(['reports_count' => 0]);

        $response = $this->getJson('/api/admin/works/reports?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.filters.min_reports', 1)
            ->assertJsonCount(2, 'data.items');

        $this->assertSame(
            [$first->id, $second->id],
            collect($response->json('data.items'))->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_response_uses_safe_paginated_summary_and_filter_shape(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create(['name' => 'Reported Work Designer']);
        $reviewer = User::factory()->create(['name' => 'Reported Work Reviewer']);
        Work::factory()->published()->featured()->create([
            'title' => 'Reported Motion Work',
            'slug' => 'reported-motion-work',
            'summary' => 'Safe report summary.',
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
            'category_id' => 8,
            'reports_count' => 3,
        ]);

        $response = $this->getJson('/api/admin/works/reports')
            ->assertOk()
            ->assertJsonPath('message', 'تم جلب قائمة بلاغات الأعمال بنجاح')
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.pagination', [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 1,
                'last_page' => 1,
            ])
            ->assertJsonPath('data.summary', [
                'total' => 1,
                'reported' => 1,
                'high_reports' => 0,
                'public_reported' => 1,
                'hidden_reported' => 0,
                'published_reported' => 1,
                'review_queue_reported' => 0,
                'featured_reported' => 1,
                'pinned_reported' => 0,
                'total_reports' => 3,
                'legacy_reports_total' => 3,
                'tracked_reports_total' => 0,
                'combined_report_signal_total' => 3,
                'pending_tracked_reports' => 0,
                'under_review_tracked_reports' => 0,
                'dismissed_tracked_reports' => 0,
                'archived_tracked_reports' => 0,
                'open_tracked_reports' => 0,
                'works_with_legacy_reports' => 1,
                'works_with_tracked_reports' => 0,
                'works_with_open_tracked_reports' => 0,
                'works_with_both_sources' => 0,
            ])
            ->assertJsonPath('data.filters', [
                'q' => null,
                'status' => null,
                'visibility_status' => null,
                'media_type' => null,
                'designer_id' => null,
                'reviewer_id' => null,
                'category_id' => null,
                'min_reports' => 1,
                'report_source' => 'all',
                'tracked_status' => null,
                'is_featured' => null,
                'is_pinned' => null,
                'from' => null,
                'to' => null,
                'sort' => 'reports_count',
                'direction' => 'desc',
                'report_sources' => ['all', 'legacy', 'tracked', 'both'],
                'tracked_statuses' => WorkReport::STATUSES,
                'default_report_source' => 'all',
                'counts_synchronized' => false,
            ]);

        $item = $response->json('data.items.0');

        $this->assertSame([
            'category_id',
            'created_at',
            'designer',
            'hidden_at',
            'id',
            'is_featured',
            'is_pinned',
            'likes_count',
            'media_type',
            'published_at',
            'report_flags',
            'report_tracking',
            'reports_count',
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
            ['has_reports', 'high_reports', 'needs_attention', 'visibility_risk'],
            collect(array_keys($item['report_flags']))->sort()->values()->all(),
        );
        $this->assertSame(
            ['filters', 'items', 'pagination', 'summary', 'tracking_support'],
            collect(array_keys($response->json('data')))->sort()->values()->all(),
        );
    }

    public function test_response_does_not_expose_sensitive_work_or_user_fields(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create([
            'name' => 'Visible Report Designer',
            'email' => 'private-report-designer@example.test',
            'password' => 'private-password-marker',
        ]);
        Work::factory()->create([
            'designer_id' => $designer->id,
            'reports_count' => 2,
            'description' => 'private-description-marker',
            'internal_notes' => 'private-internal-marker',
            'rejection_reason' => 'private-rejection-marker',
            'change_request_notes' => 'private-change-marker',
        ]);

        $response = $this->getJson('/api/admin/works/reports')
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
            'private-report-designer@example.test',
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
            'name' => 'Limited Report Designer',
            'email' => 'limited-report-designer@example.test',
        ]);
        $reviewer = User::factory()->create([
            'name' => 'Limited Report Reviewer',
            'email' => 'limited-report-reviewer@example.test',
        ]);
        Work::factory()->create([
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
            'reports_count' => 2,
        ]);

        $response = $this->getJson('/api/admin/works/reports')
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
        Work::factory()->create([
            'designer_id' => null,
            'reviewer_id' => null,
            'reports_count' => 1,
        ]);

        $this->getJson('/api/admin/works/reports')
            ->assertOk()
            ->assertJsonPath('data.items.0.designer', null)
            ->assertJsonPath('data.items.0.reviewer', null);
    }

    public function test_search_matches_title_slug_and_summary_only(): void
    {
        $this->actingAsRole('super-admin');
        $titleWork = Work::factory()->create([
            'title' => 'Nebula Reported Work',
            'slug' => 'ordinary-reported-title',
            'summary' => 'Standard summary.',
            'reports_count' => 1,
        ]);
        $slugWork = Work::factory()->create([
            'title' => 'Ordinary Report Slug',
            'slug' => 'kinetic-report-target',
            'summary' => 'Another standard summary.',
            'reports_count' => 2,
        ]);
        $summaryWork = Work::factory()->create([
            'title' => 'Ordinary Report Summary',
            'slug' => 'ordinary-reported-summary',
            'summary' => 'Includes the Mosaic report marker.',
            'reports_count' => 3,
        ]);

        foreach ([
            'Nebula' => $titleWork->id,
            'report-target' => $slugWork->id,
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

    public function test_search_does_not_match_description_private_notes_or_user_email(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create(['email' => 'email-report-secret@example.test']);
        Work::factory()->create([
            'designer_id' => $designer->id,
            'title' => 'Public report title',
            'slug' => 'public-report-title',
            'summary' => 'Public report summary.',
            'reports_count' => 2,
            'description' => 'description-report-secret',
            'internal_notes' => 'internal-report-secret',
            'rejection_reason' => 'rejection-report-secret',
            'change_request_notes' => 'changes-report-secret',
        ]);

        foreach ([
            'description-report-secret',
            'internal-report-secret',
            'rejection-report-secret',
            'changes-report-secret',
            'email-report-secret',
        ] as $query) {
            $this->getJson($this->endpoint(['q' => $query]))
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 0)
                ->assertJsonPath('data.summary.total', 0)
                ->assertJsonPath('data.items', []);
        }
    }

    public function test_short_search_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/reports?q=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_status_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->published()->create(['reports_count' => 2]);
        Work::factory()->approved()->create(['reports_count' => 2]);

        $this->assertSingleFilteredWork(['status' => Work::STATUS_PUBLISHED], $expected);
    }

    public function test_visibility_status_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->published()->create(['reports_count' => 2]);
        Work::factory()->approved()->create(['reports_count' => 2]);

        $this->assertSingleFilteredWork(['visibility_status' => Work::VISIBILITY_PUBLIC], $expected);
    }

    public function test_media_type_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->create(['media_type' => 'video', 'reports_count' => 2]);
        Work::factory()->create(['media_type' => 'image', 'reports_count' => 2]);

        $this->assertSingleFilteredWork(['media_type' => 'video'], $expected);
    }

    public function test_designer_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create();
        $otherDesigner = User::factory()->create();
        $expected = Work::factory()->create([
            'designer_id' => $designer->id,
            'reports_count' => 2,
        ]);
        Work::factory()->create([
            'designer_id' => $otherDesigner->id,
            'reports_count' => 2,
        ]);

        $this->assertSingleFilteredWork(['designer_id' => $designer->id], $expected);
    }

    public function test_reviewer_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $reviewer = User::factory()->create();
        $otherReviewer = User::factory()->create();
        $expected = Work::factory()->create([
            'reviewer_id' => $reviewer->id,
            'reports_count' => 2,
        ]);
        Work::factory()->create([
            'reviewer_id' => $otherReviewer->id,
            'reports_count' => 2,
        ]);

        $this->assertSingleFilteredWork(['reviewer_id' => $reviewer->id], $expected);
    }

    public function test_category_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->create(['category_id' => 18, 'reports_count' => 2]);
        Work::factory()->create(['category_id' => 27, 'reports_count' => 2]);

        $this->assertSingleFilteredWork(['category_id' => 18], $expected);
    }

    public function test_min_reports_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->create(['reports_count' => 7]);
        Work::factory()->create(['reports_count' => 4]);
        Work::factory()->create(['reports_count' => 1]);

        $this->assertSingleFilteredWork(['min_reports' => 5], $expected);
    }

    public function test_is_featured_true_and_false_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        $featured = Work::factory()->featured()->create(['reports_count' => 2]);
        $notFeatured = Work::factory()->create([
            'is_featured' => false,
            'reports_count' => 2,
        ]);

        $this->assertSingleFilteredWork(['is_featured' => 1], $featured);
        $this->assertSingleFilteredWork(['is_featured' => 0], $notFeatured);
    }

    public function test_is_pinned_true_and_false_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        $pinned = Work::factory()->pinned()->create(['reports_count' => 2]);
        $notPinned = Work::factory()->create([
            'is_pinned' => false,
            'reports_count' => 2,
        ]);

        $this->assertSingleFilteredWork(['is_pinned' => 1], $pinned);
        $this->assertSingleFilteredWork(['is_pinned' => 0], $notPinned);
    }

    public function test_from_and_to_filter_updated_at(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create([
            'reports_count' => 2,
            'updated_at' => '2026-06-30 23:59:59',
        ]);
        $expected = Work::factory()->create([
            'reports_count' => 2,
            'updated_at' => '2026-07-15 10:00:00',
        ]);
        Work::factory()->create([
            'reports_count' => 2,
            'updated_at' => '2026-08-01 00:00:00',
        ]);

        $this->assertSingleFilteredWork([
            'from' => '2026-07-01',
            'to' => '2026-07-31',
        ], $expected);
    }

    public function test_default_sort_is_reports_count_desc_with_stable_id_tie_breaker(): void
    {
        $this->actingAsRole('super-admin');
        $low = Work::factory()->create(['reports_count' => 1]);
        $highFirst = Work::factory()->create(['reports_count' => 8]);
        $highSecond = Work::factory()->create(['reports_count' => 8]);

        $response = $this->getJson('/api/admin/works/reports')
            ->assertOk()
            ->assertJsonPath('data.filters.sort', 'reports_count')
            ->assertJsonPath('data.filters.direction', 'desc');

        $this->assertSame(
            [$highSecond->id, $highFirst->id, $low->id],
            collect($response->json('data.items'))->pluck('id')->all(),
        );
    }

    public function test_updated_at_desc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $old = Work::factory()->create([
            'reports_count' => 2,
            'updated_at' => '2026-07-01 10:00:00',
        ]);
        $middle = Work::factory()->create([
            'reports_count' => 2,
            'updated_at' => '2026-07-10 10:00:00',
        ]);
        $new = Work::factory()->create([
            'reports_count' => 2,
            'updated_at' => '2026-07-14 10:00:00',
        ]);

        $this->assertSortedIds('updated_at', 'desc', [$new->id, $middle->id, $old->id]);
    }

    public function test_submitted_at_asc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $first = Work::factory()->submitted()->create([
            'reports_count' => 2,
            'submitted_at' => '2026-07-01 10:00:00',
        ]);
        $second = Work::factory()->submitted()->create([
            'reports_count' => 2,
            'submitted_at' => '2026-07-10 10:00:00',
        ]);
        $third = Work::factory()->submitted()->create([
            'reports_count' => 2,
            'submitted_at' => '2026-07-14 10:00:00',
        ]);

        $this->assertSortedIds('submitted_at', 'asc', [$first->id, $second->id, $third->id]);
    }

    public function test_published_at_desc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $old = Work::factory()->published()->create([
            'reports_count' => 2,
            'published_at' => '2026-07-01 10:00:00',
        ]);
        $middle = Work::factory()->published()->create([
            'reports_count' => 2,
            'published_at' => '2026-07-10 10:00:00',
        ]);
        $new = Work::factory()->published()->create([
            'reports_count' => 2,
            'published_at' => '2026-07-14 10:00:00',
        ]);

        $this->assertSortedIds('published_at', 'desc', [$new->id, $middle->id, $old->id]);
    }

    public function test_views_count_desc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $low = Work::factory()->create(['reports_count' => 2, 'views_count' => 1]);
        $high = Work::factory()->create(['reports_count' => 2, 'views_count' => 8]);
        $middle = Work::factory()->create(['reports_count' => 2, 'views_count' => 3]);

        $this->assertSortedIds('views_count', 'desc', [$high->id, $middle->id, $low->id]);
    }

    public function test_likes_count_desc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $low = Work::factory()->create(['reports_count' => 2, 'likes_count' => 2]);
        $high = Work::factory()->create(['reports_count' => 2, 'likes_count' => 12]);
        $middle = Work::factory()->create(['reports_count' => 2, 'likes_count' => 6]);

        $this->assertSortedIds('likes_count', 'desc', [$high->id, $middle->id, $low->id]);
    }

    public function test_title_asc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['title' => 'Bravo Report', 'reports_count' => 2]);
        Work::factory()->create(['title' => 'Alpha Report', 'reports_count' => 2]);
        Work::factory()->create(['title' => 'Charlie Report', 'reports_count' => 2]);

        $response = $this->getJson($this->endpoint([
            'sort' => 'title',
            'direction' => 'asc',
        ]))->assertOk();

        $this->assertSame(
            ['Alpha Report', 'Bravo Report', 'Charlie Report'],
            collect($response->json('data.items'))->pluck('title')->all(),
        );
    }

    public function test_pagination_accepts_fifty_and_rejects_values_above_the_limit(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(55)->create([
            'designer_id' => null,
            'reports_count' => 1,
        ]);

        $this->getJson('/api/admin/works/reports?per_page=50')
            ->assertOk()
            ->assertJsonCount(50, 'data.items')
            ->assertJsonPath('data.pagination.per_page', 50)
            ->assertJsonPath('data.pagination.total', 55)
            ->assertJsonPath('data.pagination.last_page', 2);

        $this->getJson('/api/admin/works/reports?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_invalid_status_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/reports?status=deleted')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_invalid_visibility_status_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/reports?visibility_status=private')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('visibility_status');
    }

    public function test_invalid_min_reports_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([0, 100001] as $invalidMinimum) {
            $this->getJson('/api/admin/works/reports?min_reports='.$invalidMinimum)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('min_reports');
        }
    }

    public function test_invalid_sort_and_direction_return_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/reports?sort=email&direction=sideways')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'direction']);
    }

    public function test_to_before_from_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/reports?from=2026-07-10&to=2026-07-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_range_over_ten_years_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/reports?from=2016-07-14&to=2026-07-15')
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

    public function test_summary_counts_report_risk_visibility_and_workflow_groups(): void
    {
        $this->actingAsRole('super-admin');

        Work::factory()->published()->featured()->create(['reports_count' => 6]);
        Work::factory()->hidden()->pinned()->create(['reports_count' => 3]);
        Work::factory()->submitted()->featured()->pinned()->create(['reports_count' => 5]);
        Work::factory()->inReview()->create([
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'reports_count' => 2,
        ]);
        Work::factory()->create([
            'status' => Work::STATUS_CHANGES_REQUESTED,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'submitted_at' => now()->subDay(),
            'reports_count' => 1,
        ]);
        Work::factory()->approved()->create([
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'reports_count' => 4,
        ]);
        Work::factory()->create(['reports_count' => 0]);

        $this->getJson('/api/admin/works/reports?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.summary', [
                'total' => 6,
                'reported' => 6,
                'high_reports' => 2,
                'public_reported' => 3,
                'hidden_reported' => 3,
                'published_reported' => 1,
                'review_queue_reported' => 3,
                'featured_reported' => 2,
                'pinned_reported' => 2,
                'total_reports' => 21,
                'legacy_reports_total' => 21,
                'tracked_reports_total' => 0,
                'combined_report_signal_total' => 21,
                'pending_tracked_reports' => 0,
                'under_review_tracked_reports' => 0,
                'dismissed_tracked_reports' => 0,
                'archived_tracked_reports' => 0,
                'open_tracked_reports' => 0,
                'works_with_legacy_reports' => 6,
                'works_with_tracked_reports' => 0,
                'works_with_open_tracked_reports' => 0,
                'works_with_both_sources' => 0,
            ]);
    }

    public function test_summary_honors_the_same_active_filters_as_items(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->published()->featured()->create([
            'media_type' => 'video',
            'reports_count' => 7,
        ]);
        Work::factory()->published()->featured()->create([
            'media_type' => 'image',
            'reports_count' => 8,
        ]);
        Work::factory()->published()->create([
            'media_type' => 'video',
            'reports_count' => 6,
        ]);
        Work::factory()->published()->featured()->create([
            'media_type' => 'video',
            'reports_count' => 3,
        ]);

        $response = $this->getJson($this->endpoint([
            'status' => Work::STATUS_PUBLISHED,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'media_type' => 'video',
            'min_reports' => 5,
            'is_featured' => 1,
        ]))->assertOk();

        $this->assertSame($expected->id, $response->json('data.items.0.id'));
        $response->assertJsonPath('data.summary', [
            'total' => 1,
            'reported' => 1,
            'high_reports' => 1,
            'public_reported' => 1,
            'hidden_reported' => 0,
            'published_reported' => 1,
            'review_queue_reported' => 0,
            'featured_reported' => 1,
            'pinned_reported' => 0,
            'total_reports' => 7,
            'legacy_reports_total' => 7,
            'tracked_reports_total' => 0,
            'combined_report_signal_total' => 7,
            'pending_tracked_reports' => 0,
            'under_review_tracked_reports' => 0,
            'dismissed_tracked_reports' => 0,
            'archived_tracked_reports' => 0,
            'open_tracked_reports' => 0,
            'works_with_legacy_reports' => 1,
            'works_with_tracked_reports' => 0,
            'works_with_open_tracked_reports' => 0,
            'works_with_both_sources' => 0,
        ]);
    }

    public function test_report_source_filters_legacy_tracked_and_both_sources(): void
    {
        $this->actingAsRole('super-admin');
        $legacyOnly = Work::factory()->create(['reports_count' => 2]);
        $trackedOnly = Work::factory()->create(['reports_count' => 0]);
        $both = Work::factory()->create(['reports_count' => 3]);
        Work::factory()->create(['reports_count' => 0]);
        WorkReport::factory()->create(['work_id' => $trackedOnly->id]);
        WorkReport::factory()->create(['work_id' => $both->id]);

        $this->assertSourceIds('all', [$legacyOnly->id, $trackedOnly->id, $both->id]);
        $this->assertSourceIds('legacy', [$legacyOnly->id, $both->id]);
        $this->assertSourceIds('tracked', [$trackedOnly->id, $both->id]);
        $this->assertSourceIds('both', [$both->id]);
    }

    public function test_tracked_status_and_combined_minimum_filter_in_the_database(): void
    {
        $this->actingAsRole('super-admin');
        $matching = Work::factory()->create(['reports_count' => 1]);
        $wrongStatus = Work::factory()->create(['reports_count' => 1]);
        WorkReport::factory()->underReview()->create(['work_id' => $matching->id]);
        WorkReport::factory()->dismissed()->create(['work_id' => $wrongStatus->id]);

        $this->getJson($this->endpoint([
            'tracked_status' => WorkReport::STATUS_UNDER_REVIEW,
            'min_reports' => 2,
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $matching->id)
            ->assertJsonPath('data.summary.tracked_reports_total', 1)
            ->assertJsonPath('data.summary.under_review_tracked_reports', 1)
            ->assertJsonPath('data.summary.pending_tracked_reports', 0);
    }

    public function test_tracking_payload_flags_support_and_summary_are_explicit(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create([
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'reports_count' => 3,
        ]);
        WorkReport::factory()->create(['work_id' => $work->id]);
        WorkReport::factory()->underReview()->create(['work_id' => $work->id]);

        $response = $this->getJson('/api/admin/works/reports')
            ->assertOk()
            ->assertJsonPath('data.items.0.reports_count', 3)
            ->assertJsonPath('data.items.0.report_tracking', [
                'legacy_count' => 3,
                'tracked_count' => 2,
                'combined_signal_count' => 5,
                'pending_count' => 1,
                'under_review_count' => 1,
                'dismissed_count' => 0,
                'archived_count' => 0,
                'open_count' => 2,
                'has_legacy_untracked' => true,
                'has_tracked' => true,
                'has_open_tracked' => true,
            ])
            ->assertJsonPath('data.items.0.report_flags', [
                'has_reports' => true,
                'high_reports' => true,
                'visibility_risk' => true,
                'needs_attention' => true,
            ])
            ->assertJsonPath('data.summary.legacy_reports_total', 3)
            ->assertJsonPath('data.summary.tracked_reports_total', 2)
            ->assertJsonPath('data.summary.combined_report_signal_total', 5)
            ->assertJsonPath('data.summary.open_tracked_reports', 2)
            ->assertJsonPath('data.summary.works_with_both_sources', 1)
            ->assertJsonPath('data.tracking_support.counts_are_synchronized', false)
            ->assertJsonPath('data.tracking_support.combined_count_is_signal_only', true);

        $this->assertSame(3, $work->refresh()->reports_count);
        $this->assertStringNotContainsString('details', $response->getContent());
    }

    public function test_new_report_count_sorts_use_stable_database_ordering(): void
    {
        $this->actingAsRole('super-admin');
        $combinedHigh = Work::factory()->create(['reports_count' => 4]);
        $trackedHigh = Work::factory()->create(['reports_count' => 0]);
        $openHigh = Work::factory()->create(['reports_count' => 0]);
        WorkReport::factory()->create(['work_id' => $combinedHigh->id]);
        WorkReport::factory()->dismissed()->count(3)->create(['work_id' => $trackedHigh->id]);
        WorkReport::factory()->count(2)->create(['work_id' => $openHigh->id]);

        $this->assertSortedIds('combined_reports_count', 'desc', [
            $combinedHigh->id,
            $trackedHigh->id,
            $openHigh->id,
        ]);
        $this->assertSortedIds('tracked_reports_count', 'desc', [
            $trackedHigh->id,
            $openHigh->id,
            $combinedHigh->id,
        ]);
        $this->assertSortedIds('open_tracked_reports_count', 'desc', [
            $openHigh->id,
            $combinedHigh->id,
            $trackedHigh->id,
        ]);
    }

    public function test_report_flags_are_calculated_correctly(): void
    {
        $this->actingAsRole('super-admin');
        $public = Work::factory()->create([
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'reports_count' => 1,
        ]);
        $highHidden = Work::factory()->create([
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'reports_count' => 5,
        ]);
        $featuredHidden = Work::factory()->featured()->create([
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'reports_count' => 2,
        ]);
        $pinnedHidden = Work::factory()->pinned()->create([
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'reports_count' => 2,
        ]);

        $response = $this->getJson('/api/admin/works/reports?per_page=50')
            ->assertOk();
        $items = collect($response->json('data.items'))->keyBy('id');

        $this->assertSame([
            'has_reports' => true,
            'high_reports' => false,
            'visibility_risk' => true,
            'needs_attention' => true,
        ], $items->get($public->id)['report_flags']);
        $this->assertSame([
            'has_reports' => true,
            'high_reports' => true,
            'visibility_risk' => false,
            'needs_attention' => false,
        ], $items->get($highHidden->id)['report_flags']);
        $this->assertTrue($items->get($featuredHidden->id)['report_flags']['needs_attention']);
        $this->assertTrue($items->get($pinnedHidden->id)['report_flags']['needs_attention']);
    }

    public function test_static_reports_route_resolves_to_reports_controller(): void
    {
        $this->actingAsRole('super-admin');

        $route = Route::getRoutes()->match(Request::create('/api/admin/works/reports', 'GET'));

        $this->assertSame(
            WorksReportsController::class.'@index',
            $route->getActionName(),
        );
        $this->getJson('/api/admin/works/reports')
            ->assertOk()
            ->assertJsonPath('message', 'تم جلب قائمة بلاغات الأعمال بنجاح');
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
     * @param list<int> $expectedIds
     */
    private function assertSortedIds(string $sort, string $direction, array $expectedIds): void
    {
        $response = $this->getJson($this->endpoint([
            'sort' => $sort,
            'direction' => $direction,
        ]))->assertOk();

        $this->assertSame(
            $expectedIds,
            collect($response->json('data.items'))->pluck('id')->all(),
        );
    }

    /**
     * @param list<int> $expectedIds
     */
    private function assertSourceIds(string $source, array $expectedIds): void
    {
        $response = $this->getJson($this->endpoint([
            'report_source' => $source,
            'per_page' => 50,
        ]))->assertOk();

        $this->assertEqualsCanonicalizing(
            $expectedIds,
            collect($response->json('data.items'))->pluck('id')->all(),
        );
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function endpoint(array $parameters): string
    {
        return '/api/admin/works/reports?'.http_build_query($parameters);
    }

    /**
     * @return list<string>
     */
    private function reportsPermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.reports.view',
            'admin.works.reports.list',
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
