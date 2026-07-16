<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksTaxonomyTagMergeController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkTag;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksTaxonomyTagMergeApiTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/admin/works/taxonomy/tags/merge';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->patchJson(self::ENDPOINT, [
            'target_tag_id' => 1,
            'source_tag_ids' => [2],
        ])->assertUnauthorized();
    }

    public function test_super_admin_can_merge_tags(): void
    {
        $this->actingAsRole('super-admin');
        [$target, $source] = WorkTag::factory()->count(2)->create();

        $this->patchJson(self::ENDPOINT, $this->payload($target, [$source]))
            ->assertOk()
            ->assertJsonPath('data.changed', true);
    }

    public function test_admin_and_staff_require_all_four_permissions(): void
    {
        foreach (['admin', 'staff'] as $role) {
            foreach ($this->mergePermissions() as $missingPermission) {
                $this->actingAsRole($role, array_values(array_diff($this->mergePermissions(), [$missingPermission])));
                [$target, $source] = WorkTag::factory()->count(2)->create();

                $this->patchJson(self::ENDPOINT, $this->payload($target, [$source]))->assertForbidden();
            }

            $this->actingAsRole($role, $this->mergePermissions());
            [$target, $source] = WorkTag::factory()->count(2)->create();
            $this->patchJson(self::ENDPOINT, $this->payload($target, [$source]))->assertOk();
        }
    }

    public function test_update_disable_and_bulk_assign_permissions_do_not_authorize_merge(): void
    {
        foreach ([
            'admin.works.taxonomy.tags.update',
            'admin.works.taxonomy.tags.disable',
            'admin.works.taxonomy.bulk_assign',
        ] as $unrelatedPermission) {
            $this->actingAsRole('admin', [
                'admin.works.access',
                'admin.works.taxonomy.view',
                'admin.works.taxonomy.tags.view',
                $unrelatedPermission,
            ]);
            [$target, $source] = WorkTag::factory()->count(2)->create();

            $this->patchJson(self::ENDPOINT, $this->payload($target, [$source]))->assertForbidden();
        }
    }

    public function test_client_designer_and_external_roles_are_forbidden(): void
    {
        foreach (['client', 'designer', 'external'] as $role) {
            if ($role === 'external') {
                Role::findOrCreate($role, 'web');
            }

            $this->actingAsRole($role, $this->mergePermissions());
            [$target, $source] = WorkTag::factory()->count(2)->create();
            $this->patchJson(self::ENDPOINT, $this->payload($target, [$source]))->assertForbidden();
        }
    }

    public function test_required_and_shape_validation_is_strict(): void
    {
        $this->actingAsRole('super-admin');
        [$target, $source] = WorkTag::factory()->count(2)->create();

        $this->patchJson(self::ENDPOINT, ['source_tag_ids' => [$source->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('target_tag_id');
        $this->patchJson(self::ENDPOINT, ['target_tag_id' => $target->id])
            ->assertUnprocessable()->assertJsonValidationErrors('source_tag_ids');
        $this->patchJson(self::ENDPOINT, ['target_tag_id' => $target->id, 'source_tag_ids' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('source_tag_ids');
        $this->patchJson(self::ENDPOINT, ['target_tag_id' => $target->id, 'source_tag_ids' => [$source->id, $source->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('source_tag_ids.1');
        $this->patchJson(self::ENDPOINT, ['target_tag_id' => $target->id, 'source_tag_ids' => range(1, 26)])
            ->assertUnprocessable()->assertJsonValidationErrors('source_tag_ids');
    }

    public function test_missing_disabled_and_overlapping_tags_are_rejected(): void
    {
        $this->actingAsRole('super-admin');
        $target = WorkTag::factory()->create();
        $source = WorkTag::factory()->create();
        $disabledTarget = WorkTag::factory()->disabled()->create();

        $this->patchJson(self::ENDPOINT, ['target_tag_id' => 999999, 'source_tag_ids' => [$source->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('target_tag_id');
        $this->patchJson(self::ENDPOINT, ['target_tag_id' => $disabledTarget->id, 'source_tag_ids' => [$source->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('target_tag_id');
        $this->patchJson(self::ENDPOINT, ['target_tag_id' => $target->id, 'source_tag_ids' => [999999]])
            ->assertUnprocessable()->assertJsonValidationErrors('source_tag_ids.0');
        $this->patchJson(self::ENDPOINT, ['target_tag_id' => $target->id, 'source_tag_ids' => [$target->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('source_tag_ids');

        $this->assertTrue($target->fresh()->isActive());
        $this->assertTrue($source->fresh()->isActive());
        $this->assertDatabaseCount('work_tag_assignments', 0);
        $this->assertMergeAuditCount(0);
    }

    public function test_query_parameters_and_unknown_or_sensitive_fields_are_rejected(): void
    {
        $this->actingAsRole('super-admin');
        [$target, $source] = WorkTag::factory()->count(2)->create();

        $this->patchJson(self::ENDPOINT.'?token=secret', $this->payload($target, [$source]))
            ->assertUnprocessable()->assertJsonValidationErrors('token');

        foreach (['target', 'tag_id', 'work_ids', 'delete_sources', 'metadata', 'payload', 'email', 'password', 'cookie'] as $field) {
            $this->patchJson(self::ENDPOINT, [
                ...$this->payload($target, [$source]),
                $field => 'unexpected',
            ])->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        $this->assertTrue($source->fresh()->isActive());
        $this->assertMergeAuditCount(0);
    }

    public function test_merge_collapses_duplicates_disables_only_active_sources_and_returns_safe_contract(): void
    {
        $this->actingAsRole('super-admin');
        $category = WorkCategory::factory()->create();
        $target = WorkTag::factory()->create([
            'name_ar' => 'الهدف',
            'name_en' => 'Target',
            'slug' => 'merge-target',
            'sort_order' => 30,
        ]);
        $activeSource = WorkTag::factory()->create([
            'name_ar' => 'مصدر فعال',
            'name_en' => 'Active Source',
            'slug' => 'active-source',
            'sort_order' => 20,
        ]);
        $oldDisabledAt = now()->subDays(5)->startOfSecond();
        $disabledSource = WorkTag::factory()->create([
            'name_ar' => 'مصدر معطل',
            'name_en' => 'Disabled Source',
            'slug' => 'disabled-source',
            'sort_order' => 10,
            'disabled_at' => $oldDisabledAt,
        ]);
        $works = Work::factory()->count(3)->create(['category_id' => $category->id]);
        $works[0]->tags()->attach($activeSource);
        $works[1]->tags()->attach([$activeSource->id, $disabledSource->id]);
        $works[2]->tags()->attach([$disabledSource->id, $target->id]);
        $targetUpdatedAt = $target->updated_at->toJSON();
        $disabledSourceUpdatedAt = $disabledSource->updated_at->toJSON();
        $workSnapshots = $works->mapWithKeys(fn (Work $work): array => [$work->id => [
            'category_id' => $work->category_id,
            'title' => $work->title,
            'updated_at' => $work->updated_at->toJSON(),
        ]]);
        $tagSnapshots = collect([$target, $activeSource, $disabledSource])->mapWithKeys(
            fn (WorkTag $tag): array => [$tag->id => [
                'name_ar' => $tag->name_ar,
                'name_en' => $tag->name_en,
                'slug' => $tag->slug,
                'sort_order' => $tag->sort_order,
            ]],
        );

        $response = $this->patchJson(self::ENDPOINT, [
            'target_tag_id' => $target->id,
            'source_tag_ids' => [$disabledSource->id, $activeSource->id],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.target_tag.id', $target->id)
            ->assertJsonPath('data.target_tag.works_count', 3)
            ->assertJsonPath('data.summary', [
                'source_tags_requested' => 2,
                'source_tags_disabled' => 1,
                'affected_works' => 3,
                'source_assignments_removed' => 4,
                'target_assignments_added' => 2,
                'duplicate_assignments_collapsed' => 2,
            ])
            ->assertJsonPath('message', 'تم دمج وسوم الأعمال بنجاح')
            ->assertJsonPath('errors', null);

        $sourcePayloads = $response->json('data.source_tags');
        $this->assertSame(collect([$activeSource->id, $disabledSource->id])->sort()->values()->all(), collect($sourcePayloads)->pluck('id')->all());
        $this->assertSafeTargetPayload($response->json('data.target_tag'));

        foreach ($sourcePayloads as $sourcePayload) {
            $this->assertSafeSourcePayload($sourcePayload);
            $this->assertSame(0, $sourcePayload['works_count']);
            $this->assertTrue($sourcePayload['merge_state']['is_disabled']);
        }

        $sourcePayloadsById = collect($sourcePayloads)->keyBy('id');
        $this->assertTrue($sourcePayloadsById[$activeSource->id]['merge_state']['was_active']);
        $this->assertFalse($sourcePayloadsById[$disabledSource->id]['merge_state']['was_active']);
        $this->assertSame(2, $sourcePayloadsById[$activeSource->id]['merge_state']['assignments_removed']);
        $this->assertSame(2, $sourcePayloadsById[$disabledSource->id]['merge_state']['assignments_removed']);

        $this->assertSame(3, DB::table('work_tag_assignments')->where('work_tag_id', $target->id)->count());
        $this->assertSame(0, DB::table('work_tag_assignments')->whereIn('work_tag_id', [$activeSource->id, $disabledSource->id])->count());
        $this->assertDatabaseCount('work_tags', 3);
        $this->assertTrue($target->fresh()->isActive());
        $this->assertFalse($activeSource->fresh()->isActive());
        $this->assertSame($oldDisabledAt->toJSON(), $disabledSource->fresh()->disabled_at->toJSON());
        $this->assertSame($targetUpdatedAt, $target->fresh()->updated_at->toJSON());
        $this->assertSame($disabledSourceUpdatedAt, $disabledSource->fresh()->updated_at->toJSON());

        foreach ($works as $work) {
            $freshWork = $work->fresh();
            $this->assertSame($workSnapshots[$work->id]['category_id'], $freshWork->category_id);
            $this->assertSame($workSnapshots[$work->id]['title'], $freshWork->title);
            $this->assertSame($workSnapshots[$work->id]['updated_at'], $freshWork->updated_at->toJSON());
        }

        foreach ([$target, $activeSource, $disabledSource] as $tag) {
            $freshTag = $tag->fresh();
            $this->assertSame($tagSnapshots[$tag->id]['name_ar'], $freshTag->name_ar);
            $this->assertSame($tagSnapshots[$tag->id]['name_en'], $freshTag->name_en);
            $this->assertSame($tagSnapshots[$tag->id]['slug'], $freshTag->slug);
            $this->assertSame($tagSnapshots[$tag->id]['sort_order'], $freshTag->sort_order);
        }

        $this->assertExactMergeAudit($target->id, [$activeSource->id, $disabledSource->id], [
            'source_tag_count' => 2,
            'source_tags_disabled' => 1,
            'affected_work_count' => 3,
            'source_assignments_removed' => 4,
            'target_assignments_added' => 2,
            'duplicate_assignments_collapsed' => 2,
        ]);
    }

    public function test_single_source_merge_moves_assignment_without_touching_work_timestamp(): void
    {
        $this->actingAsRole('super-admin');
        [$target, $source] = WorkTag::factory()->count(2)->create();
        $work = Work::factory()->create();
        $work->tags()->attach($source);
        $updatedAt = $work->updated_at->toJSON();

        $this->patchJson(self::ENDPOINT, $this->payload($target, [$source]))
            ->assertOk()
            ->assertJsonPath('data.summary.target_assignments_added', 1)
            ->assertJsonPath('data.summary.source_assignments_removed', 1)
            ->assertJsonPath('data.summary.duplicate_assignments_collapsed', 0);

        $this->assertDatabaseHas('work_tag_assignments', ['work_id' => $work->id, 'work_tag_id' => $target->id]);
        $this->assertDatabaseMissing('work_tag_assignments', ['work_id' => $work->id, 'work_tag_id' => $source->id]);
        $this->assertSame($updatedAt, $work->fresh()->updated_at->toJSON());
    }

    public function test_repeating_successful_merge_is_a_stable_no_op_without_timestamp_or_audit_changes(): void
    {
        $this->actingAsRole('super-admin');
        [$target, $source] = WorkTag::factory()->count(2)->create();
        $work = Work::factory()->create();
        $work->tags()->attach($source);
        $payload = $this->payload($target, [$source]);

        $this->patchJson(self::ENDPOINT, $payload)->assertOk()->assertJsonPath('data.changed', true);
        $source = $source->fresh();
        $target = $target->fresh();
        $sourceUpdatedAt = $source->updated_at->toJSON();
        $sourceDisabledAt = $source->disabled_at->toJSON();
        $targetUpdatedAt = $target->updated_at->toJSON();
        $workUpdatedAt = $work->fresh()->updated_at->toJSON();
        $auditCount = AuditEvent::query()->count();
        $this->travel(1)->minute();

        $this->patchJson(self::ENDPOINT, $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.summary', [
                'source_tags_requested' => 1,
                'source_tags_disabled' => 0,
                'affected_works' => 0,
                'source_assignments_removed' => 0,
                'target_assignments_added' => 0,
                'duplicate_assignments_collapsed' => 0,
            ])
            ->assertJsonPath('message', 'لم تتغير وسوم الأعمال المدمجة');

        $this->assertSame($sourceUpdatedAt, $source->fresh()->updated_at->toJSON());
        $this->assertSame($sourceDisabledAt, $source->fresh()->disabled_at->toJSON());
        $this->assertSame($targetUpdatedAt, $target->fresh()->updated_at->toJSON());
        $this->assertSame($workUpdatedAt, $work->fresh()->updated_at->toJSON());
        $this->assertSame($auditCount, AuditEvent::query()->count());
        $this->assertMergeAuditCount(1);
    }

    public function test_validation_failure_is_atomic_and_creates_no_merge_audit(): void
    {
        $this->actingAsRole('super-admin');
        [$target, $source] = WorkTag::factory()->count(2)->create();
        $work = Work::factory()->create();
        $work->tags()->attach($source);

        $this->patchJson(self::ENDPOINT, [
            'target_tag_id' => $target->id,
            'source_tag_ids' => [$source->id, 999999],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('work_tag_assignments', ['work_id' => $work->id, 'work_tag_id' => $source->id]);
        $this->assertDatabaseMissing('work_tag_assignments', ['work_id' => $work->id, 'work_tag_id' => $target->id]);
        $this->assertTrue($source->fresh()->isActive());
        $this->assertMergeAuditCount(0);
    }

    public function test_forbidden_and_validation_failures_create_no_merge_audit(): void
    {
        [$target, $source] = WorkTag::factory()->count(2)->create();
        $this->actingAsRole('client', $this->mergePermissions());
        $this->patchJson(self::ENDPOINT, $this->payload($target, [$source]))->assertForbidden();
        $this->assertMergeAuditCount(0);

        $this->actingAsRole('super-admin');
        $this->patchJson(self::ENDPOINT, ['target_tag_id' => $target->id, 'source_tag_ids' => []])
            ->assertUnprocessable();
        $this->assertMergeAuditCount(0);
    }

    public function test_tag_catalog_and_assignment_and_tag_actions_remain_compatible_after_merge(): void
    {
        $this->actingAsRole('super-admin');
        [$target, $source] = WorkTag::factory()->count(2)->create();
        $work = Work::factory()->create();
        $newWork = Work::factory()->create();
        $work->tags()->attach($source);

        $this->patchJson(self::ENDPOINT, $this->payload($target, [$source]))->assertOk();

        $this->getJson('/api/admin/works/taxonomy/tags')
            ->assertOk()
            ->assertJsonFragment(['id' => $target->id, 'works_count' => 1])
            ->assertJsonFragment(['id' => $source->id, 'works_count' => 0])
            ->assertJsonPath('data.summary.assignments_total', 1);
        $this->patchJson('/api/admin/works/'.$newWork->id.'/taxonomy/tags', ['tag_ids' => [$source->id]])
            ->assertUnprocessable();
        $this->patchJson('/api/admin/works/'.$newWork->id.'/taxonomy/tags', ['tag_ids' => [$target->id]])
            ->assertOk()->assertJsonPath('data.changed', true);
        $this->patchJson('/api/admin/works/taxonomy/tags/'.$source->id, ['name_en' => 'Renamed Disabled Source'])
            ->assertOk()->assertJsonPath('data.tag.is_active', false);
    }

    public function test_route_uses_patch_expected_controller_and_required_order_without_extra_merge_routes(): void
    {
        $mergeRoute = Route::getRoutes()->match(Request::create(self::ENDPOINT, 'PATCH'));
        $this->assertSame(WorksTaxonomyTagMergeController::class.'@merge', $mergeRoute->getActionName());
        $this->assertContains('auth:sanctum', $mergeRoute->gatherMiddleware());

        $routes = collect(Route::getRoutes()->getRoutes());
        $mergePosition = $routes->search(fn ($route): bool => $route->uri() === 'api/admin/works/taxonomy/tags/merge');
        $dynamicTagPosition = $routes->search(fn ($route): bool => $route->uri() === 'api/admin/works/taxonomy/tags/{tag}');
        $workShowPosition = $routes->search(fn ($route): bool => $route->uri() === 'api/admin/works/{work}');
        $this->assertIsInt($mergePosition);
        $this->assertIsInt($dynamicTagPosition);
        $this->assertIsInt($workShowPosition);
        $this->assertLessThan($dynamicTagPosition, $mergePosition);
        $this->assertLessThan($workShowPosition, $mergePosition);

        foreach (['POST', 'PUT', 'DELETE'] as $method) {
            $this->json($method, self::ENDPOINT)->assertMethodNotAllowed();
        }

        $this->actingAsRole('super-admin');
        $this->patchJson('/api/admin/works/taxonomy/categories/merge', [])->assertNotFound();
        $this->deleteJson('/api/admin/works/taxonomy/tags/1')->assertMethodNotAllowed();
        $this->patchJson('/api/admin/works/taxonomy/tags/1/restore', [])->assertNotFound();
    }

    /** @return list<string> */
    private function mergePermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.taxonomy.view',
            'admin.works.taxonomy.tags.view',
            'admin.works.taxonomy.merge_tags',
        ];
    }

    /** @param list<WorkTag> $sources @return array{target_tag_id: int, source_tag_ids: list<int>} */
    private function payload(WorkTag $target, array $sources): array
    {
        return [
            'target_tag_id' => $target->id,
            'source_tag_ids' => array_map(fn (WorkTag $source): int => $source->id, $sources),
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

    private function assertMergeAuditCount(int $expected): void
    {
        $this->assertSame(
            $expected,
            AuditEvent::query()->where('event_type', 'works.taxonomy.tags.merged')->count(),
        );
    }

    /** @param list<int> $sourceTagIds @param array<string, int> $expected */
    private function assertExactMergeAudit(int $targetTagId, array $sourceTagIds, array $expected): void
    {
        sort($sourceTagIds, SORT_NUMERIC);
        $event = AuditEvent::query()->where('event_type', 'works.taxonomy.tags.merged')->sole();
        $this->assertSame('works', $event->category);
        $this->assertSame('work_tag', $event->target_type);
        $this->assertSame($targetTagId, $event->target_id);
        $this->assertSame('merge', $event->action);
        $this->assertSame('success', $event->outcome);
        $this->assertSame([
            'target_tag_id' => $targetTagId,
            'source_tag_ids' => $sourceTagIds,
            ...$expected,
        ], $event->metadata);
    }

    /** @param array<string, mixed> $payload */
    private function assertSafeTargetPayload(array $payload): void
    {
        $this->assertSame([
            'disabled_at',
            'id',
            'is_active',
            'name_ar',
            'name_en',
            'slug',
            'sort_order',
            'works_count',
        ], $this->sortedKeys($payload));
    }

    /** @param array<string, mixed> $payload */
    private function assertSafeSourcePayload(array $payload): void
    {
        $this->assertSame([
            'disabled_at',
            'id',
            'is_active',
            'merge_state',
            'name_ar',
            'name_en',
            'slug',
            'sort_order',
            'works_count',
        ], $this->sortedKeys($payload));
        $this->assertSame([
            'assignments_removed',
            'is_disabled',
            'was_active',
        ], $this->sortedKeys($payload['merge_state']));
    }

    /** @param array<string, mixed> $values @return list<string> */
    private function sortedKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return $keys;
    }
}
