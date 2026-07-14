<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksVisibilityController;
use App\Models\User;
use App\Models\Work;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorksVisibilityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/admin/works/visibility')
            ->assertUnauthorized();
    }

    public function test_super_admin_can_list_visibility_works(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(2)->create();

        $this->getJson('/api/admin/works/visibility')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_admin_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/admin/works/visibility')
            ->assertForbidden();
    }

    public function test_staff_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/admin/works/visibility')
            ->assertForbidden();
    }

    public function test_admin_with_access_only_gets_403(): void
    {
        $this->actingAsRole('admin', ['admin.works.access']);

        $this->getJson('/api/admin/works/visibility')
            ->assertForbidden();
    }

    public function test_admin_and_staff_with_required_permissions_can_list(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, $this->visibilityPermissions());

            $this->getJson('/api/admin/works/visibility')
                ->assertOk()
                ->assertJsonPath('success', true);
        }
    }

    public function test_client_and_designer_with_accidental_permissions_get_403(): void
    {
        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $this->visibilityPermissions());

            $this->getJson('/api/admin/works/visibility')
                ->assertForbidden();
        }
    }

    public function test_response_uses_safe_paginated_summary_and_filter_shape(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create(['name' => 'Visibility Designer']);
        $reviewer = User::factory()->create(['name' => 'Visibility Reviewer']);
        Work::factory()->published()->create([
            'title' => 'Visible Motion Work',
            'slug' => 'visible-motion-work',
            'summary' => 'Safe visibility summary.',
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
            'category_id' => 8,
            'is_featured' => true,
            'reports_count' => 2,
        ]);

        $response = $this->getJson('/api/admin/works/visibility')
            ->assertOk()
            ->assertJsonPath('message', 'تم جلب قائمة الظهور والتمييز بنجاح')
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.pagination', [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 1,
                'last_page' => 1,
            ])
            ->assertJsonPath('data.summary', [
                'total' => 1,
                'public' => 1,
                'hidden' => 0,
                'featured' => 1,
                'pinned' => 0,
                'published' => 1,
                'hidden_status' => 0,
                'reported' => 1,
                'promoted' => 1,
            ])
            ->assertJsonPath('data.filters', [
                'q' => null,
                'status' => null,
                'visibility_status' => null,
                'media_type' => null,
                'designer_id' => null,
                'reviewer_id' => null,
                'category_id' => null,
                'is_featured' => null,
                'is_pinned' => null,
                'reported' => null,
                'from' => null,
                'to' => null,
                'sort' => 'updated_at',
                'direction' => 'desc',
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
            'reports_count',
            'reviewer',
            'slug',
            'status',
            'summary',
            'title',
            'updated_at',
            'views_count',
            'visibility_flags',
            'visibility_status',
        ], collect(array_keys($item))->sort()->values()->all());
        $this->assertSame(
            ['has_reports', 'is_hidden', 'is_promoted', 'is_public'],
            collect(array_keys($item['visibility_flags']))->sort()->values()->all(),
        );
        $this->assertSame(
            ['filters', 'items', 'pagination', 'summary'],
            collect(array_keys($response->json('data')))->sort()->values()->all(),
        );
    }

    public function test_response_does_not_expose_sensitive_work_or_user_fields(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create([
            'name' => 'Visible Designer',
            'email' => 'private-visibility-designer@example.test',
            'password' => 'private-password-marker',
        ]);
        Work::factory()->create([
            'designer_id' => $designer->id,
            'description' => 'private-description-marker',
            'internal_notes' => 'private-internal-marker',
            'rejection_reason' => 'private-rejection-marker',
            'change_request_notes' => 'private-change-marker',
        ]);

        $response = $this->getJson('/api/admin/works/visibility')
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
            'private-visibility-designer@example.test',
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
            'name' => 'Limited Visibility Designer',
            'email' => 'limited-visibility-designer@example.test',
        ]);
        $reviewer = User::factory()->create([
            'name' => 'Limited Visibility Reviewer',
            'email' => 'limited-visibility-reviewer@example.test',
        ]);
        Work::factory()->create([
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
        ]);

        $response = $this->getJson('/api/admin/works/visibility')
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
        ]);

        $this->getJson('/api/admin/works/visibility')
            ->assertOk()
            ->assertJsonPath('data.items.0.designer', null)
            ->assertJsonPath('data.items.0.reviewer', null);
    }

    public function test_search_matches_title_slug_and_summary_only(): void
    {
        $this->actingAsRole('super-admin');
        $titleWork = Work::factory()->create([
            'title' => 'Nebula Visibility Work',
            'slug' => 'ordinary-visibility-title',
            'summary' => 'Standard summary.',
        ]);
        $slugWork = Work::factory()->create([
            'title' => 'Ordinary Visibility Slug',
            'slug' => 'kinetic-visibility-target',
            'summary' => 'Another standard summary.',
        ]);
        $summaryWork = Work::factory()->create([
            'title' => 'Ordinary Visibility Summary',
            'slug' => 'ordinary-visibility-summary',
            'summary' => 'Includes the Mosaic visibility marker.',
        ]);

        foreach ([
            'Nebula' => $titleWork->id,
            'visibility-target' => $slugWork->id,
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
        $designer = User::factory()->create(['email' => 'email-visibility-secret@example.test']);
        Work::factory()->create([
            'designer_id' => $designer->id,
            'title' => 'Public visibility title',
            'slug' => 'public-visibility-title',
            'summary' => 'Public visibility summary.',
            'description' => 'description-visibility-secret',
            'internal_notes' => 'internal-visibility-secret',
            'rejection_reason' => 'rejection-visibility-secret',
            'change_request_notes' => 'changes-visibility-secret',
        ]);

        foreach ([
            'description-visibility-secret',
            'internal-visibility-secret',
            'rejection-visibility-secret',
            'changes-visibility-secret',
            'email-visibility-secret',
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

        $this->getJson('/api/admin/works/visibility?q=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_status_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->published()->create();
        Work::factory()->approved()->create();

        $this->assertSingleFilteredWork(['status' => Work::STATUS_PUBLISHED], $expected);
    }

    public function test_visibility_status_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->published()->create();
        Work::factory()->approved()->create();

        $this->assertSingleFilteredWork(['visibility_status' => Work::VISIBILITY_PUBLIC], $expected);
    }

    public function test_media_type_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->create(['media_type' => 'video']);
        Work::factory()->create(['media_type' => 'image']);

        $this->assertSingleFilteredWork(['media_type' => 'video'], $expected);
    }

    public function test_designer_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create();
        $otherDesigner = User::factory()->create();
        $expected = Work::factory()->create(['designer_id' => $designer->id]);
        Work::factory()->create(['designer_id' => $otherDesigner->id]);

        $this->assertSingleFilteredWork(['designer_id' => $designer->id], $expected);
    }

    public function test_reviewer_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $reviewer = User::factory()->create();
        $otherReviewer = User::factory()->create();
        $expected = Work::factory()->create(['reviewer_id' => $reviewer->id]);
        Work::factory()->create(['reviewer_id' => $otherReviewer->id]);

        $this->assertSingleFilteredWork(['reviewer_id' => $reviewer->id], $expected);
    }

    public function test_category_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->create(['category_id' => 18]);
        Work::factory()->create(['category_id' => 27]);

        $this->assertSingleFilteredWork(['category_id' => 18], $expected);
    }

    public function test_is_featured_true_and_false_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        $featured = Work::factory()->featured()->create();
        $notFeatured = Work::factory()->create(['is_featured' => false]);

        $this->assertSingleFilteredWork(['is_featured' => 1], $featured);
        $this->assertSingleFilteredWork(['is_featured' => 0], $notFeatured);
    }

    public function test_is_pinned_true_and_false_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        $pinned = Work::factory()->pinned()->create();
        $notPinned = Work::factory()->create(['is_pinned' => false]);

        $this->assertSingleFilteredWork(['is_pinned' => 1], $pinned);
        $this->assertSingleFilteredWork(['is_pinned' => 0], $notPinned);
    }

    public function test_reported_true_and_false_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        $reported = Work::factory()->create(['reports_count' => 3]);
        $notReported = Work::factory()->create(['reports_count' => 0]);

        $this->assertSingleFilteredWork(['reported' => 1], $reported);
        $this->assertSingleFilteredWork(['reported' => 0], $notReported);
    }

    public function test_from_and_to_filter_updated_at(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['updated_at' => '2026-06-30 23:59:59']);
        $expected = Work::factory()->create(['updated_at' => '2026-07-15 10:00:00']);
        Work::factory()->create(['updated_at' => '2026-08-01 00:00:00']);

        $this->assertSingleFilteredWork([
            'from' => '2026-07-01',
            'to' => '2026-07-31',
        ], $expected);
    }

    public function test_default_sort_is_updated_at_desc_with_stable_id_tie_breaker(): void
    {
        $this->actingAsRole('super-admin');
        $old = Work::factory()->create(['updated_at' => '2026-07-01 10:00:00']);
        $newFirst = Work::factory()->create(['updated_at' => '2026-07-10 10:00:00']);
        $newSecond = Work::factory()->create(['updated_at' => '2026-07-10 10:00:00']);

        $response = $this->getJson('/api/admin/works/visibility')
            ->assertOk()
            ->assertJsonPath('data.filters.sort', 'updated_at')
            ->assertJsonPath('data.filters.direction', 'desc');

        $this->assertSame(
            [$newSecond->id, $newFirst->id, $old->id],
            collect($response->json('data.items'))->pluck('id')->all(),
        );
    }

    public function test_published_at_desc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $old = Work::factory()->published()->create(['published_at' => '2026-07-01 10:00:00']);
        $middle = Work::factory()->published()->create(['published_at' => '2026-07-10 10:00:00']);
        $new = Work::factory()->published()->create(['published_at' => '2026-07-14 10:00:00']);

        $this->assertSortedIds('published_at', 'desc', [$new->id, $middle->id, $old->id]);
    }

    public function test_views_count_desc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $low = Work::factory()->create(['views_count' => 1]);
        $high = Work::factory()->create(['views_count' => 8]);
        $middle = Work::factory()->create(['views_count' => 3]);

        $this->assertSortedIds('views_count', 'desc', [$high->id, $middle->id, $low->id]);
    }

    public function test_likes_count_desc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $low = Work::factory()->create(['likes_count' => 2]);
        $high = Work::factory()->create(['likes_count' => 12]);
        $middle = Work::factory()->create(['likes_count' => 6]);

        $this->assertSortedIds('likes_count', 'desc', [$high->id, $middle->id, $low->id]);
    }

    public function test_reports_count_desc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $low = Work::factory()->create(['reports_count' => 1]);
        $high = Work::factory()->create(['reports_count' => 8]);
        $middle = Work::factory()->create(['reports_count' => 3]);

        $this->assertSortedIds('reports_count', 'desc', [$high->id, $middle->id, $low->id]);
    }

    public function test_title_asc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['title' => 'Bravo Visibility']);
        Work::factory()->create(['title' => 'Alpha Visibility']);
        Work::factory()->create(['title' => 'Charlie Visibility']);

        $response = $this->getJson($this->endpoint([
            'sort' => 'title',
            'direction' => 'asc',
        ]))->assertOk();

        $this->assertSame(
            ['Alpha Visibility', 'Bravo Visibility', 'Charlie Visibility'],
            collect($response->json('data.items'))->pluck('title')->all(),
        );
    }

    public function test_pagination_accepts_fifty_and_rejects_values_above_the_limit(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(55)->create(['designer_id' => null]);

        $this->getJson('/api/admin/works/visibility?per_page=50')
            ->assertOk()
            ->assertJsonCount(50, 'data.items')
            ->assertJsonPath('data.pagination.per_page', 50)
            ->assertJsonPath('data.pagination.total', 55)
            ->assertJsonPath('data.pagination.last_page', 2);

        $this->getJson('/api/admin/works/visibility?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_invalid_status_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/visibility?status=deleted')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_invalid_visibility_status_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/visibility?visibility_status=private')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('visibility_status');
    }

    public function test_invalid_sort_and_direction_return_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/visibility?sort=email&direction=sideways')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'direction']);
    }

    public function test_to_before_from_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/visibility?from=2026-07-10&to=2026-07-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_range_over_ten_years_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/visibility?from=2016-07-14&to=2026-07-15')
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

    public function test_summary_counts_visibility_promotion_status_and_reports(): void
    {
        $this->actingAsRole('super-admin');

        Work::factory()->published()->featured()->create([
            'reports_count' => 2,
        ]);
        Work::factory()->hidden()->pinned()->create();
        Work::factory()->approved()->featured()->pinned()->create();
        Work::factory()->create([
            'status' => Work::STATUS_DRAFT,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
        ]);

        $this->getJson('/api/admin/works/visibility')
            ->assertOk()
            ->assertJsonPath('data.summary', [
                'total' => 4,
                'public' => 2,
                'hidden' => 2,
                'featured' => 2,
                'pinned' => 2,
                'published' => 1,
                'hidden_status' => 1,
                'reported' => 1,
                'promoted' => 3,
            ]);
    }

    public function test_summary_honors_the_same_active_filters_as_items(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->featured()->create([
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'media_type' => 'video',
        ]);
        Work::factory()->featured()->create([
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'media_type' => 'video',
        ]);
        Work::factory()->create([
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'media_type' => 'image',
        ]);

        $response = $this->getJson($this->endpoint([
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'media_type' => 'video',
            'is_featured' => 1,
        ]))->assertOk();

        $this->assertSame($expected->id, $response->json('data.items.0.id'));
        $response->assertJsonPath('data.summary', [
            'total' => 1,
            'public' => 1,
            'hidden' => 0,
            'featured' => 1,
            'pinned' => 0,
            'published' => 0,
            'hidden_status' => 0,
            'reported' => 0,
            'promoted' => 1,
        ]);
    }

    public function test_visibility_flags_are_calculated_correctly(): void
    {
        $this->actingAsRole('super-admin');
        $public = Work::factory()->published()->create([
            'is_featured' => false,
            'is_pinned' => false,
            'reports_count' => 0,
        ]);
        $hiddenPromoted = Work::factory()->create([
            'status' => Work::STATUS_APPROVED,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'is_featured' => true,
            'reports_count' => 0,
        ]);
        $hiddenStatus = Work::factory()->create([
            'status' => Work::STATUS_HIDDEN,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'is_pinned' => true,
            'reports_count' => 3,
        ]);

        $response = $this->getJson('/api/admin/works/visibility?per_page=50')
            ->assertOk();
        $items = collect($response->json('data.items'))->keyBy('id');

        $this->assertSame([
            'is_public' => true,
            'is_hidden' => false,
            'is_promoted' => false,
            'has_reports' => false,
        ], $items->get($public->id)['visibility_flags']);
        $this->assertSame([
            'is_public' => false,
            'is_hidden' => true,
            'is_promoted' => true,
            'has_reports' => false,
        ], $items->get($hiddenPromoted->id)['visibility_flags']);
        $this->assertSame([
            'is_public' => true,
            'is_hidden' => true,
            'is_promoted' => true,
            'has_reports' => true,
        ], $items->get($hiddenStatus->id)['visibility_flags']);
    }

    public function test_static_visibility_route_resolves_to_visibility_controller(): void
    {
        $this->actingAsRole('super-admin');

        $route = Route::getRoutes()->match(Request::create('/api/admin/works/visibility', 'GET'));

        $this->assertSame(
            WorksVisibilityController::class.'@index',
            $route->getActionName(),
        );
        $this->getJson('/api/admin/works/visibility')
            ->assertOk()
            ->assertJsonPath('message', 'تم جلب قائمة الظهور والتمييز بنجاح');
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
     * @param array<string, int|string> $parameters
     */
    private function endpoint(array $parameters): string
    {
        return '/api/admin/works/visibility?'.http_build_query($parameters);
    }

    /**
     * @return list<string>
     */
    private function visibilityPermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.visibility.view',
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
