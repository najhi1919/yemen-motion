<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksTaxonomyController;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkTag;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorksTaxonomyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/admin/works/taxonomy')
            ->assertUnauthorized();
    }

    public function test_super_admin_can_list_taxonomy_buckets(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['category_id' => 1]);
        Work::factory()->create(['category_id' => 2]);

        $this->getJson('/api/admin/works/taxonomy')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_admin_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/admin/works/taxonomy')
            ->assertForbidden();
    }

    public function test_staff_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/admin/works/taxonomy')
            ->assertForbidden();
    }

    public function test_admin_with_access_only_gets_403(): void
    {
        $this->actingAsRole('admin', ['admin.works.access']);

        $this->getJson('/api/admin/works/taxonomy')
            ->assertForbidden();
    }

    public function test_admin_with_access_and_taxonomy_view_can_list_without_catalog_permission(): void
    {
        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.taxonomy.view',
        ]);

        $this->getJson('/api/admin/works/taxonomy')
            ->assertOk();
    }

    public function test_admin_and_staff_with_required_permissions_can_list(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, $this->taxonomyPermissions());

            $this->getJson('/api/admin/works/taxonomy')
                ->assertOk()
                ->assertJsonPath('success', true);
        }
    }

    public function test_client_and_designer_with_accidental_permissions_get_403(): void
    {
        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $this->taxonomyPermissions());

            $this->getJson('/api/admin/works/taxonomy')
                ->assertForbidden();
        }
    }

    public function test_works_are_grouped_by_category_id(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(2)->create(['category_id' => 14]);
        Work::factory()->create(['category_id' => 28]);

        $response = $this->getJson('/api/admin/works/taxonomy?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.summary.total_works', 3);
        $items = collect($response->json('data.items'))->keyBy('category_id');

        $this->assertSame(2, $items->get(14)['works_count']);
        $this->assertSame('تصنيف #14', $items->get(14)['label']);
        $this->assertSame(1, $items->get(28)['works_count']);
    }

    public function test_null_category_appears_as_uncategorized_bucket(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(2)->create(['category_id' => null]);

        $this->getJson('/api/admin/works/taxonomy')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.category_id', null)
            ->assertJsonPath('data.items.0.label', 'غير مصنف')
            ->assertJsonPath('data.items.0.works_count', 2)
            ->assertJsonPath('data.items.0.taxonomy_flags.uncategorized', true);
    }

    public function test_bucket_returns_safe_catalog_category_and_uses_its_arabic_name(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create([
            'name_ar' => 'هوية بصرية',
            'name_en' => 'Visual Identity',
            'slug' => 'visual-identity',
            'sort_order' => 6,
        ]);
        Work::factory()->create(['category_id' => $category->id]);

        $this->getJson('/api/admin/works/taxonomy')
            ->assertOk()
            ->assertJsonPath('data.items.0.category_id', $category->id)
            ->assertJsonPath('data.items.0.label', 'هوية بصرية')
            ->assertJsonPath('data.items.0.category', [
                'id' => $category->id,
                'name_ar' => 'هوية بصرية',
                'name_en' => 'Visual Identity',
                'slug' => 'visual-identity',
                'disabled_at' => null,
                'is_active' => true,
                'sort_order' => 6,
            ])
            ->assertJsonPath('data.items.0.category_tracking', [
                'catalog_record_exists' => true,
                'is_legacy_unmapped' => false,
                'is_uncategorized' => false,
            ])
            ->assertJsonPath('data.category_support.mapping_complete', true);
    }

    public function test_unmapped_and_uncategorized_buckets_have_distinct_tracking_states(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['category_id' => 1]);
        Work::factory()->create(['category_id' => null]);

        $items = collect($this->getJson('/api/admin/works/taxonomy?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.category_support.mapping_complete', false)
            ->json('data.items'));
        $legacy = $items->firstWhere('category_id', 1);
        $uncategorized = $items->firstWhere('category_id', null);

        $this->assertNull($legacy['category']);
        $this->assertSame('تصنيف #1', $legacy['label']);
        $this->assertSame([
            'catalog_record_exists' => false,
            'is_legacy_unmapped' => true,
            'is_uncategorized' => false,
        ], $legacy['category_tracking']);

        $this->assertNull($uncategorized['category']);
        $this->assertSame('غير مصنف', $uncategorized['label']);
        $this->assertSame([
            'catalog_record_exists' => false,
            'is_legacy_unmapped' => false,
            'is_uncategorized' => true,
        ], $uncategorized['category_tracking']);
    }

    public function test_catalog_summary_additions_are_global_and_keep_bucket_summary_meanings(): void
    {
        $this->actingAsRole('super-admin');
        $usedCategory = WorkCategory::factory()->create();
        WorkCategory::factory()->disabled()->create();
        $usedTag = WorkTag::factory()->create();
        WorkTag::factory()->disabled()->create();
        $linkedWork = Work::factory()->create(['category_id' => $usedCategory->id]);
        Work::factory()->count(2)->create(['category_id' => 900]);
        Work::factory()->create(['category_id' => null]);
        $linkedWork->tags()->attach($usedTag->id);

        $this->getJson('/api/admin/works/taxonomy?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.summary.total_categories', 3)
            ->assertJsonPath('data.summary.categorized_categories', 2)
            ->assertJsonPath('data.summary.uncategorized_buckets', 1)
            ->assertJsonPath('data.summary.total_works', 4)
            ->assertJsonPath('data.summary.categorized_works', 3)
            ->assertJsonPath('data.summary.uncategorized_works', 1)
            ->assertJsonPath('data.summary.catalog_categories_total', 2)
            ->assertJsonPath('data.summary.active_catalog_categories', 1)
            ->assertJsonPath('data.summary.disabled_catalog_categories', 1)
            ->assertJsonPath('data.summary.used_catalog_categories', 1)
            ->assertJsonPath('data.summary.unused_catalog_categories', 1)
            ->assertJsonPath('data.summary.legacy_unmapped_category_ids', 1)
            ->assertJsonPath('data.summary.works_with_legacy_unmapped_category', 2)
            ->assertJsonPath('data.summary.tags_total', 2)
            ->assertJsonPath('data.summary.active_tags', 1)
            ->assertJsonPath('data.summary.disabled_tags', 1)
            ->assertJsonPath('data.summary.used_tags', 1)
            ->assertJsonPath('data.summary.unused_tags', 1)
            ->assertJsonPath('data.summary.tag_assignments_total', 1);
    }

    public function test_taxonomy_read_does_not_create_or_modify_catalog_or_work_data(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create(['category_id' => 1]);
        $workUpdatedAt = $work->updated_at?->toJSON();

        $this->getJson('/api/admin/works/taxonomy')->assertOk();

        $this->assertSame(1, $work->refresh()->category_id);
        $this->assertSame($workUpdatedAt, $work->updated_at?->toJSON());
        $this->assertDatabaseCount('work_categories', 0);
        $this->assertDatabaseCount('work_tags', 0);
        $this->assertDatabaseCount('work_tag_assignments', 0);
    }

    public function test_response_uses_safe_paginated_summary_filter_and_tag_support_shape(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->published()->featured()->create([
            'category_id' => 8,
            'reports_count' => 3,
            'views_count' => 12,
            'likes_count' => 4,
            'updated_at' => '2026-07-15 10:00:00',
        ]);

        $response = $this->getJson('/api/admin/works/taxonomy')
            ->assertOk()
            ->assertJsonPath('message', 'تم جلب تصنيفات الأعمال بنجاح')
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.pagination', [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 1,
                'last_page' => 1,
            ])
            ->assertJsonPath('data.filters', [
                'q' => null,
                'category_id' => null,
                'status' => null,
                'visibility_status' => null,
                'media_type' => null,
                'only_uncategorized' => null,
                'only_reported' => null,
                'only_promoted' => null,
                'from' => null,
                'to' => null,
                'sort' => 'works_count',
                'direction' => 'desc',
            ])
            ->assertJsonPath('data.tag_support.available', true)
            ->assertJsonPath('data.tag_support.catalog_source', 'work_tags')
            ->assertJsonPath('data.tag_support.assignments_source', 'work_tag_assignments')
            ->assertJsonPath(
                'data.tag_support.reason',
                'بنية الوسوم المستقلة متاحة للقراءة.',
            )
            ->assertJsonPath('data.category_support.available', true)
            ->assertJsonPath('data.category_support.mapping_complete', false)
            ->assertJsonPath('data.category_support.foreign_key_enforced', false);

        $item = $response->json('data.items.0');

        $this->assertSame(
            collect([
                'category',
                'category_id',
                'category_tracking',
                'featured_count',
                'hidden_count',
                'label',
                'latest_work_at',
                'pinned_count',
                'published_count',
                'reported_count',
                'review_queue_count',
                'taxonomy_flags',
                'total_likes',
                'total_reports',
                'total_views',
                'works_count',
            ])->sort()->values()->all(),
            collect(array_keys($item))->sort()->values()->all(),
        );
        $this->assertSame(
            collect([
                'has_hidden',
                'has_published',
                'has_reports',
                'is_promoted',
                'needs_attention',
                'uncategorized',
            ])->sort()->values()->all(),
            collect(array_keys($item['taxonomy_flags']))->sort()->values()->all(),
        );
        $this->assertSame(
            collect(['category_support', 'filters', 'items', 'pagination', 'summary', 'tag_support'])
                ->sort()
                ->values()
                ->all(),
            collect(array_keys($response->json('data')))->sort()->values()->all(),
        );
    }

    public function test_response_does_not_expose_work_rows_or_sensitive_fields(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create([
            'name' => 'Private Taxonomy Designer',
            'email' => 'taxonomy-private@example.test',
            'password' => 'taxonomy-private-password',
        ]);
        Work::factory()->create([
            'category_id' => 4,
            'designer_id' => $designer->id,
            'title' => 'private-taxonomy-title-marker',
            'slug' => 'private-taxonomy-slug-marker',
            'summary' => 'private-taxonomy-summary-marker',
            'description' => 'private-taxonomy-description-marker',
            'internal_notes' => 'private-taxonomy-internal-marker',
            'rejection_reason' => 'private-taxonomy-rejection-marker',
            'change_request_notes' => 'private-taxonomy-change-marker',
        ]);

        $response = $this->getJson('/api/admin/works/taxonomy')
            ->assertOk();
        $keys = $this->recursiveKeys($response->json());
        $json = strtolower($response->getContent());

        foreach ([
            'work',
            'works',
            'rows',
            'title',
            'description',
            'internal_notes',
            'rejection_reason',
            'change_request_notes',
            'designer',
            'reviewer',
            'user',
            'users',
            'email',
            'password',
            'token',
            'cookie',
            'metadata',
            'payload',
            'model',
        ] as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $keys);
        }

        foreach ([
            'taxonomy-private@example.test',
            'taxonomy-private-password',
            'private-taxonomy-title-marker',
            'private-taxonomy-slug-marker',
            'private-taxonomy-summary-marker',
            'private-taxonomy-description-marker',
            'private-taxonomy-internal-marker',
            'private-taxonomy-rejection-marker',
            'private-taxonomy-change-marker',
        ] as $forbiddenValue) {
            $this->assertStringNotContainsString($forbiddenValue, $json);
        }
    }

    public function test_search_matches_computed_label_category_id_and_uncategorized_label(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['category_id' => 42]);
        Work::factory()->create(['category_id' => 84]);
        Work::factory()->create(['category_id' => null]);

        $this->getJson($this->endpoint(['q' => 'تصنيف #42']))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.category_id', 42)
            ->assertJsonPath('data.filters.q', 'تصنيف #42');

        $this->getJson($this->endpoint(['q' => '84']))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.category_id', 84);

        $this->getJson($this->endpoint(['q' => 'غير مصنف']))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.category_id', null);
    }

    public function test_search_does_not_match_work_content_private_notes_or_user_email(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create(['email' => 'taxonomy-email-secret@example.test']);
        Work::factory()->create([
            'category_id' => 7,
            'designer_id' => $designer->id,
            'title' => 'taxonomy-title-secret',
            'slug' => 'taxonomy-slug-secret',
            'summary' => 'taxonomy-summary-secret',
            'description' => 'taxonomy-description-secret',
            'internal_notes' => 'taxonomy-internal-secret',
            'rejection_reason' => 'taxonomy-rejection-secret',
            'change_request_notes' => 'taxonomy-change-secret',
        ]);

        foreach ([
            'taxonomy-title-secret',
            'taxonomy-slug-secret',
            'taxonomy-summary-secret',
            'taxonomy-description-secret',
            'taxonomy-internal-secret',
            'taxonomy-rejection-secret',
            'taxonomy-change-secret',
            'taxonomy-email-secret',
        ] as $query) {
            $this->getJson($this->endpoint(['q' => $query]))
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 0)
                ->assertJsonPath('data.summary.total_categories', 0)
                ->assertJsonPath('data.items', []);
        }
    }

    public function test_category_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['category_id' => 18]);
        Work::factory()->create(['category_id' => 27]);

        $this->assertSingleCategory(['category_id' => 18], 18, 1);
    }

    public function test_status_visibility_and_media_type_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->published()->create([
            'category_id' => 11,
            'media_type' => 'video',
        ]);
        Work::factory()->approved()->create([
            'category_id' => 22,
            'media_type' => 'image',
        ]);

        $this->assertSingleCategory(['status' => Work::STATUS_PUBLISHED], 11, 1);
        $this->assertSingleCategory(['visibility_status' => Work::VISIBILITY_PUBLIC], 11, 1);
        $this->assertSingleCategory(['media_type' => 'video'], 11, 1);
    }

    public function test_only_uncategorized_true_and_false_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['category_id' => null]);
        Work::factory()->create(['category_id' => 5]);

        $this->getJson($this->endpoint(['only_uncategorized' => 1]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.category_id', null)
            ->assertJsonPath('data.filters.only_uncategorized', true);

        $this->getJson($this->endpoint(['only_uncategorized' => 0]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.category_id', 5)
            ->assertJsonPath('data.filters.only_uncategorized', false);
    }

    public function test_only_reported_true_filters_underlying_bucket_works(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['category_id' => 10, 'reports_count' => 3]);
        Work::factory()->create(['category_id' => 10, 'reports_count' => 0]);
        Work::factory()->create(['category_id' => 20, 'reports_count' => 0]);

        $this->getJson($this->endpoint(['only_reported' => 1]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.category_id', 10)
            ->assertJsonPath('data.items.0.works_count', 1)
            ->assertJsonPath('data.items.0.reported_count', 1)
            ->assertJsonPath('data.filters.only_reported', true);

        $this->getJson($this->endpoint(['only_reported' => 0]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.summary.total_works', 3)
            ->assertJsonPath('data.filters.only_reported', false);
    }

    public function test_only_promoted_true_filters_featured_or_pinned_works(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->featured()->create(['category_id' => 10]);
        Work::factory()->pinned()->create(['category_id' => 20]);
        Work::factory()->create(['category_id' => 30]);

        $response = $this->getJson($this->endpoint([
            'only_promoted' => 1,
            'per_page' => 50,
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.summary.total_works', 2)
            ->assertJsonPath('data.filters.only_promoted', true);

        $this->assertSame(
            [10, 20],
            collect($response->json('data.items'))->pluck('category_id')->sort()->values()->all(),
        );
    }

    public function test_from_and_to_filter_updated_at(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create([
            'category_id' => 1,
            'updated_at' => '2026-06-30 23:59:59',
        ]);
        Work::factory()->create([
            'category_id' => 2,
            'updated_at' => '2026-07-15 10:00:00',
        ]);
        Work::factory()->create([
            'category_id' => 3,
            'updated_at' => '2026-08-01 00:00:00',
        ]);

        $this->assertSingleCategory([
            'from' => '2026-07-01',
            'to' => '2026-07-31',
        ], 2, 1);
    }

    public function test_supported_sorts_and_default_order_work(): void
    {
        $this->actingAsRole('super-admin');

        Work::factory()->count(3)->create([
            'category_id' => 10,
            'status' => Work::STATUS_DRAFT,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'reports_count' => 0,
            'views_count' => 1,
            'likes_count' => 1,
            'updated_at' => '2026-07-01 10:00:00',
        ]);
        Work::factory()->count(2)->create([
            'category_id' => 20,
            'status' => Work::STATUS_PUBLISHED,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'reports_count' => 5,
            'views_count' => 100,
            'likes_count' => 50,
            'updated_at' => '2026-07-15 10:00:00',
        ]);

        $this->getJson('/api/admin/works/taxonomy')
            ->assertOk()
            ->assertJsonPath('data.filters.sort', 'works_count')
            ->assertJsonPath('data.filters.direction', 'desc')
            ->assertJsonPath('data.items.0.category_id', 10);

        $this->assertFirstCategoryBySort('category_id', 'asc', 10);

        foreach ([
            'latest_work_at',
            'reported_count',
            'published_count',
            'hidden_count',
            'total_reports',
            'total_views',
            'total_likes',
        ] as $sort) {
            $this->assertFirstCategoryBySort($sort, 'desc', 20);
        }
    }

    public function test_pagination_accepts_fifty_and_rejects_values_above_the_limit(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(3)->sequence(
            ['category_id' => 1],
            ['category_id' => 2],
            ['category_id' => 3],
        )->create();

        $this->getJson('/api/admin/works/taxonomy?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonCount(2, 'data.items');

        $this->getJson('/api/admin/works/taxonomy?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 50);

        $this->getJson('/api/admin/works/taxonomy?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_invalid_status_visibility_sort_and_direction_return_422(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([
            'status' => 'not-a-status',
            'visibility_status' => 'private',
            'sort' => 'email',
            'direction' => 'sideways',
        ] as $parameter => $value) {
            $this->getJson($this->endpoint([$parameter => $value]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($parameter);
        }
    }

    public function test_to_before_from_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson($this->endpoint([
            'from' => '2026-07-15',
            'to' => '2026-07-14',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_range_over_ten_years_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson($this->endpoint([
            'from' => '2016-07-15',
            'to' => '2026-07-16',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_unknown_and_sensitive_query_parameters_return_422(): void
    {
        $this->actingAsRole('super-admin');
        $parameters = [
            'email' => 'private@example.test',
            'password' => 'secret',
            'token' => 'secret-token',
            'cookie' => 'secret-cookie',
            'internal_notes' => 'private',
            'rejection_reason' => 'private',
            'change_request_notes' => 'private',
            'payload' => 'private',
            'metadata' => 'private',
            'description' => 'private',
            'designer_id' => 1,
            'reviewer_id' => 1,
        ];

        $this->getJson($this->endpoint($parameters))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys($parameters));
    }

    public function test_summary_counts_all_required_taxonomy_fields(): void
    {
        $this->actingAsRole('super-admin');

        Work::factory()->published()->featured()->create([
            'category_id' => 10,
            'reports_count' => 3,
            'views_count' => 10,
            'likes_count' => 2,
        ]);
        Work::factory()->hidden()->pinned()->create([
            'category_id' => 10,
            'reports_count' => 0,
            'views_count' => 20,
            'likes_count' => 3,
        ]);
        Work::factory()->submitted()->create([
            'category_id' => 20,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'reports_count' => 0,
            'views_count' => 5,
            'likes_count' => 1,
        ]);
        Work::factory()->approved()->create([
            'category_id' => null,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'reports_count' => 2,
            'views_count' => 7,
            'likes_count' => 4,
        ]);

        $this->getJson('/api/admin/works/taxonomy?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.summary', [
                'total_categories' => 3,
                'categorized_categories' => 2,
                'uncategorized_buckets' => 1,
                'total_works' => 4,
                'categorized_works' => 3,
                'uncategorized_works' => 1,
                'reported_categories' => 2,
                'promoted_categories' => 1,
                'published_categories' => 1,
                'hidden_categories' => 2,
                'total_reports' => 5,
                'total_views' => 42,
                'total_likes' => 10,
                'catalog_categories_total' => 0,
                'active_catalog_categories' => 0,
                'disabled_catalog_categories' => 0,
                'used_catalog_categories' => 0,
                'unused_catalog_categories' => 0,
                'legacy_unmapped_category_ids' => 2,
                'works_with_legacy_unmapped_category' => 3,
                'tags_total' => 0,
                'active_tags' => 0,
                'disabled_tags' => 0,
                'used_tags' => 0,
                'unused_tags' => 0,
                'tag_assignments_total' => 0,
            ]);
    }

    public function test_summary_honors_the_same_active_filters_as_items(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->published()->featured()->create([
            'category_id' => 30,
            'media_type' => 'video',
            'reports_count' => 4,
            'views_count' => 10,
            'likes_count' => 2,
        ]);
        Work::factory()->published()->create([
            'category_id' => 30,
            'media_type' => 'image',
            'reports_count' => 5,
        ]);
        Work::factory()->approved()->featured()->create([
            'category_id' => 40,
            'media_type' => 'video',
            'reports_count' => 6,
        ]);

        $this->getJson($this->endpoint([
            'status' => Work::STATUS_PUBLISHED,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'media_type' => 'video',
            'only_reported' => 1,
            'only_promoted' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.category_id', 30)
            ->assertJsonPath('data.items.0.works_count', 1)
            ->assertJsonPath('data.summary.total_categories', 1)
            ->assertJsonPath('data.summary.total_works', 1)
            ->assertJsonPath('data.summary.total_reports', 4);
    }

    public function test_taxonomy_flags_are_calculated_correctly(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->published()->create([
            'category_id' => 1,
            'reports_count' => 0,
        ]);
        Work::factory()->create([
            'category_id' => 2,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'reports_count' => 1,
        ]);
        Work::factory()->hidden()->create([
            'category_id' => 3,
            'reports_count' => 0,
        ]);
        Work::factory()->featured()->create([
            'category_id' => 4,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'reports_count' => 0,
        ]);
        Work::factory()->create([
            'category_id' => null,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'reports_count' => 0,
        ]);

        $response = $this->getJson('/api/admin/works/taxonomy?per_page=50')
            ->assertOk();
        $items = collect($response->json('data.items'));
        $byCategory = $items->whereNotNull('category_id')->keyBy('category_id');
        $uncategorized = $items->firstWhere('category_id', null);

        $this->assertSame([
            'uncategorized' => false,
            'has_reports' => false,
            'has_published' => true,
            'has_hidden' => false,
            'is_promoted' => false,
            'needs_attention' => false,
        ], $byCategory->get(1)['taxonomy_flags']);
        $this->assertTrue($byCategory->get(2)['taxonomy_flags']['has_reports']);
        $this->assertTrue($byCategory->get(2)['taxonomy_flags']['needs_attention']);
        $this->assertTrue($byCategory->get(3)['taxonomy_flags']['has_hidden']);
        $this->assertTrue($byCategory->get(3)['taxonomy_flags']['needs_attention']);
        $this->assertTrue($byCategory->get(4)['taxonomy_flags']['is_promoted']);
        $this->assertFalse($byCategory->get(4)['taxonomy_flags']['needs_attention']);
        $this->assertTrue($uncategorized['taxonomy_flags']['uncategorized']);
        $this->assertTrue($uncategorized['taxonomy_flags']['needs_attention']);
    }

    public function test_category_and_tag_support_are_explicitly_available(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/taxonomy')
            ->assertOk()
            ->assertJsonPath('data.category_support.available', true)
            ->assertJsonPath('data.category_support.catalog_source', 'work_categories')
            ->assertJsonPath('data.category_support.work_reference', 'works.category_id')
            ->assertJsonPath('data.category_support.foreign_key_enforced', false)
            ->assertJsonPath('data.category_support.legacy_unmapped_values_possible', true)
            ->assertJsonPath('data.category_support.mapping_complete', true)
            ->assertJsonPath('data.tag_support.available', true)
            ->assertJsonPath('data.tag_support.catalog_source', 'work_tags')
            ->assertJsonPath('data.tag_support.assignments_source', 'work_tag_assignments')
            ->assertJsonPath(
                'data.tag_support.reason',
                'بنية الوسوم المستقلة متاحة للقراءة.',
            );
    }

    public function test_static_taxonomy_route_resolves_to_taxonomy_controller(): void
    {
        $this->actingAsRole('super-admin');

        $route = Route::getRoutes()->match(Request::create('/api/admin/works/taxonomy', 'GET'));

        $this->assertSame(
            WorksTaxonomyController::class.'@index',
            $route->getActionName(),
        );
        $this->getJson('/api/admin/works/taxonomy')
            ->assertOk()
            ->assertJsonPath('message', 'تم جلب تصنيفات الأعمال بنجاح');
    }

    /**
     * @param array<string, int|string> $filters
     */
    private function assertSingleCategory(array $filters, ?int $categoryId, int $worksCount): void
    {
        $this->getJson($this->endpoint($filters))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.summary.total_categories', 1)
            ->assertJsonPath('data.items.0.category_id', $categoryId)
            ->assertJsonPath('data.items.0.works_count', $worksCount)
            ->assertJsonCount(1, 'data.items');
    }

    private function assertFirstCategoryBySort(string $sort, string $direction, int $categoryId): void
    {
        $this->getJson($this->endpoint([
            'sort' => $sort,
            'direction' => $direction,
        ]))
            ->assertOk()
            ->assertJsonPath('data.filters.sort', $sort)
            ->assertJsonPath('data.filters.direction', $direction)
            ->assertJsonPath('data.items.0.category_id', $categoryId);
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function endpoint(array $parameters): string
    {
        return '/api/admin/works/taxonomy?'.http_build_query($parameters);
    }

    /**
     * @return list<string>
     */
    private function taxonomyPermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.taxonomy.view',
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
