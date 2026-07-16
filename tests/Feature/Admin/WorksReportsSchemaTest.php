<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Work;
use App\Models\WorkReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorksReportsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_reports_table_has_the_required_columns_indexes_and_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasTable('work_reports'));
        $this->assertTrue(Schema::hasColumns('work_reports', [
            'id',
            'work_id',
            'reporter_id',
            'reason_code',
            'details',
            'status',
            'reviewed_by',
            'reviewed_at',
            'dismissed_at',
            'archived_at',
            'resolution_notes',
            'created_at',
            'updated_at',
        ]));

        foreach ([
            ['work_id'],
            ['work_id', 'status'],
            ['status', 'created_at'],
            ['reporter_id', 'created_at'],
            ['reviewed_by'],
        ] as $indexColumns) {
            $this->assertTrue(Schema::hasIndex('work_reports', $indexColumns));
        }

        $foreignKeys = collect(Schema::getForeignKeys('work_reports'))
            ->keyBy(fn (array $foreignKey): string => $foreignKey['columns'][0]);

        $this->assertSame('works', $foreignKeys['work_id']['foreign_table']);
        $this->assertContains(
            strtolower((string) $foreignKeys['work_id']['on_delete']),
            ['restrict', 'no action'],
        );
        $this->assertSame('users', $foreignKeys['reporter_id']['foreign_table']);
        $this->assertSame('set null', strtolower((string) $foreignKeys['reporter_id']['on_delete']));
        $this->assertSame('users', $foreignKeys['reviewed_by']['foreign_table']);
        $this->assertSame('set null', strtolower((string) $foreignKeys['reviewed_by']['on_delete']));
    }

    public function test_status_defaults_to_pending_and_status_constants_are_exact(): void
    {
        $work = Work::factory()->create();
        $report = WorkReport::query()->create([
            'work_id' => $work->id,
            'reason_code' => 'inappropriate_content',
        ]);

        $this->assertSame(WorkReport::STATUS_PENDING, $report->refresh()->status);
        $this->assertSame([
            WorkReport::STATUS_PENDING,
            WorkReport::STATUS_UNDER_REVIEW,
            WorkReport::STATUS_DISMISSED,
            WorkReport::STATUS_ARCHIVED,
        ], WorkReport::STATUSES);
    }

    public function test_work_report_relations_return_the_correct_models(): void
    {
        $work = Work::factory()->create();
        $reporter = User::factory()->create();
        $reviewer = User::factory()->create();
        $report = WorkReport::factory()->create([
            'work_id' => $work->id,
            'reporter_id' => $reporter->id,
            'reviewed_by' => $reviewer->id,
        ]);

        $this->assertTrue($report->work->is($work));
        $this->assertTrue($report->reporter->is($reporter));
        $this->assertTrue($report->reviewer->is($reviewer));
    }

    public function test_work_reports_relation_returns_only_reports_for_that_work(): void
    {
        $work = Work::factory()->create();
        $linkedReports = WorkReport::factory()->count(2)->create(['work_id' => $work->id]);
        WorkReport::factory()->create();

        $this->assertEqualsCanonicalizing(
            $linkedReports->modelKeys(),
            $work->reports()->pluck('id')->all(),
        );
    }

    public function test_reporter_and_reviewer_can_be_null(): void
    {
        $report = WorkReport::factory()->create([
            'reporter_id' => null,
            'reviewed_by' => null,
        ]);

        $this->assertNull($report->reporter_id);
        $this->assertNull($report->reviewed_by);
        $this->assertNull($report->reporter);
        $this->assertNull($report->reviewer);
    }

    public function test_review_timestamps_are_cast_to_datetime(): void
    {
        $report = WorkReport::factory()->create([
            'reviewed_at' => now()->subHours(3),
            'dismissed_at' => now()->subHours(2),
            'archived_at' => now()->subHour(),
        ]);

        $this->assertInstanceOf(Carbon::class, $report->reviewed_at);
        $this->assertInstanceOf(Carbon::class, $report->dismissed_at);
        $this->assertInstanceOf(Carbon::class, $report->archived_at);
    }

    public function test_factory_creates_valid_reports_in_all_four_states(): void
    {
        $pending = WorkReport::factory()->create();
        $underReview = WorkReport::factory()->underReview()->create();
        $dismissed = WorkReport::factory()->dismissed()->create();
        $archived = WorkReport::factory()->archived()->create();

        $this->assertSame(WorkReport::STATUS_PENDING, $pending->status);

        $this->assertSame(WorkReport::STATUS_UNDER_REVIEW, $underReview->status);
        $this->assertNotNull($underReview->reviewer);
        $this->assertNotNull($underReview->reviewed_at);

        $this->assertSame(WorkReport::STATUS_DISMISSED, $dismissed->status);
        $this->assertNotNull($dismissed->reviewed_at);
        $this->assertNotNull($dismissed->dismissed_at);
        $this->assertNotNull($dismissed->resolution_notes);
        $this->assertTrue($dismissed->reviewed_at->lessThanOrEqualTo($dismissed->dismissed_at));

        $this->assertSame(WorkReport::STATUS_ARCHIVED, $archived->status);
        $this->assertNotNull($archived->reviewed_at);
        $this->assertNotNull($archived->archived_at);
        $this->assertNull($archived->dismissed_at);
        $this->assertTrue($archived->reviewed_at->lessThanOrEqualTo($archived->archived_at));
    }

    public function test_deleting_reporter_sets_reporter_id_to_null_without_deleting_report(): void
    {
        $reporter = User::factory()->create();
        $report = WorkReport::factory()->create(['reporter_id' => $reporter->id]);

        $reporter->delete();

        $this->assertNull($report->refresh()->reporter_id);
        $this->assertDatabaseHas('work_reports', ['id' => $report->id]);
    }

    public function test_deleting_reviewer_sets_reviewed_by_to_null_without_deleting_report(): void
    {
        $reviewer = User::factory()->create();
        $report = WorkReport::factory()->create(['reviewed_by' => $reviewer->id]);

        $reviewer->delete();

        $this->assertNull($report->refresh()->reviewed_by);
        $this->assertDatabaseHas('work_reports', ['id' => $report->id]);
    }

    public function test_creating_and_deleting_reports_does_not_change_legacy_reports_count(): void
    {
        $work = Work::factory()->create(['reports_count' => 9]);

        $report = WorkReport::factory()->create(['work_id' => $work->id]);
        $this->assertSame(9, $work->refresh()->reports_count);

        $report->delete();
        $this->assertSame(9, $work->refresh()->reports_count);
    }

    public function test_legacy_reports_count_does_not_create_synthetic_reports(): void
    {
        $work = Work::factory()->create(['reports_count' => 7]);

        $this->assertSame(7, $work->reports_count);
        $this->assertSame(0, $work->reports()->count());
        $this->assertDatabaseCount('work_reports', 0);
    }

    public function test_no_work_report_routes_or_api_actions_are_registered(): void
    {
        $workReportRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route): bool {
                return str_contains($route->uri(), 'work-reports')
                    || str_contains($route->getActionName(), WorkReport::class);
            });

        $this->assertCount(0, $workReportRoutes);
    }
}
