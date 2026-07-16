<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksTaxonomyCatalogController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkTag;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksTaxonomyCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private const CATEGORIES_ENDPOINT = '/api/admin/works/taxonomy/categories';

    private const TAGS_ENDPOINT = '/api/admin/works/taxonomy/tags';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_requests_get_401_for_both_catalogs(): void
    {
        foreach ([self::CATEGORIES_ENDPOINT, self::TAGS_ENDPOINT] as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }
    }

    public function test_super_admin_can_read_both_catalogs(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson(self::CATEGORIES_ENDPOINT)->assertOk();
        $this->getJson(self::TAGS_ENDPOINT)->assertOk();
    }

    public function test_admin_and_staff_require_access_taxonomy_view_and_the_resource_permission(): void
    {
        foreach (['admin', 'staff'] as $role) {
            foreach ([
                [],
                ['admin.works.taxonomy.view'],
                ['admin.works.access'],
                ['admin.works.access', 'admin.works.taxonomy.view'],
            ] as $permissions) {
                $this->actingAsRole($role, $permissions);
                $this->getJson(self::CATEGORIES_ENDPOINT)->assertForbidden();
                $this->getJson(self::TAGS_ENDPOINT)->assertForbidden();
            }

            $this->actingAsRole($role, $this->categoryPermissions());
            $this->getJson(self::CATEGORIES_ENDPOINT)->assertOk();

            $this->actingAsRole($role, $this->tagPermissions());
            $this->getJson(self::TAGS_ENDPOINT)->assertOk();
        }
    }

    public function test_category_and_tag_view_permissions_do_not_cross_authorize(): void
    {
        $this->actingAsRole('admin', $this->categoryPermissions());
        $this->getJson(self::CATEGORIES_ENDPOINT)->assertOk();
        $this->getJson(self::TAGS_ENDPOINT)->assertForbidden();

        $this->actingAsRole('admin', $this->tagPermissions());
        $this->getJson(self::TAGS_ENDPOINT)->assertOk();
        $this->getJson(self::CATEGORIES_ENDPOINT)->assertForbidden();
    }

    public function test_client_designer_and_non_internal_roles_are_forbidden_with_accidental_permissions(): void
    {
        $permissions = array_values(array_unique([
            ...$this->categoryPermissions(),
            ...$this->tagPermissions(),
        ]));

        foreach (['client', 'designer', 'external'] as $role) {
            if ($role === 'external') {
                Role::findOrCreate($role, 'web');
            }

            $this->actingAsRole($role, $permissions);
            $this->getJson(self::CATEGORIES_ENDPOINT)->assertForbidden();
            $this->getJson(self::TAGS_ENDPOINT)->assertForbidden();
        }
    }

    public function test_empty_category_catalog_returns_the_safe_contract_and_legacy_counts(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(4)->create(['category_id' => 1]);

        $this->getJson(self::CATEGORIES_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.pagination', [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 0,
                'last_page' => 1,
            ])
            ->assertJsonPath('data.summary', [
                'total' => 0,
                'active' => 0,
                'disabled' => 0,
                'used' => 0,
                'unused' => 0,
                'legacy_unmapped_category_ids' => 1,
                'works_with_legacy_unmapped_category' => 4,
            ])
            ->assertJsonPath('data.filters.states', ['all', 'active', 'disabled'])
            ->assertJsonPath('data.filters.per_page_options', [15, 25, 50])
            ->assertJsonPath('message', 'تم جلب كتالوج تصنيفات الأعمال بنجاح')
            ->assertJsonPath('errors', null);

        $this->assertDatabaseCount('work_categories', 0);
    }

    public function test_category_catalog_paginates_in_the_database(): void
    {
        $this->actingAsRole('super-admin');
        WorkCategory::factory()->count(16)->create();

        $this->getJson(self::CATEGORIES_ENDPOINT.'?page=2&per_page=15')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.total', 16)
            ->assertJsonPath('data.pagination.last_page', 2);
    }

    public function test_category_search_matches_only_names_and_slug(): void
    {
        $this->actingAsRole('super-admin');
        WorkCategory::factory()->create([
            'name_ar' => 'هوية مؤسسية',
            'name_en' => 'Corporate Identity',
            'slug' => 'identity-catalog',
            'created_at' => '2026-01-02 03:04:05',
        ]);
        WorkCategory::factory()->create([
            'name_ar' => 'رسوم متحركة',
            'name_en' => 'Motion Graphics',
            'slug' => 'motion-catalog',
        ]);

        foreach (['هوية', 'Corporate', 'identity-catalog'] as $query) {
            $this->getJson($this->endpoint(self::CATEGORIES_ENDPOINT, ['q' => $query]))
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 1)
                ->assertJsonPath('data.items.0.slug', 'identity-catalog');
        }

        $this->getJson($this->endpoint(self::CATEGORIES_ENDPOINT, ['q' => '2026-01']))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_category_state_filters_counts_flags_and_summary_are_correct(): void
    {
        $this->actingAsRole('super-admin');
        $used = WorkCategory::factory()->create(['name_en' => 'Alpha Used', 'slug' => 'alpha-used']);
        WorkCategory::factory()->disabled()->create(['name_en' => 'Beta Disabled', 'slug' => 'beta-disabled']);
        Work::factory()->count(2)->create(['category_id' => $used->id]);
        Work::factory()->count(3)->create(['category_id' => 999]);

        $this->getJson(self::CATEGORIES_ENDPOINT.'?state=active')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.works_count', 2)
            ->assertJsonPath('data.items.0.category_flags', [
                'is_used' => true,
                'is_unused' => false,
                'is_disabled' => false,
            ])
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.active', 1)
            ->assertJsonPath('data.summary.disabled', 0)
            ->assertJsonPath('data.summary.used', 1)
            ->assertJsonPath('data.summary.unused', 0)
            ->assertJsonPath('data.summary.legacy_unmapped_category_ids', 0)
            ->assertJsonPath('data.summary.works_with_legacy_unmapped_category', 0);

        $this->getJson(self::CATEGORIES_ENDPOINT.'?state=disabled')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.category_flags.is_unused', true)
            ->assertJsonPath('data.items.0.category_flags.is_disabled', true)
            ->assertJsonPath('data.summary.disabled', 1)
            ->assertJsonPath('data.summary.unused', 1);

        $this->getJson(self::CATEGORIES_ENDPOINT.'?state=all')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 2);
    }

    public function test_category_summary_honors_search_for_catalog_and_legacy_counts(): void
    {
        $this->actingAsRole('super-admin');
        $matching = WorkCategory::factory()->create(['name_en' => 'Matching Alpha', 'slug' => 'matching-alpha']);
        WorkCategory::factory()->disabled()->create(['name_en' => 'Other Beta', 'slug' => 'other-beta']);
        Work::factory()->create(['category_id' => $matching->id]);
        Work::factory()->count(2)->create(['category_id' => 700]);

        $this->getJson(self::CATEGORIES_ENDPOINT.'?q=Matching')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.active', 1)
            ->assertJsonPath('data.summary.disabled', 0)
            ->assertJsonPath('data.summary.used', 1)
            ->assertJsonPath('data.summary.unused', 0)
            ->assertJsonPath('data.summary.legacy_unmapped_category_ids', 0)
            ->assertJsonPath('data.summary.works_with_legacy_unmapped_category', 0);
    }

    public function test_all_category_sorts_and_directions_are_supported(): void
    {
        $this->actingAsRole('super-admin');
        $first = WorkCategory::factory()->create([
            'name_ar' => 'A Arabic',
            'name_en' => 'A English',
            'slug' => 'a-category',
            'sort_order' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $last = WorkCategory::factory()->create([
            'name_ar' => 'Z Arabic',
            'name_en' => 'Z English',
            'slug' => 'z-category',
            'sort_order' => 9,
            'created_at' => '2026-02-01 00:00:00',
            'updated_at' => '2026-02-01 00:00:00',
        ]);
        Work::factory()->create(['category_id' => $last->id]);

        foreach (['sort_order', 'name_ar', 'name_en', 'slug', 'works_count', 'created_at', 'updated_at'] as $sort) {
            $this->assertFirstCatalogItem(self::CATEGORIES_ENDPOINT, $sort, 'asc', $first->id);
            $this->assertFirstCatalogItem(self::CATEGORIES_ENDPOINT, $sort, 'desc', $last->id);
        }
    }

    public function test_category_payload_contains_only_the_approved_fields(): void
    {
        $this->actingAsRole('super-admin');
        WorkCategory::factory()->create();
        $item = $this->getJson(self::CATEGORIES_ENDPOINT)->assertOk()->json('data.items.0');

        $this->assertSame([
            'category_flags',
            'created_at',
            'disabled_at',
            'id',
            'is_active',
            'name_ar',
            'name_en',
            'slug',
            'sort_order',
            'updated_at',
            'works_count',
        ], collect(array_keys($item))->sort()->values()->all());
        $this->assertSame(
            ['is_disabled', 'is_unused', 'is_used'],
            collect(array_keys($item['category_flags']))->sort()->values()->all(),
        );
    }

    public function test_category_invalid_unknown_and_sensitive_parameters_return_422(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([
            'q' => 'x',
            'state' => 'hidden',
            'sort' => 'email',
            'direction' => 'sideways',
            'page' => 0,
            'per_page' => 20,
        ] as $key => $value) {
            $this->getJson($this->endpoint(self::CATEGORIES_ENDPOINT, [$key => $value]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($key);
        }

        $this->assertSensitiveParametersRejected(self::CATEGORIES_ENDPOINT);
    }

    public function test_empty_tag_catalog_returns_the_safe_contract(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson(self::TAGS_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.pagination.per_page', 15)
            ->assertJsonPath('data.summary', [
                'total' => 0,
                'active' => 0,
                'disabled' => 0,
                'used' => 0,
                'unused' => 0,
                'assignments_total' => 0,
            ])
            ->assertJsonPath('data.filters.states', ['all', 'active', 'disabled'])
            ->assertJsonPath('message', 'تم جلب كتالوج وسوم الأعمال بنجاح')
            ->assertJsonPath('errors', null);
    }

    public function test_tag_catalog_pagination_search_and_state_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        WorkTag::factory()->count(14)->create();
        WorkTag::factory()->create([
            'name_ar' => 'وسم مميز',
            'name_en' => 'Featured Marker',
            'slug' => 'featured-marker',
        ]);
        WorkTag::factory()->disabled()->create([
            'name_ar' => 'وسم معطل',
            'name_en' => 'Disabled Marker',
            'slug' => 'disabled-marker',
        ]);

        $this->getJson(self::TAGS_ENDPOINT.'?page=2&per_page=15')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.pagination.total', 16);

        foreach (['وسم مميز', 'Featured Marker', 'featured-marker'] as $query) {
            $this->getJson($this->endpoint(self::TAGS_ENDPOINT, ['q' => $query]))
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 1)
                ->assertJsonPath('data.items.0.slug', 'featured-marker');
        }

        $this->getJson(self::TAGS_ENDPOINT.'?state=disabled')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.slug', 'disabled-marker');
    }

    public function test_tag_counts_flags_and_filtered_assignment_summary_are_correct(): void
    {
        $this->actingAsRole('super-admin');
        $used = WorkTag::factory()->create(['name_en' => 'Matching Used', 'slug' => 'matching-used']);
        $other = WorkTag::factory()->create(['name_en' => 'Other Used', 'slug' => 'other-used']);
        WorkTag::factory()->disabled()->create(['name_en' => 'Unused Disabled', 'slug' => 'unused-disabled']);
        $works = Work::factory()->count(3)->create();
        $used->works()->attach($works->take(2)->modelKeys());
        $other->works()->attach($works->last()->id);

        $this->getJson(self::TAGS_ENDPOINT.'?q=Matching')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.works_count', 2)
            ->assertJsonPath('data.items.0.tag_flags', [
                'is_used' => true,
                'is_unused' => false,
                'is_disabled' => false,
            ])
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.used', 1)
            ->assertJsonPath('data.summary.unused', 0)
            ->assertJsonPath('data.summary.assignments_total', 2);

        $this->getJson(self::TAGS_ENDPOINT.'?state=disabled')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.disabled', 1)
            ->assertJsonPath('data.summary.unused', 1)
            ->assertJsonPath('data.summary.assignments_total', 0);
    }

    public function test_all_tag_sorts_and_directions_are_supported(): void
    {
        $this->actingAsRole('super-admin');
        $first = WorkTag::factory()->create([
            'name_ar' => 'A Arabic',
            'name_en' => 'A English',
            'slug' => 'a-tag',
            'sort_order' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $last = WorkTag::factory()->create([
            'name_ar' => 'Z Arabic',
            'name_en' => 'Z English',
            'slug' => 'z-tag',
            'sort_order' => 9,
            'created_at' => '2026-02-01 00:00:00',
            'updated_at' => '2026-02-01 00:00:00',
        ]);
        $work = Work::factory()->create();
        $last->works()->attach($work->id);

        foreach (['sort_order', 'name_ar', 'name_en', 'slug', 'works_count', 'created_at', 'updated_at'] as $sort) {
            $this->assertFirstCatalogItem(self::TAGS_ENDPOINT, $sort, 'asc', $first->id);
            $this->assertFirstCatalogItem(self::TAGS_ENDPOINT, $sort, 'desc', $last->id);
        }
    }

    public function test_tag_payload_contains_only_the_approved_fields(): void
    {
        $this->actingAsRole('super-admin');
        WorkTag::factory()->create();
        $item = $this->getJson(self::TAGS_ENDPOINT)->assertOk()->json('data.items.0');

        $this->assertSame([
            'created_at',
            'disabled_at',
            'id',
            'is_active',
            'name_ar',
            'name_en',
            'slug',
            'sort_order',
            'tag_flags',
            'updated_at',
            'works_count',
        ], collect(array_keys($item))->sort()->values()->all());
        $this->assertSame(
            ['is_disabled', 'is_unused', 'is_used'],
            collect(array_keys($item['tag_flags']))->sort()->values()->all(),
        );
    }

    public function test_tag_invalid_unknown_and_sensitive_parameters_return_422(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([
            'q' => 'x',
            'state' => 'hidden',
            'sort' => 'email',
            'direction' => 'sideways',
            'page' => 0,
            'per_page' => 20,
        ] as $key => $value) {
            $this->getJson($this->endpoint(self::TAGS_ENDPOINT, [$key => $value]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($key);
        }

        $this->assertSensitiveParametersRejected(self::TAGS_ENDPOINT);
    }

    public function test_catalog_read_routes_remain_static_and_unsupported_methods_are_rejected(): void
    {
        $categoryRoute = Route::getRoutes()->match(Request::create(self::CATEGORIES_ENDPOINT, 'GET'));
        $tagRoute = Route::getRoutes()->match(Request::create(self::TAGS_ENDPOINT, 'GET'));

        $this->assertSame(
            WorksTaxonomyCatalogController::class.'@categories',
            $categoryRoute->getActionName(),
        );
        $this->assertSame(
            WorksTaxonomyCatalogController::class.'@tags',
            $tagRoute->getActionName(),
        );
        $this->assertSame(['GET', 'HEAD'], $categoryRoute->methods());
        $this->assertSame(['GET', 'HEAD'], $tagRoute->methods());

        $routes = collect(Route::getRoutes()->getRoutes())->values();
        $categoryPosition = $routes->search(fn ($route): bool => $route->uri() === 'api/admin/works/taxonomy/categories');
        $tagPosition = $routes->search(fn ($route): bool => $route->uri() === 'api/admin/works/taxonomy/tags');
        $showPosition = $routes->search(fn ($route): bool => $route->uri() === 'api/admin/works/{work}');

        $this->assertIsInt($categoryPosition);
        $this->assertIsInt($tagPosition);
        $this->assertIsInt($showPosition);
        $this->assertLessThan($showPosition, $categoryPosition);
        $this->assertLessThan($showPosition, $tagPosition);

        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $this->json($method, self::CATEGORIES_ENDPOINT)->assertMethodNotAllowed();
        }

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->json($method, self::TAGS_ENDPOINT)->assertMethodNotAllowed();
        }

        $this->actingAsRole('super-admin');
        $this->getJson('/api/admin/works/taxonomy')->assertOk();
    }

    public function test_catalog_reads_do_not_modify_models_assignments_or_audit_events(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();
        $tag = WorkTag::factory()->create();
        $work = Work::factory()->create(['category_id' => 1]);
        $work->tags()->attach($tag->id);
        $categoryUpdatedAt = $category->updated_at?->toJSON();
        $tagUpdatedAt = $tag->updated_at?->toJSON();
        $workUpdatedAt = $work->updated_at?->toJSON();
        $auditCount = AuditEvent::query()->count();

        $this->getJson(self::CATEGORIES_ENDPOINT)->assertOk();
        $this->getJson(self::TAGS_ENDPOINT)->assertOk();

        $this->assertSame($categoryUpdatedAt, $category->refresh()->updated_at?->toJSON());
        $this->assertSame($tagUpdatedAt, $tag->refresh()->updated_at?->toJSON());
        $this->assertSame($workUpdatedAt, $work->refresh()->updated_at?->toJSON());
        $this->assertSame(1, $work->category_id);
        $this->assertDatabaseHas('work_tag_assignments', [
            'work_id' => $work->id,
            'work_tag_id' => $tag->id,
        ]);
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    public function test_unmapped_legacy_category_one_does_not_create_a_catalog_record(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(4)->create(['category_id' => 1]);

        $this->getJson(self::CATEGORIES_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.summary.legacy_unmapped_category_ids', 1)
            ->assertJsonPath('data.summary.works_with_legacy_unmapped_category', 4);

        $this->assertDatabaseCount('work_categories', 0);
        $this->assertDatabaseCount('work_tags', 0);
        $this->assertDatabaseCount('work_tag_assignments', 0);
        $this->assertSame([1], Work::query()->distinct()->pluck('category_id')->all());
    }

    /** @return list<string> */
    private function categoryPermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.taxonomy.view',
            'admin.works.taxonomy.categories.view',
        ];
    }

    /** @return list<string> */
    private function tagPermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.taxonomy.view',
            'admin.works.taxonomy.tags.view',
        ];
    }

    /** @param list<string> $permissions */
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

    /** @param array<string, int|string> $parameters */
    private function endpoint(string $endpoint, array $parameters): string
    {
        return $endpoint.'?'.http_build_query($parameters);
    }

    private function assertFirstCatalogItem(
        string $endpoint,
        string $sort,
        string $direction,
        int $expectedId,
    ): void {
        $this->getJson($this->endpoint($endpoint, [
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => 50,
        ]))
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $expectedId);
    }

    private function assertSensitiveParametersRejected(string $endpoint): void
    {
        $parameters = [
            'description' => 'private',
            'metadata' => 'private',
            'payload' => 'private',
            'work' => 1,
            'works' => 1,
            'users' => 1,
            'roles' => 1,
            'permissions' => 1,
            'include' => 'all',
            'with' => 'works',
            'raw' => 1,
            'disabled_at' => '2026-01-01',
            'is_active' => 1,
            'category_id' => 1,
            'tag_ids' => 1,
            'email' => 'private@example.test',
            'password' => 'secret',
            'token' => 'secret-token',
            'cookie' => 'secret-cookie',
        ];

        $this->getJson($this->endpoint($endpoint, $parameters))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys($parameters));
    }
}
