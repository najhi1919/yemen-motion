<?php

namespace Tests\Feature\Admin;

use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkTag;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class WorksTaxonomySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_categories_table_matches_the_required_contract(): void
    {
        $this->assertTrue(Schema::hasTable('work_categories'));
        $this->assertSameCanonicalColumns('work_categories', [
            'id',
            'name_ar',
            'name_en',
            'slug',
            'disabled_at',
            'sort_order',
            'created_at',
            'updated_at',
        ]);
        $this->assertTrue(Schema::hasIndex('work_categories', ['slug'], 'unique'));
        $this->assertTrue(Schema::hasIndex('work_categories', ['disabled_at', 'sort_order', 'id']));
    }

    public function test_work_tags_table_matches_the_required_contract(): void
    {
        $this->assertTrue(Schema::hasTable('work_tags'));
        $this->assertSameCanonicalColumns('work_tags', [
            'id',
            'name_ar',
            'name_en',
            'slug',
            'disabled_at',
            'sort_order',
            'created_at',
            'updated_at',
        ]);
        $this->assertTrue(Schema::hasIndex('work_tags', ['slug'], 'unique'));
        $this->assertTrue(Schema::hasIndex('work_tags', ['disabled_at', 'sort_order', 'id']));
    }

    public function test_work_tag_assignments_table_matches_the_required_contract(): void
    {
        $this->assertTrue(Schema::hasTable('work_tag_assignments'));
        $this->assertSameCanonicalColumns('work_tag_assignments', ['work_id', 'work_tag_id']);
        $this->assertFalse(Schema::hasColumn('work_tag_assignments', 'id'));
        $this->assertFalse(Schema::hasColumn('work_tag_assignments', 'created_at'));
        $this->assertFalse(Schema::hasColumn('work_tag_assignments', 'updated_at'));
        $this->assertTrue(Schema::hasIndex(
            'work_tag_assignments',
            ['work_id', 'work_tag_id'],
            'primary',
        ));
        $this->assertTrue(Schema::hasIndex(
            'work_tag_assignments',
            ['work_tag_id', 'work_id'],
        ));
    }

    public function test_pivot_foreign_keys_use_the_required_delete_policies(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('work_tag_assignments'))
            ->keyBy(fn (array $foreignKey): string => $foreignKey['columns'][0]);

        $this->assertSame('works', $foreignKeys['work_id']['foreign_table']);
        $this->assertSame('cascade', strtolower((string) $foreignKeys['work_id']['on_delete']));
        $this->assertSame('work_tags', $foreignKeys['work_tag_id']['foreign_table']);
        $this->assertContains(
            strtolower((string) $foreignKeys['work_tag_id']['on_delete']),
            ['restrict', 'no action'],
        );
    }

    public function test_work_category_slug_is_unique(): void
    {
        WorkCategory::factory()->create(['slug' => 'unique-category']);

        $this->expectException(QueryException::class);

        WorkCategory::factory()->create(['slug' => 'unique-category']);
    }

    public function test_work_tag_slug_is_unique(): void
    {
        WorkTag::factory()->create(['slug' => 'unique-tag']);

        $this->expectException(QueryException::class);

        WorkTag::factory()->create(['slug' => 'unique-tag']);
    }

    public function test_factories_create_valid_active_taxonomy_models(): void
    {
        $category = WorkCategory::factory()->create();
        $tag = WorkTag::factory()->create();

        $this->assertNotSame('', $category->name_ar);
        $this->assertNotSame('', $category->name_en);
        $this->assertSame(strtolower($category->slug), $category->slug);
        $this->assertNull($category->disabled_at);
        $this->assertSame(0, $category->sort_order);
        $this->assertTrue($category->isActive());

        $this->assertNotSame('', $tag->name_ar);
        $this->assertNotSame('', $tag->name_en);
        $this->assertSame(strtolower($tag->slug), $tag->slug);
        $this->assertNull($tag->disabled_at);
        $this->assertSame(0, $tag->sort_order);
        $this->assertTrue($tag->isActive());
    }

    public function test_disabled_and_ordered_factory_states_are_valid(): void
    {
        $category = WorkCategory::factory()->disabled()->ordered(7)->create();
        $tag = WorkTag::factory()->disabled()->ordered(9)->create();

        $this->assertNotNull($category->disabled_at);
        $this->assertFalse($category->isActive());
        $this->assertSame(7, $category->sort_order);

        $this->assertNotNull($tag->disabled_at);
        $this->assertFalse($tag->isActive());
        $this->assertSame(9, $tag->sort_order);
    }

    public function test_ordered_factory_states_reject_negative_values(): void
    {
        try {
            WorkCategory::factory()->ordered(-1);
            $this->fail('The category factory accepted a negative sort order.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        WorkTag::factory()->ordered(-1);
    }

    public function test_category_active_and_disabled_scopes_work(): void
    {
        $active = WorkCategory::factory()->create();
        $disabled = WorkCategory::factory()->disabled()->create();

        $this->assertSame([$active->id], WorkCategory::query()->active()->pluck('id')->all());
        $this->assertSame([$disabled->id], WorkCategory::query()->disabled()->pluck('id')->all());
    }

    public function test_tag_active_and_disabled_scopes_work(): void
    {
        $active = WorkTag::factory()->create();
        $disabled = WorkTag::factory()->disabled()->create();

        $this->assertSame([$active->id], WorkTag::query()->active()->pluck('id')->all());
        $this->assertSame([$disabled->id], WorkTag::query()->disabled()->pluck('id')->all());
    }

    public function test_ordered_scopes_sort_by_sort_order_then_id(): void
    {
        $categoryLater = WorkCategory::factory()->ordered(4)->create();
        $categoryFirst = WorkCategory::factory()->ordered(1)->create();
        $categorySecond = WorkCategory::factory()->ordered(1)->create();

        $tagLater = WorkTag::factory()->ordered(4)->create();
        $tagFirst = WorkTag::factory()->ordered(1)->create();
        $tagSecond = WorkTag::factory()->ordered(1)->create();

        $this->assertSame(
            [$categoryFirst->id, $categorySecond->id, $categoryLater->id],
            WorkCategory::query()->ordered()->pluck('id')->all(),
        );
        $this->assertSame(
            [$tagFirst->id, $tagSecond->id, $tagLater->id],
            WorkTag::query()->ordered()->pluck('id')->all(),
        );
    }

    public function test_work_category_relations_return_only_matching_models(): void
    {
        $category = WorkCategory::factory()->create();
        $otherCategory = WorkCategory::factory()->create();
        $linkedWorks = Work::factory()->count(2)->create(['category_id' => $category->id]);
        Work::factory()->create(['category_id' => $otherCategory->id]);

        $this->assertTrue($linkedWorks->first()->category->is($category));
        $this->assertEqualsCanonicalizing(
            $linkedWorks->modelKeys(),
            $category->works()->pluck('id')->all(),
        );
    }

    public function test_unmatched_category_id_remains_valid_and_resolves_to_null(): void
    {
        $work = Work::factory()->create(['category_id' => 999999]);

        $this->assertSame(999999, $work->category_id);
        $this->assertNull($work->category);
        $this->assertDatabaseHas('works', ['id' => $work->id, 'category_id' => 999999]);
    }

    public function test_work_and_tag_relations_return_only_linked_models(): void
    {
        $work = Work::factory()->create();
        $otherWork = Work::factory()->create();
        $linkedTags = WorkTag::factory()->count(2)->create();
        $otherTag = WorkTag::factory()->create();

        $work->tags()->attach($linkedTags->modelKeys());
        $otherWork->tags()->attach($otherTag->id);

        $this->assertEqualsCanonicalizing(
            $linkedTags->modelKeys(),
            $work->tags()->pluck('work_tags.id')->all(),
        );
        $this->assertSame([$otherWork->id], $otherTag->works()->pluck('works.id')->all());
    }

    public function test_duplicate_work_tag_assignment_is_rejected(): void
    {
        $work = Work::factory()->create();
        $tag = WorkTag::factory()->create();
        $assignment = ['work_id' => $work->id, 'work_tag_id' => $tag->id];
        DB::table('work_tag_assignments')->insert($assignment);

        $this->expectException(QueryException::class);

        DB::table('work_tag_assignments')->insert($assignment);
    }

    public function test_deleting_work_cascades_its_tag_assignments(): void
    {
        $work = Work::factory()->create();
        $tag = WorkTag::factory()->create();
        $work->tags()->attach($tag->id);

        $work->delete();

        $this->assertDatabaseMissing('work_tag_assignments', [
            'work_id' => $work->id,
            'work_tag_id' => $tag->id,
        ]);
        $this->assertDatabaseHas('work_tags', ['id' => $tag->id]);
    }

    public function test_deleting_an_assigned_work_tag_is_rejected(): void
    {
        $work = Work::factory()->create();
        $tag = WorkTag::factory()->create();
        $work->tags()->attach($tag->id);

        $this->expectException(QueryException::class);

        $tag->delete();
    }

    public function test_deleting_an_unassigned_work_tag_is_allowed(): void
    {
        $tag = WorkTag::factory()->create();

        $tag->delete();

        $this->assertDatabaseMissing('work_tags', ['id' => $tag->id]);
    }

    public function test_detaching_assignment_does_not_delete_work_or_tag(): void
    {
        $work = Work::factory()->create();
        $tag = WorkTag::factory()->create();
        $work->tags()->attach($tag->id);

        $work->tags()->detach($tag->id);

        $this->assertDatabaseMissing('work_tag_assignments', [
            'work_id' => $work->id,
            'work_tag_id' => $tag->id,
        ]);
        $this->assertDatabaseHas('works', ['id' => $work->id]);
        $this->assertDatabaseHas('work_tags', ['id' => $tag->id]);
    }

    public function test_migrations_and_work_creation_do_not_create_taxonomy_records(): void
    {
        $this->assertDatabaseCount('work_categories', 0);
        $this->assertDatabaseCount('work_tags', 0);

        Work::factory()->create(['category_id' => 1]);

        $this->assertDatabaseCount('work_categories', 0);
        $this->assertDatabaseCount('work_tags', 0);
    }

    public function test_legacy_category_column_is_unchanged_and_no_transition_column_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('works', 'category_id'));
        $this->assertFalse(Schema::hasColumn('works', 'work_category_id'));

        $uncategorized = Work::factory()->create(['category_id' => null]);
        $unmatched = Work::factory()->create(['category_id' => 876543]);

        $this->assertNull($uncategorized->category_id);
        $this->assertSame(876543, $unmatched->category_id);
        $this->assertNull($unmatched->category);
    }

    public function test_work_lifecycle_has_no_taxonomy_model_event_side_effects(): void
    {
        $work = Work::factory()->create(['category_id' => 321]);
        $work->update(['category_id' => 654]);
        $work->delete();

        $this->assertDatabaseCount('work_categories', 0);
        $this->assertDatabaseCount('work_tags', 0);
        $this->assertDatabaseCount('work_tag_assignments', 0);
    }

    public function test_no_taxonomy_schema_routes_or_api_actions_are_registered(): void
    {
        $unexpectedRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route): bool {
                $routeSignature = $route->uri().' '.$route->getActionName();

                return str_contains($routeSignature, 'work-categories')
                    || str_contains($routeSignature, 'work-tags')
                    || str_contains($routeSignature, 'work-tag-assignments')
                    || str_contains($routeSignature, WorkCategory::class)
                    || str_contains($routeSignature, WorkTag::class);
            });

        $this->assertCount(0, $unexpectedRoutes);
    }

    /** @param list<string> $expectedColumns */
    private function assertSameCanonicalColumns(string $table, array $expectedColumns): void
    {
        $actualColumns = Schema::getColumnListing($table);
        sort($actualColumns);
        sort($expectedColumns);

        $this->assertSame($expectedColumns, $actualColumns);
    }
}
