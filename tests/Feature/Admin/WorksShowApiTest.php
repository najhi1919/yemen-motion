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

class WorksShowApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $work = Work::factory()->create();

        $this->getJson($this->endpoint($work))
            ->assertUnauthorized();
    }

    public function test_super_admin_can_view_work_detail(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->submitted()->create([
            'description' => 'never-return-super-description',
        ]);

        $response = $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.work.id', $work->id)
            ->assertJsonPath('message', 'تم جلب تفاصيل العمل بنجاح')
            ->assertJsonPath('errors', null);

        $this->assertStringNotContainsString('never-return-super-description', $response->getContent());
    }

    public function test_missing_work_returns_404_for_authorized_user(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/999999999')
            ->assertNotFound();
    }

    public function test_admin_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('admin');
        $work = Work::factory()->create();

        $this->getJson($this->endpoint($work))
            ->assertForbidden();
    }

    public function test_staff_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('staff');
        $work = Work::factory()->create();

        $this->getJson($this->endpoint($work))
            ->assertForbidden();
    }

    public function test_admin_without_detail_permission_gets_403(): void
    {
        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.all.view',
        ]);
        $work = Work::factory()->create();

        $this->getJson($this->endpoint($work))
            ->assertForbidden();
    }

    public function test_admin_and_staff_with_base_detail_permissions_can_view_safe_detail(): void
    {
        $work = Work::factory()->create();

        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, $this->basePermissions());

            $this->getJson($this->endpoint($work))
                ->assertOk()
                ->assertJsonPath('data.work.id', $work->id)
                ->assertJsonPath('data.relations.designer', null)
                ->assertJsonPath('data.relations.reviewer', null)
                ->assertJsonPath('data.media', null)
                ->assertJsonPath('data.private_notes', null);
        }
    }

    public function test_client_and_designer_with_accidental_permissions_get_403(): void
    {
        $work = Work::factory()->create();
        $permissions = [...$this->basePermissions(), ...$this->optionalPermissions()];

        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $permissions);

            $this->getJson($this->endpoint($work))
                ->assertForbidden();
        }
    }

    public function test_base_work_shape_contains_only_allowed_keys(): void
    {
        $this->actingAsRole('admin', $this->basePermissions());
        $work = Work::factory()->approved()->create();

        $response = $this->getJson($this->endpoint($work))
            ->assertOk();

        $this->assertSame([
            'approved_at',
            'archived_at',
            'category_id',
            'created_at',
            'delivery_days',
            'hidden_at',
            'id',
            'is_featured',
            'is_pinned',
            'likes_count',
            'media_type',
            'price_amount',
            'published_at',
            'rejected_at',
            'reports_count',
            'reviewed_at',
            'slug',
            'status',
            'submitted_at',
            'summary',
            'title',
            'updated_at',
            'views_count',
            'visibility_status',
        ], collect(array_keys($response->json('data.work')))->sort()->values()->all());
        $this->assertSame(
            ['field_access', 'media', 'private_notes', 'relations', 'taxonomy', 'taxonomy_access', 'work'],
            collect(array_keys($response->json('data')))->sort()->values()->all(),
        );
        $this->assertSame([
            'category' => null,
            'category_tracking' => null,
            'tags' => null,
        ], $response->json('data.taxonomy'));
        $this->assertSame([
            'can_view_category' => false,
            'can_view_tags' => false,
        ], $response->json('data.taxonomy_access'));
    }

    public function test_super_admin_receives_safe_taxonomy_snapshot_in_show(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->disabled()->create(['sort_order' => 12]);
        $work = Work::factory()->create(['category_id' => $category->id]);
        $last = WorkTag::factory()->create(['sort_order' => 30]);
        $firstHigherId = WorkTag::factory()->disabled()->create(['sort_order' => 4]);
        $firstLowerId = WorkTag::factory()->create(['sort_order' => 4]);
        $work->tags()->attach([$last->id, $firstHigherId->id, $firstLowerId->id]);

        $response = $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.taxonomy_access', [
                'can_view_category' => true,
                'can_view_tags' => true,
            ])
            ->assertJsonPath('data.taxonomy.category.id', $category->id)
            ->assertJsonPath('data.taxonomy.category.is_active', false)
            ->assertJsonPath('data.taxonomy.category_tracking.catalog_record_exists', true);

        $taxonomy = $response->json('data.taxonomy');
        $expectedTagIds = collect([$last, $firstHigherId, $firstLowerId])
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

    public function test_show_taxonomy_sections_are_independently_permission_scoped(): void
    {
        $category = WorkCategory::factory()->create();
        $tag = WorkTag::factory()->create();
        $work = Work::factory()->create(['category_id' => $category->id]);
        $work->tags()->attach($tag);

        $this->actingAsRole('admin', [...$this->basePermissions(),
            'admin.works.taxonomy.view',
            'admin.works.taxonomy.categories.view',
        ]);
        $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.taxonomy_access.can_view_category', true)
            ->assertJsonPath('data.taxonomy_access.can_view_tags', false)
            ->assertJsonPath('data.taxonomy.category.id', $category->id)
            ->assertJsonPath('data.taxonomy.tags', null);

        $this->actingAsRole('staff', [...$this->basePermissions(),
            'admin.works.taxonomy.view',
            'admin.works.taxonomy.tags.view',
        ]);
        $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.taxonomy_access.can_view_category', false)
            ->assertJsonPath('data.taxonomy_access.can_view_tags', true)
            ->assertJsonPath('data.taxonomy.category', null)
            ->assertJsonPath('data.taxonomy.category_tracking', null)
            ->assertJsonPath('data.taxonomy.tags.0.id', $tag->id);
    }

    public function test_show_without_taxonomy_view_keeps_work_readable_and_does_not_accept_update_permissions_as_view(): void
    {
        $work = Work::factory()->create();
        $this->actingAsRole('admin', [...$this->basePermissions(),
            'admin.works.update.category',
            'admin.works.update.tags',
            'admin.works.bulk.category_update',
            'admin.works.bulk.tags_update',
        ]);

        $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.work.id', $work->id)
            ->assertJsonPath('data.taxonomy_access', [
                'can_view_category' => false,
                'can_view_tags' => false,
            ])
            ->assertJsonPath('data.taxonomy', [
                'category' => null,
                'category_tracking' => null,
                'tags' => null,
            ]);
    }

    public function test_show_distinguishes_uncategorized_and_legacy_and_empty_tags(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();
        $legacyCategoryId = $category->id + 100_000;
        $uncategorized = Work::factory()->create(['category_id' => null]);
        $legacy = Work::factory()->create(['category_id' => $legacyCategoryId]);

        $this->getJson($this->endpoint($uncategorized))
            ->assertOk()
            ->assertJsonPath('data.taxonomy.category', null)
            ->assertJsonPath('data.taxonomy.category_tracking', [
                'catalog_record_exists' => false,
                'is_legacy_unmapped' => false,
                'is_uncategorized' => true,
            ])
            ->assertJsonPath('data.taxonomy.tags', []);

        $this->getJson($this->endpoint($legacy))
            ->assertOk()
            ->assertJsonPath('data.work.category_id', $legacyCategoryId)
            ->assertJsonPath('data.taxonomy.category', null)
            ->assertJsonPath('data.taxonomy.category_tracking', [
                'catalog_record_exists' => false,
                'is_legacy_unmapped' => true,
                'is_uncategorized' => false,
            ])
            ->assertJsonPath('data.taxonomy.tags', []);
    }

    public function test_response_does_not_expose_disallowed_or_sensitive_fields(): void
    {
        $this->actingAsRole('admin', $this->basePermissions());
        $designer = User::factory()->create([
            'name' => 'Hidden Sensitive Designer',
            'email' => 'hidden-sensitive@example.test',
            'password' => 'hidden-password-marker',
        ]);
        $work = Work::factory()->create([
            'designer_id' => $designer->id,
            'reviewer_id' => $designer->id,
            'description' => 'hidden-description-marker',
            'internal_notes' => 'hidden-internal-marker',
            'rejection_reason' => 'hidden-rejection-marker',
            'change_request_notes' => 'hidden-change-marker',
        ]);

        $response = $this->getJson($this->endpoint($work))
            ->assertOk();
        $keys = $this->recursiveKeys($response->json());
        $json = strtolower($response->getContent());

        foreach ([
            'description',
            'internal_notes',
            'rejection_reason',
            'change_request_notes',
            'designer_id',
            'reviewer_id',
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
            'hidden sensitive designer',
            'hidden-sensitive@example.test',
            'hidden-password-marker',
            'hidden-description-marker',
            'hidden-internal-marker',
            'hidden-rejection-marker',
            'hidden-change-marker',
        ] as $forbiddenValue) {
            $this->assertStringNotContainsString($forbiddenValue, $json);
        }
    }

    public function test_without_designer_permission_relations_are_null_without_identity_leakage(): void
    {
        $this->actingAsRole('admin', $this->basePermissions());
        $designer = User::factory()->create([
            'name' => 'Confidential Designer Name',
            'email' => 'confidential-designer@example.test',
        ]);
        $reviewer = User::factory()->create([
            'name' => 'Confidential Reviewer Name',
            'email' => 'confidential-reviewer@example.test',
        ]);
        $work = Work::factory()->create([
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
        ]);

        $response = $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.relations.designer', null)
            ->assertJsonPath('data.relations.reviewer', null)
            ->assertJsonPath('data.field_access.can_view_designer', false);
        $json = strtolower($response->getContent());

        foreach ([$designer->name, $designer->email, $reviewer->name, $reviewer->email] as $value) {
            $this->assertStringNotContainsString(strtolower($value), $json);
        }
    }

    public function test_with_designer_permission_relations_expose_only_id_and_name(): void
    {
        $this->actingAsRole('admin', [
            ...$this->basePermissions(),
            'admin.works.designer.view',
        ]);
        $designer = User::factory()->create([
            'name' => 'Visible Designer Name',
            'email' => 'private-visible-designer@example.test',
        ]);
        $reviewer = User::factory()->create([
            'name' => 'Visible Reviewer Name',
            'email' => 'private-visible-reviewer@example.test',
        ]);
        $work = Work::factory()->create([
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
        ]);

        $response = $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.relations.designer', [
                'id' => $designer->id,
                'name' => $designer->name,
            ])
            ->assertJsonPath('data.relations.reviewer', [
                'id' => $reviewer->id,
                'name' => $reviewer->name,
            ])
            ->assertJsonPath('data.field_access.can_view_designer', true);

        $this->assertSame(['id', 'name'], array_keys($response->json('data.relations.designer')));
        $this->assertSame(['id', 'name'], array_keys($response->json('data.relations.reviewer')));
        $this->assertStringNotContainsString($designer->email, $response->getContent());
        $this->assertStringNotContainsString($reviewer->email, $response->getContent());
    }

    public function test_without_media_permission_media_is_null(): void
    {
        $this->actingAsRole('admin', $this->basePermissions());
        $work = Work::factory()->create(['media_type' => 'image']);

        $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.media', null)
            ->assertJsonPath('data.field_access.can_view_media', false);
    }

    public function test_with_media_permission_media_contains_only_type_and_presence_flag(): void
    {
        $this->actingAsRole('admin', [
            ...$this->basePermissions(),
            'admin.works.media.view',
        ]);
        $workWithMedia = Work::factory()->create(['media_type' => 'image']);
        $workWithoutMedia = Work::factory()->create(['media_type' => null]);

        $withMedia = $this->getJson($this->endpoint($workWithMedia))
            ->assertOk()
            ->assertJsonPath('data.media', [
                'media_type' => 'image',
                'has_media' => true,
            ])
            ->assertJsonPath('data.field_access.can_view_media', true);
        $withoutMedia = $this->getJson($this->endpoint($workWithoutMedia))
            ->assertOk()
            ->assertJsonPath('data.media', [
                'media_type' => null,
                'has_media' => false,
            ]);

        $this->assertSame(['has_media', 'media_type'], collect(array_keys($withMedia->json('data.media')))->sort()->values()->all());
        $this->assertSame(['has_media', 'media_type'], collect(array_keys($withoutMedia->json('data.media')))->sort()->values()->all());
    }

    public function test_without_private_notes_permission_private_values_are_not_exposed(): void
    {
        $this->actingAsRole('admin', $this->basePermissions());
        $work = Work::factory()->create([
            'internal_notes' => 'blocked-internal-value',
            'rejection_reason' => 'blocked-rejection-value',
            'change_request_notes' => 'blocked-change-value',
        ]);

        $response = $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.private_notes', null)
            ->assertJsonPath('data.field_access.can_view_private_notes', false);

        foreach (['blocked-internal-value', 'blocked-rejection-value', 'blocked-change-value'] as $value) {
            $this->assertStringNotContainsString($value, $response->getContent());
        }
    }

    public function test_with_private_notes_permission_private_notes_are_returned_explicitly(): void
    {
        $this->actingAsRole('admin', [
            ...$this->basePermissions(),
            'admin.works.private_notes.view',
        ]);
        $work = Work::factory()->create([
            'internal_notes' => 'allowed-internal-value',
            'rejection_reason' => 'allowed-rejection-value',
            'change_request_notes' => 'allowed-change-value',
        ]);

        $response = $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.private_notes', [
                'internal_notes' => 'allowed-internal-value',
                'rejection_reason' => 'allowed-rejection-value',
                'change_request_notes' => 'allowed-change-value',
            ])
            ->assertJsonPath('data.field_access.can_view_private_notes', true);

        $this->assertSame(
            ['change_request_notes', 'internal_notes', 'rejection_reason'],
            collect(array_keys($response->json('data.private_notes')))->sort()->values()->all(),
        );
    }

    public function test_metadata_permission_sets_flag_without_returning_metadata_section(): void
    {
        $this->actingAsRole('admin', [
            ...$this->basePermissions(),
            'admin.works.metadata.view',
        ]);
        $work = Work::factory()->create();

        $response = $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.field_access.can_view_metadata', true)
            ->assertJsonMissingPath('data.metadata');

        $this->assertArrayNotHasKey('metadata', $response->json('data'));
    }

    public function test_super_admin_field_access_flags_are_all_true(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create();

        $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.field_access', [
                'can_view_designer' => true,
                'can_view_media' => true,
                'can_view_metadata' => true,
                'can_view_private_notes' => true,
            ]);
    }

    public function test_base_admin_field_access_flags_are_all_false(): void
    {
        $this->actingAsRole('admin', $this->basePermissions());
        $work = Work::factory()->create();

        $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.field_access', [
                'can_view_designer' => false,
                'can_view_media' => false,
                'can_view_metadata' => false,
                'can_view_private_notes' => false,
            ]);
    }

    public function test_optional_permissions_set_their_field_access_flags(): void
    {
        $this->actingAsRole('staff', [
            ...$this->basePermissions(),
            ...$this->optionalPermissions(),
        ]);
        $work = Work::factory()->create();

        $this->getJson($this->endpoint($work))
            ->assertOk()
            ->assertJsonPath('data.field_access', [
                'can_view_designer' => true,
                'can_view_media' => true,
                'can_view_metadata' => true,
                'can_view_private_notes' => true,
            ]);
    }

    public function test_unknown_and_sensitive_query_parameters_return_422(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create();
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

        $this->getJson($this->endpoint($work).'?'.http_build_query($parameters))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys($parameters));
    }

    public function test_route_accepts_only_numeric_work_ids_and_static_routes_remain_resolved(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/not-a-number')
            ->assertNotFound();
        $this->getJson('/api/admin/works/access')
            ->assertOk()
            ->assertJsonPath('data.base_route', '/admin/works');
        $this->getJson('/api/admin/works/overview')
            ->assertOk()
            ->assertJsonStructure(['data' => ['summary', 'series']]);
    }

    private function endpoint(Work $work): string
    {
        return '/api/admin/works/'.$work->id;
    }

    /**
     * @return list<string>
     */
    private function basePermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.all.view',
            'admin.works.detail.view',
        ];
    }

    /**
     * @return list<string>
     */
    private function optionalPermissions(): array
    {
        return [
            'admin.works.designer.view',
            'admin.works.media.view',
            'admin.works.metadata.view',
            'admin.works.private_notes.view',
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
