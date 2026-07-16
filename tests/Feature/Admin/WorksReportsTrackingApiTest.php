<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksReportsController;
use App\Http\Controllers\Api\Admin\WorksShowController;
use App\Http\Controllers\Api\Admin\WorksTrackedReportsController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkReport;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksReportsTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_users_get_401_for_tracked_list_and_detail(): void
    {
        $report = WorkReport::factory()->create();

        $this->getJson($this->listEndpoint($report->work_id))->assertUnauthorized();
        $this->getJson($this->detailEndpoint($report))->assertUnauthorized();
    }

    public function test_super_admin_can_read_tracked_list_and_detail(): void
    {
        $this->actingAsRole('super-admin');
        $report = WorkReport::factory()->create();

        $this->getJson($this->listEndpoint($report->work_id))->assertOk();
        $this->getJson($this->detailEndpoint($report))->assertOk();
    }

    public function test_internal_roles_need_the_exact_list_and_detail_permissions(): void
    {
        $report = WorkReport::factory()->create();

        foreach (['admin', 'staff'] as $role) {
            foreach ([
                [],
                ['admin.works.access'],
                ['admin.works.reports.view'],
                ['admin.works.access', 'admin.works.reports.view'],
            ] as $permissions) {
                $this->actingAsRole($role, $permissions);
                $this->getJson($this->listEndpoint($report->work_id))->assertForbidden();
                $this->getJson($this->detailEndpoint($report))->assertForbidden();
            }

            $this->actingAsRole($role, $this->listPermissions());
            $this->getJson($this->listEndpoint($report->work_id))->assertOk();
            $this->getJson($this->detailEndpoint($report))->assertForbidden();

            $this->actingAsRole($role, $this->detailPermissions());
            $this->getJson($this->detailEndpoint($report))->assertOk();
            $this->getJson($this->listEndpoint($report->work_id))->assertForbidden();
        }
    }

    public function test_client_designer_and_non_internal_roles_are_always_forbidden(): void
    {
        $report = WorkReport::factory()->create();
        $allPermissions = array_values(array_unique([
            ...$this->listPermissions(),
            ...$this->detailPermissions(),
        ]));

        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $allPermissions);
            $this->getJson($this->listEndpoint($report->work_id))->assertForbidden();
            $this->getJson($this->detailEndpoint($report))->assertForbidden();
        }

        Role::findOrCreate('contractor', 'web');
        $this->actingAsRole('contractor', $allPermissions);
        $this->getJson($this->listEndpoint($report->work_id))->assertForbidden();
        $this->getJson($this->detailEndpoint($report))->assertForbidden();
    }

    public function test_list_returns_only_the_selected_works_reports_with_pagination_and_default_sort(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create(['reports_count' => 4]);
        $old = WorkReport::factory()->create([
            'work_id' => $work->id,
            'created_at' => '2026-07-01 10:00:00',
        ]);
        $newFirst = WorkReport::factory()->create([
            'work_id' => $work->id,
            'created_at' => '2026-07-10 10:00:00',
        ]);
        $newSecond = WorkReport::factory()->create([
            'work_id' => $work->id,
            'created_at' => '2026-07-10 10:00:00',
        ]);
        WorkReport::factory()->create();

        $response = $this->getJson($this->listEndpoint($work).'?per_page=15')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.per_page', 15)
            ->assertJsonPath('data.work.legacy_reports_count', 4)
            ->assertJsonPath('data.work.tracked_reports_count', 3)
            ->assertJsonPath('data.filters.sort', 'created_at')
            ->assertJsonPath('data.filters.direction', 'desc');

        $this->assertSame(
            [$newSecond->id, $newFirst->id, $old->id],
            collect($response->json('data.items'))->pluck('id')->all(),
        );
    }

    public function test_list_filters_status_reason_users_and_date_range(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create();
        $reporter = User::factory()->create();
        $reviewer = User::factory()->create();
        $expected = WorkReport::factory()->underReview()->create([
            'work_id' => $work->id,
            'reporter_id' => $reporter->id,
            'reviewed_by' => $reviewer->id,
            'reason_code' => 'copyright.claim-1',
            'created_at' => '2026-07-15 10:00:00',
        ]);
        WorkReport::factory()->dismissed()->create([
            'work_id' => $work->id,
            'reason_code' => 'other_reason',
            'created_at' => '2026-06-01 10:00:00',
        ]);

        foreach ([
            ['status' => WorkReport::STATUS_UNDER_REVIEW],
            ['reason_code' => 'copyright.claim-1'],
            ['reporter_id' => $reporter->id],
            ['reviewed_by' => $reviewer->id],
            ['from' => '2026-07-01', 'to' => '2026-07-31'],
        ] as $filters) {
            $this->getJson($this->listEndpoint($work).'?'.http_build_query($filters))
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 1)
                ->assertJsonPath('data.items.0.id', $expected->id);
        }
    }

    public function test_list_supports_declared_sorts_directions_and_page_sizes(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create();
        WorkReport::factory()->dismissed()->count(2)->create(['work_id' => $work->id]);

        foreach (['created_at', 'updated_at', 'status', 'reviewed_at', 'dismissed_at', 'archived_at'] as $sort) {
            foreach (['asc', 'desc'] as $direction) {
                $this->getJson($this->listEndpoint($work).'?'.http_build_query([
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => 25,
                ]))
                    ->assertOk()
                    ->assertJsonPath('data.filters.sort', $sort)
                    ->assertJsonPath('data.filters.direction', $direction)
                    ->assertJsonPath('data.pagination.per_page', 25);
            }
        }

        foreach ([15, 25, 50] as $perPage) {
            $this->getJson($this->listEndpoint($work).'?per_page='.$perPage)
                ->assertOk()
                ->assertJsonPath('data.pagination.per_page', $perPage);
        }
    }

    public function test_invalid_unknown_and_sensitive_list_parameters_return_422(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create();

        $this->getJson($this->listEndpoint($work).'?'.http_build_query([
            'status' => 'deleted',
            'reason_code' => 'Unsafe Value!',
            'reporter_id' => 0,
            'reviewed_by' => 0,
            'from' => '2010-01-01',
            'to' => '2026-01-02',
            'sort' => 'details',
            'direction' => 'sideways',
            'per_page' => 20,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'reason_code',
                'reporter_id',
                'reviewed_by',
                'to',
                'sort',
                'direction',
                'per_page',
            ]);

        $sensitive = [
            'q', 'details', 'resolution_notes', 'email', 'password', 'token', 'cookie',
            'metadata', 'payload', 'internal_notes', 'description', 'summary', 'work',
            'user', 'users', 'reporter', 'reviewer', 'roles', 'permissions', 'include',
            'with', 'raw',
        ];
        $parameters = array_fill_keys($sensitive, 'private');

        $this->getJson($this->listEndpoint($work).'?'.http_build_query($parameters))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($sensitive);
    }

    public function test_missing_and_nonnumeric_work_ids_return_404(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/999999/reports')->assertNotFound();
        $this->getJson('/api/admin/works/not-a-number/reports')->assertNotFound();
    }

    public function test_list_summary_items_relations_and_flags_are_safe(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create([
            'description' => 'private-work-description',
            'internal_notes' => 'private-work-notes',
            'reports_count' => 6,
        ]);
        $reporter = User::factory()->create(['email' => 'private-reporter@example.test']);
        $reviewer = User::factory()->create(['email' => 'private-reviewer@example.test']);
        WorkReport::factory()->create([
            'work_id' => $work->id,
            'reporter_id' => $reporter->id,
            'details' => 'private-details',
            'resolution_notes' => 'private-resolution',
        ]);
        WorkReport::factory()->underReview()->create([
            'work_id' => $work->id,
            'reporter_id' => null,
            'reviewed_by' => $reviewer->id,
        ]);
        WorkReport::factory()->dismissed()->create(['work_id' => $work->id]);
        WorkReport::factory()->archived()->create(['work_id' => $work->id]);

        $response = $this->getJson($this->listEndpoint($work).'?per_page=15')
            ->assertOk()
            ->assertJsonPath('data.summary', [
                'total' => 4,
                'pending' => 1,
                'under_review' => 1,
                'dismissed' => 1,
                'archived' => 1,
                'open' => 2,
            ])
            ->assertJsonPath('data.items.0.report_flags.is_archived', true);

        $items = collect($response->json('data.items'));
        $pending = $items->firstWhere('status', WorkReport::STATUS_PENDING);
        $underReview = $items->firstWhere('status', WorkReport::STATUS_UNDER_REVIEW);
        $this->assertSame(['id', 'name'], array_keys($pending['reporter']));
        $this->assertNull($underReview['reporter']);
        $this->assertSame(['id', 'name'], array_keys($underReview['reviewer']));
        $this->assertTrue($pending['report_flags']['needs_attention']);
        $this->assertTrue($underReview['report_flags']['is_open']);

        foreach (['details', 'resolution_notes', 'email', 'password', 'metadata', 'payload'] as $key) {
            $this->assertNotContains($key, $this->recursiveKeys($response->json('data.items')));
        }
        foreach (['private-details', 'private-resolution', 'private-reporter@example.test', 'private-reviewer@example.test', 'private-work-description', 'private-work-notes'] as $value) {
            $this->assertStringNotContainsString($value, $response->getContent());
        }
    }

    public function test_detail_returns_private_report_fields_only_with_detail_permission_and_safe_context(): void
    {
        $reporter = User::factory()->create(['name' => 'Safe Reporter', 'email' => 'hidden-reporter@example.test']);
        $reviewer = User::factory()->create(['name' => 'Safe Reviewer', 'email' => 'hidden-reviewer@example.test']);
        $work = Work::factory()->featured()->pinned()->create([
            'reports_count' => 8,
            'description' => 'hidden-work-description',
            'summary' => 'hidden-work-summary',
            'internal_notes' => 'hidden-work-notes',
        ]);
        $report = WorkReport::factory()->dismissed()->create([
            'work_id' => $work->id,
            'reporter_id' => $reporter->id,
            'reviewed_by' => $reviewer->id,
            'details' => 'Allowed report details.',
            'resolution_notes' => 'Allowed resolution notes.',
        ]);
        WorkReport::factory()->create(['work_id' => $work->id]);
        $this->actingAsRole('admin', $this->detailPermissions());

        $response = $this->getJson($this->detailEndpoint($report))
            ->assertOk()
            ->assertJsonPath('data.report.id', $report->id)
            ->assertJsonPath('data.report.details', 'Allowed report details.')
            ->assertJsonPath('data.report.resolution_notes', 'Allowed resolution notes.')
            ->assertJsonPath('data.report.reporter', ['id' => $reporter->id, 'name' => 'Safe Reporter'])
            ->assertJsonPath('data.report.reviewer', ['id' => $reviewer->id, 'name' => 'Safe Reviewer'])
            ->assertJsonPath('data.work.legacy_reports_count', 8)
            ->assertJsonPath('data.work.tracked_reports_count', 2)
            ->assertJsonPath('data.field_access', [
                'can_view_report_details' => true,
                'can_view_resolution_notes' => true,
            ]);

        foreach (['hidden-reporter@example.test', 'hidden-reviewer@example.test', 'hidden-work-description', 'hidden-work-summary', 'hidden-work-notes'] as $value) {
            $this->assertStringNotContainsString($value, $response->getContent());
        }
        foreach (['email', 'password', 'token', 'cookie', 'metadata', 'payload', 'description', 'summary', 'internal_notes'] as $key) {
            $this->assertNotContains($key, $this->recursiveKeys($response->json()));
        }
    }

    public function test_detail_handles_null_users_missing_ids_and_rejects_every_query_parameter(): void
    {
        $this->actingAsRole('super-admin');
        $report = WorkReport::factory()->create(['reporter_id' => null, 'reviewed_by' => null]);

        $this->getJson($this->detailEndpoint($report))
            ->assertOk()
            ->assertJsonPath('data.report.reporter', null)
            ->assertJsonPath('data.report.reviewer', null);
        $this->getJson($this->detailEndpoint($report).'?include=work')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('include');
        $this->getJson('/api/admin/works/reports/999999')->assertNotFound();
        $this->getJson('/api/admin/works/reports/not-a-number')->assertNotFound();
    }

    public function test_routes_are_get_only_numeric_and_resolve_without_shadowing_existing_routes(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create();
        $report = WorkReport::factory()->create(['work_id' => $work->id]);

        $reportsRoute = Route::getRoutes()->match(Request::create('/api/admin/works/reports', 'GET'));
        $listRoute = Route::getRoutes()->match(Request::create($this->listEndpoint($work), 'GET'));
        $detailRoute = Route::getRoutes()->match(Request::create($this->detailEndpoint($report), 'GET'));
        $showRoute = Route::getRoutes()->match(Request::create('/api/admin/works/'.$work->id, 'GET'));

        $this->assertSame(WorksReportsController::class.'@index', $reportsRoute->getActionName());
        $this->assertSame(WorksTrackedReportsController::class.'@index', $listRoute->getActionName());
        $this->assertSame(WorksTrackedReportsController::class.'@show', $detailRoute->getActionName());
        $this->assertSame(WorksShowController::class.'@show', $showRoute->getActionName());
        $this->assertSame('[0-9]+', $listRoute->wheres['work']);
        $this->assertSame('[0-9]+', $detailRoute->wheres['report']);

        foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
            $this->{$method}($this->listEndpoint($work))->assertMethodNotAllowed();
            $this->{$method}($this->detailEndpoint($report))->assertMethodNotAllowed();
        }
    }

    public function test_reading_does_not_modify_reports_works_legacy_count_or_audit_events(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create(['reports_count' => 12]);
        $report = WorkReport::factory()->dismissed()->create(['work_id' => $work->id]);
        $workSnapshot = collect($work->fresh()->getRawOriginal())
            ->sortKeys()
            ->all();
        $reportSnapshot = collect($report->fresh()->getRawOriginal())
            ->sortKeys()
            ->all();
        $auditCount = AuditEvent::query()->count();

        $this->getJson($this->listEndpoint($work))->assertOk();
        $this->getJson($this->detailEndpoint($report))->assertOk();

        $workAfter = collect($work->fresh()->getRawOriginal())
            ->sortKeys()
            ->all();
        $reportAfter = collect($report->fresh()->getRawOriginal())
            ->sortKeys()
            ->all();

        $this->assertSame($workSnapshot, $workAfter);
        $this->assertSame($reportSnapshot, $reportAfter);
        $this->assertSame(12, $work->fresh()->reports_count);
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    private function listEndpoint(Work|int $work): string
    {
        $workId = $work instanceof Work ? $work->id : $work;

        return "/api/admin/works/{$workId}/reports";
    }

    private function detailEndpoint(WorkReport $report): string
    {
        return "/api/admin/works/reports/{$report->id}";
    }

    /** @return list<string> */
    private function listPermissions(): array
    {
        return ['admin.works.access', 'admin.works.reports.view', 'admin.works.reports.list'];
    }

    /** @return list<string> */
    private function detailPermissions(): array
    {
        return ['admin.works.access', 'admin.works.reports.view', 'admin.works.reports.detail.view'];
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

    /** @return list<string> */
    private function recursiveKeys(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $keys = [];

        foreach ($value as $key => $nestedValue) {
            if (is_string($key)) {
                $keys[] = $key;
            }

            $keys = [...$keys, ...$this->recursiveKeys($nestedValue)];
        }

        return array_values(array_unique($keys));
    }
}
