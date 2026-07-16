<?php

namespace Tests\Feature\Admin;

use App\Models\Work;
use App\Models\WorkCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorksTaxonomyCategoryIdReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_does_not_create_taxonomy_records(): void
    {
        $this->assertDatabaseCount('work_categories', 0);
        $this->assertDatabaseCount('work_tags', 0);
        $this->assertDatabaseCount('work_tag_assignments', 0);

        $this->reservationMigration()->up();

        $this->assertDatabaseCount('work_categories', 0);
        $this->assertDatabaseCount('work_tags', 0);
        $this->assertDatabaseCount('work_tag_assignments', 0);
    }

    public function test_migration_does_not_modify_works_or_their_category_ids(): void
    {
        $legacyCategoryId = $this->unusedFutureId();
        $work = Work::factory()->create(['category_id' => $legacyCategoryId]);
        $originalUpdatedAt = $work->updated_at;

        $this->reservationMigration()->up();

        $work->refresh();

        $this->assertSame($legacyCategoryId, $work->category_id);
        $this->assertTrue($originalUpdatedAt->equalTo($work->updated_at));
        $this->assertDatabaseCount('works', 1);
        $this->assertDatabaseCount('work_categories', 0);
    }

    public function test_new_category_id_is_above_the_highest_legacy_category_id(): void
    {
        $legacyMax = $this->unusedFutureId();
        Work::factory()->create(['category_id' => $legacyMax]);

        $this->reservationMigration()->up();

        $category = WorkCategory::factory()->create();

        $this->assertGreaterThan($legacyMax, $category->id);
    }

    public function test_reservation_uses_the_highest_of_multiple_legacy_values(): void
    {
        $legacyMax = $this->unusedFutureId(300_000);

        Work::factory()->create(['category_id' => $legacyMax - 200_000]);
        Work::factory()->create(['category_id' => $legacyMax]);
        Work::factory()->create(['category_id' => $legacyMax - 100_000]);

        $this->reservationMigration()->up();

        $category = WorkCategory::factory()->create();

        $this->assertGreaterThan($legacyMax, $category->id);
    }

    public function test_catalog_maximum_can_advance_but_never_lower_the_allocator(): void
    {
        $probe = WorkCategory::factory()->create();
        $catalogMax = $probe->id + 100_000;

        DB::table('work_categories')->insert([
            'id' => $catalogMax,
            'name_ar' => 'تصنيف متقدم',
            'name_en' => 'Advanced category',
            'slug' => 'advanced-category-'.$catalogMax,
            'disabled_at' => null,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->reservationMigration()->up();
        $first = WorkCategory::factory()->create();
        $this->reservationMigration()->up();
        $second = WorkCategory::factory()->create();

        $this->assertGreaterThan($catalogMax, $first->id);
        $this->assertGreaterThan($first->id, $second->id);
    }

    public function test_running_up_twice_is_safe_and_does_not_create_or_reuse_records(): void
    {
        $legacyMax = $this->unusedFutureId();
        Work::factory()->create(['category_id' => $legacyMax]);
        $migration = $this->reservationMigration();

        $migration->up();
        $migration->up();

        $this->assertDatabaseCount('work_categories', 0);
        $this->assertDatabaseCount('work_tags', 0);
        $this->assertDatabaseCount('work_tag_assignments', 0);

        $first = WorkCategory::factory()->create();
        $this->reservationMigration()->up();
        $second = WorkCategory::factory()->create();

        $this->assertGreaterThan($legacyMax, $first->id);
        $this->assertGreaterThan($first->id, $second->id);
    }

    public function test_down_preserves_data_and_does_not_move_the_allocator_backwards(): void
    {
        $legacyMax = $this->unusedFutureId();
        $work = Work::factory()->create(['category_id' => $legacyMax]);
        $migration = $this->reservationMigration();

        $migration->up();
        $first = WorkCategory::factory()->create();
        $migration->down();
        $second = WorkCategory::factory()->create();

        $this->assertDatabaseHas('works', [
            'id' => $work->id,
            'category_id' => $legacyMax,
        ]);
        $this->assertDatabaseHas('work_categories', ['id' => $first->id]);
        $this->assertDatabaseHas('work_categories', ['id' => $second->id]);
        $this->assertGreaterThan($first->id, $second->id);
    }

    public function test_legacy_category_column_remains_unconstrained_and_no_replacement_column_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('works', 'category_id'));
        $this->assertFalse(Schema::hasColumn('works', 'work_category_id'));

        $foreignColumns = collect(Schema::getForeignKeys('works'))
            ->flatMap(static fn (array $foreignKey): array => $foreignKey['columns'] ?? [])
            ->all();

        $this->assertNotContains('category_id', $foreignColumns);
    }

    public function test_reservation_adds_no_routes_or_apis(): void
    {
        $routesBefore = $this->routeSignatures();

        $this->reservationMigration()->up();

        $this->assertSame($routesBefore, $this->routeSignatures());
    }

    public function test_current_database_driver_is_supported_by_the_reservation(): void
    {
        $this->assertContains(DB::connection()->getDriverName(), ['pgsql', 'sqlite']);
    }

    private function reservationMigration(): Migration
    {
        return require database_path(
            'migrations/2026_07_17_000003_reserve_work_category_ids_above_legacy_values.php'
        );
    }

    private function unusedFutureId(int $offset = 100_000): int
    {
        $probe = WorkCategory::factory()->create();
        $futureId = $probe->id + $offset;
        $probe->delete();

        return $futureId;
    }

    /**
     * @return array<int, string>
     */
    private function routeSignatures(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->map(static fn ($route): string => implode('|', [
                implode(',', $route->methods()),
                $route->uri(),
                $route->getActionName() ?? '',
            ]))
            ->sort()
            ->values()
            ->all();
    }
}
