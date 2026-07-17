<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkTag;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorksIndexApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/admin/works')
            ->assertUnauthorized();
    }

    public function test_super_admin_can_list_works(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(2)->create();

        $this->getJson('/api/admin/works')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_admin_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/admin/works')
            ->assertForbidden();
    }

    public function test_staff_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/admin/works')
            ->assertForbidden();
    }

    public function test_admin_with_access_and_all_view_but_without_list_gets_403(): void
    {
        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.all.view',
        ]);

        $this->getJson('/api/admin/works')
            ->assertForbidden();
    }

    public function test_admin_and_staff_with_all_required_permissions_can_list(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, $this->listPermissions());

            $this->getJson('/api/admin/works')
                ->assertOk()
                ->assertJsonPath('success', true);
        }
    }

    public function test_client_and_designer_with_accidental_permissions_get_403(): void
    {
        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $this->listPermissions());

            $this->getJson('/api/admin/works')
                ->assertForbidden();
        }
    }

    public function test_response_uses_safe_paginated_shape_and_limited_user_references(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create(['name' => 'Allowed Designer Name']);
        $reviewer = User::factory()->create(['name' => 'Allowed Reviewer Name']);
        Work::factory()->create([
            'title' => 'Motion Identity',
            'slug' => 'motion-identity',
            'summary' => 'A concise safe summary.',
            'price_amount' => '100.00',
            'delivery_days' => 3,
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
            'category_id' => 7,
        ]);

        $response = $this->getJson('/api/admin/works')
            ->assertOk()
            ->assertJsonPath('message', 'تم جلب قائمة الأعمال بنجاح')
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.pagination', [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 1,
                'last_page' => 1,
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
                'sort' => 'created_at',
                'direction' => 'desc',
            ]);

        $item = $response->json('data.items.0');

        $this->assertSame([
            'category_id',
            'created_at',
            'delivery_days',
            'designer',
            'id',
            'is_featured',
            'is_pinned',
            'likes_count',
            'media_type',
            'price_amount',
            'published_at',
            'reports_count',
            'reviewer',
            'slug',
            'status',
            'submitted_at',
            'summary',
            'taxonomy',
            'title',
            'updated_at',
            'views_count',
            'visibility_status',
        ], collect(array_keys($item))->sort()->values()->all());
        $this->assertSame(['id', 'name'], array_keys($item['designer']));
        $this->assertSame(['id', 'name'], array_keys($item['reviewer']));
        $this->assertSame([
            'category' => null,
            'category_tracking' => [
                'catalog_record_exists' => false,
                'is_legacy_unmapped' => true,
                'is_uncategorized' => false,
            ],
            'tags' => [],
        ], $item['taxonomy']);
        $this->assertSame([
            'can_view_category' => true,
            'can_view_tags' => true,
        ], $response->json('data.taxonomy_access'));
    }

    public function test_super_admin_receives_safe_category_and_ordered_active_and_disabled_tags(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->disabled()->create(['sort_order' => 8]);
        $work = Work::factory()->create(['category_id' => $category->id]);
        $later = WorkTag::factory()->create(['sort_order' => 20]);
        $sameOrderHigherId = WorkTag::factory()->disabled()->create(['sort_order' => 5]);
        $sameOrderLowerId = WorkTag::factory()->create(['sort_order' => 5]);
        $work->tags()->attach([$later->id, $sameOrderHigherId->id, $sameOrderLowerId->id]);

        $response = $this->getJson('/api/admin/works')
            ->assertOk()
            ->assertJsonPath('data.taxonomy_access', [
                'can_view_category' => true,
                'can_view_tags' => true,
            ])
            ->assertJsonPath('data.items.0.taxonomy.category.id', $category->id)
            ->assertJsonPath('data.items.0.taxonomy.category.is_active', false)
            ->assertJsonPath('data.items.0.taxonomy.category_tracking', [
                'catalog_record_exists' => true,
                'is_legacy_unmapped' => false,
                'is_uncategorized' => false,
            ]);

        $taxonomy = $response->json('data.items.0.taxonomy');
        $expectedTagIds = collect([$later, $sameOrderHigherId, $sameOrderLowerId])
            ->sortBy(fn (WorkTag $tag): string => sprintf('%010d-%010d', $tag->sort_order, $tag->id))
            ->pluck('id')
            ->values()
            ->all();

        $this->assertSame($expectedTagIds, array_column($taxonomy['tags'], 'id'));
        $this->assertContains(false, array_column($taxonomy['tags'], 'is_active'));
        $this->assertSame(
            ['disabled_at', 'id', 'is_active', 'name_ar', 'name_en', 'slug', 'sort_order'],
            collect(array_keys($taxonomy['category']))->sort()->values()->all(),
        );

        foreach ($taxonomy['tags'] as $tag) {
            $this->assertSame(
                ['disabled_at', 'id', 'is_active', 'name_ar', 'name_en', 'slug', 'sort_order'],
                collect(array_keys($tag))->sort()->values()->all(),
            );
        }
    }

    public function test_taxonomy_sections_are_independently_permission_scoped_in_index(): void
    {
        $category = WorkCategory::factory()->create();
        $tag = WorkTag::factory()->create();
        $work = Work::factory()->create(['category_id' => $category->id]);
        $work->tags()->attach($tag);

        $this->actingAsRole('admin', [...$this->listPermissions(),
            'admin.works.taxonomy.view',
            'admin.works.taxonomy.categories.view',
        ]);
        $this->getJson('/api/admin/works')
            ->assertOk()
            ->assertJsonPath('data.taxonomy_access.can_view_category', true)
            ->assertJsonPath('data.taxonomy_access.can_view_tags', false)
            ->assertJsonPath('data.items.0.taxonomy.category.id', $category->id)
            ->assertJsonPath('data.items.0.taxonomy.tags', null);

        $this->actingAsRole('staff', [...$this->listPermissions(),
            'admin.works.taxonomy.view',
            'admin.works.taxonomy.tags.view',
        ]);
        $this->getJson('/api/admin/works')
            ->assertOk()
            ->assertJsonPath('data.taxonomy_access.can_view_category', false)
            ->assertJsonPath('data.taxonomy_access.can_view_tags', true)
            ->assertJsonPath('data.items.0.taxonomy.category', null)
            ->assertJsonPath('data.items.0.taxonomy.category_tracking', null)
            ->assertJsonPath('data.items.0.taxonomy.tags.0.id', $tag->id);
    }

    public function test_index_without_taxonomy_view_does_not_hide_work_or_expose_taxonomy(): void
    {
        Work::factory()->create();
        $this->actingAsRole('admin', [...$this->listPermissions(),
            'admin.works.update.category',
            'admin.works.update.tags',
            'admin.works.taxonomy.bulk_assign',
        ]);

        $this->getJson('/api/admin/works')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.taxonomy_access', [
                'can_view_category' => false,
                'can_view_tags' => false,
            ])
            ->assertJsonPath('data.items.0.taxonomy', [
                'category' => null,
                'category_tracking' => null,
                'tags' => null,
            ]);
    }

    public function test_index_distinguishes_uncategorized_and_legacy_and_returns_empty_tags_when_authorized(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();
        $legacyCategoryId = $category->id + 100_000;
        $uncategorized = Work::factory()->create(['category_id' => null, 'created_at' => now()->subMinute()]);
        $legacy = Work::factory()->create(['category_id' => $legacyCategoryId, 'created_at' => now()]);

        $response = $this->getJson('/api/admin/works?sort=id&direction=asc')
            ->assertOk();
        $items = collect($response->json('data.items'))->keyBy('id');

        $this->assertSame([
            'catalog_record_exists' => false,
            'is_legacy_unmapped' => false,
            'is_uncategorized' => true,
        ], $items[$uncategorized->id]['taxonomy']['category_tracking']);
        $this->assertSame([
            'catalog_record_exists' => false,
            'is_legacy_unmapped' => true,
            'is_uncategorized' => false,
        ], $items[$legacy->id]['taxonomy']['category_tracking']);
        $this->assertSame($legacyCategoryId, $items[$legacy->id]['category_id']);
        $this->assertSame([], $items[$uncategorized->id]['taxonomy']['tags']);
        $this->assertSame([], $items[$legacy->id]['taxonomy']['tags']);
    }

    public function test_response_does_not_expose_sensitive_work_or_user_fields(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create([
            'name' => 'Visible Designer',
            'email' => 'private-designer@example.test',
            'password' => 'private-password-marker',
        ]);
        Work::factory()->create([
            'designer_id' => $designer->id,
            'description' => 'private-description-marker',
            'internal_notes' => 'private-internal-marker',
            'rejection_reason' => 'private-rejection-marker',
            'change_request_notes' => 'private-change-marker',
        ]);

        $response = $this->getJson('/api/admin/works')
            ->assertOk();
        $json = strtolower($response->getContent());
        $keys = $this->recursiveKeys($response->json());

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
            'user',
            'users',
            'rows',
        ] as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $keys);
        }

        foreach ([
            'private-designer@example.test',
            'private-password-marker',
            'private-description-marker',
            'private-internal-marker',
            'private-rejection-marker',
            'private-change-marker',
        ] as $forbiddenValue) {
            $this->assertStringNotContainsString($forbiddenValue, $json);
        }
    }

    public function test_missing_designer_and_reviewer_are_returned_as_null(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create([
            'designer_id' => null,
            'reviewer_id' => null,
        ]);

        $this->getJson('/api/admin/works')
            ->assertOk()
            ->assertJsonPath('data.items.0.designer', null)
            ->assertJsonPath('data.items.0.reviewer', null);
    }

    public function test_search_matches_title_slug_and_summary_only(): void
    {
        $this->actingAsRole('super-admin');
        $titleWork = Work::factory()->create([
            'title' => 'Nebula Editorial',
            'slug' => 'ordinary-title-work',
            'summary' => 'Standard summary.',
        ]);
        $slugWork = Work::factory()->create([
            'title' => 'Ordinary Slug Work',
            'slug' => 'kinetic-slug-target',
            'summary' => 'Another standard summary.',
        ]);
        $summaryWork = Work::factory()->create([
            'title' => 'Ordinary Summary Work',
            'slug' => 'ordinary-summary-work',
            'summary' => 'Includes the Mosaic marker.',
        ]);

        foreach ([
            'Nebula' => $titleWork->id,
            'slug-target' => $slugWork->id,
            'Mosaic' => $summaryWork->id,
        ] as $query => $expectedId) {
            $this->getJson($this->endpoint(['q' => $query]))
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 1)
                ->assertJsonPath('data.items.0.id', $expectedId)
                ->assertJsonPath('data.filters.q', $query);
        }
    }

    public function test_search_does_not_match_description_or_private_notes(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create([
            'title' => 'Public searchable title',
            'slug' => 'public-searchable-title',
            'summary' => 'Public searchable summary.',
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
        ] as $query) {
            $this->getJson($this->endpoint(['q' => $query]))
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 0)
                ->assertJsonPath('data.items', []);
        }
    }

    public function test_status_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->published()->create();
        Work::factory()->submitted()->create();

        $this->assertSingleFilteredWork(['status' => Work::STATUS_PUBLISHED], $expected);
    }

    public function test_visibility_status_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->published()->create();
        Work::factory()->submitted()->create();

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
        $expected = Work::factory()->create(['category_id' => 41]);
        Work::factory()->create(['category_id' => 42]);

        $this->assertSingleFilteredWork(['category_id' => 41], $expected);
    }

    public function test_featured_and_pinned_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        $featured = Work::factory()->featured()->create();
        $pinned = Work::factory()->pinned()->create();
        Work::factory()->create();

        $this->assertSingleFilteredWork(['is_featured' => 1], $featured);
        $this->assertSingleFilteredWork(['is_pinned' => 1], $pinned);
    }

    public function test_reported_true_and_false_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        $reported = Work::factory()->create(['reports_count' => 3]);
        $notReported = Work::factory()->create(['reports_count' => 0]);

        $this->assertSingleFilteredWork(['reported' => 1], $reported);
        $this->assertSingleFilteredWork(['reported' => 0], $notReported);
    }

    public function test_from_and_to_filter_created_at(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['created_at' => '2026-06-30 23:59:59']);
        $expected = Work::factory()->create(['created_at' => '2026-07-15 10:00:00']);
        Work::factory()->create(['created_at' => '2026-08-01 00:00:00']);

        $this->assertSingleFilteredWork([
            'from' => '2026-07-01',
            'to' => '2026-07-31',
        ], $expected);
    }

    public function test_title_sorting_works_in_both_directions(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['title' => 'Bravo Work']);
        Work::factory()->create(['title' => 'Alpha Work']);
        Work::factory()->create(['title' => 'Charlie Work']);

        $ascending = $this->getJson($this->endpoint([
            'sort' => 'title',
            'direction' => 'asc',
            'per_page' => 50,
        ]))->assertOk();
        $descending = $this->getJson($this->endpoint([
            'sort' => 'title',
            'direction' => 'desc',
            'per_page' => 50,
        ]))->assertOk();

        $this->assertSame(
            ['Alpha Work', 'Bravo Work', 'Charlie Work'],
            collect($ascending->json('data.items'))->pluck('title')->all(),
        );
        $this->assertSame(
            ['Charlie Work', 'Bravo Work', 'Alpha Work'],
            collect($descending->json('data.items'))->pluck('title')->all(),
        );
    }

    public function test_default_sort_is_created_at_desc_with_stable_id_tie_breaker(): void
    {
        $this->actingAsRole('super-admin');
        $older = Work::factory()->create(['created_at' => '2026-07-01 10:00:00']);
        $newerFirst = Work::factory()->create(['created_at' => '2026-07-10 10:00:00']);
        $newerSecond = Work::factory()->create(['created_at' => '2026-07-10 10:00:00']);

        $response = $this->getJson('/api/admin/works')
            ->assertOk()
            ->assertJsonPath('data.filters.sort', 'created_at')
            ->assertJsonPath('data.filters.direction', 'desc');

        $this->assertSame(
            [$newerSecond->id, $newerFirst->id, $older->id],
            collect($response->json('data.items'))->pluck('id')->all(),
        );
    }

    public function test_reports_count_desc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $low = Work::factory()->create(['reports_count' => 1]);
        $high = Work::factory()->create(['reports_count' => 8]);
        $middle = Work::factory()->create(['reports_count' => 3]);

        $response = $this->getJson($this->endpoint([
            'sort' => 'reports_count',
            'direction' => 'desc',
        ]))->assertOk();

        $this->assertSame(
            [$high->id, $middle->id, $low->id],
            collect($response->json('data.items'))->pluck('id')->all(),
        );
    }

    public function test_pagination_accepts_fifty_and_rejects_values_above_the_limit(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(55)->create();

        $this->getJson('/api/admin/works?per_page=50')
            ->assertOk()
            ->assertJsonCount(50, 'data.items')
            ->assertJsonPath('data.pagination.per_page', 50)
            ->assertJsonPath('data.pagination.total', 55)
            ->assertJsonPath('data.pagination.last_page', 2);

        $this->getJson('/api/admin/works?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_page_parameter_returns_the_requested_page(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(5)->create();

        $this->getJson('/api/admin/works?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 5)
            ->assertJsonPath('data.pagination.last_page', 3);
    }

    public function test_short_search_and_invalid_status_return_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works?q=x&status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q', 'status']);
    }

    public function test_invalid_sort_and_direction_return_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works?sort=email&direction=sideways')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'direction']);
    }

    public function test_to_before_from_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works?from=2026-07-10&to=2026-07-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_range_over_ten_years_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works?from=2016-07-12&to=2026-07-13')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_unknown_and_sensitive_query_parameters_return_422(): void
    {
        $this->actingAsRole('super-admin');
        $parameters = [
            'email' => 'private@example.test',
            'password' => 'secret',
            'token' => 'secret',
            'cookie' => 'secret',
            'internal_notes' => 'secret',
            'rejection_reason' => 'secret',
            'change_request_notes' => 'secret',
            'payload' => 'secret',
            'metadata' => 'secret',
        ];

        $this->getJson($this->endpoint($parameters))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys($parameters));
    }

    /**
     * @param array<string, int|string> $filters
     */
    private function assertSingleFilteredWork(array $filters, Work $expected): void
    {
        $response = $this->getJson($this->endpoint($filters))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonCount(1, 'data.items');

        $this->assertSame($expected->id, $response->json('data.items.0.id'));
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function endpoint(array $parameters): string
    {
        return '/api/admin/works?'.http_build_query($parameters);
    }

    /**
     * @return list<string>
     */
    private function listPermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.all.view',
            'admin.works.list',
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
