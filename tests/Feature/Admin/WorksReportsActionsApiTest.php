<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksReportActionController;
use App\Http\Controllers\Api\Admin\WorksReportsController;
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

class WorksReportsActionsApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private const ACTION_PERMISSIONS = [
        'review' => 'admin.works.reports.review',
        'dismiss' => 'admin.works.reports.dismiss',
        'archive' => 'admin.works.reports.archive',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_users_get_401_for_every_action(): void
    {
        foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
            $report = $this->reportForAction($action);

            $this->patchJson($this->actionEndpoint($report, $action), $this->bodyForAction($action))
                ->assertUnauthorized();
        }
    }

    public function test_super_admin_can_execute_all_three_actions(): void
    {
        $this->actingAsRole('super-admin');

        foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
            $report = $this->reportForAction($action);

            $this->patchJson($this->actionEndpoint($report, $action), $this->bodyForAction($action))
                ->assertOk()
                ->assertJsonPath('data.action', $action)
                ->assertJsonPath('data.changed', true);
        }
    }

    public function test_admin_and_staff_require_access_view_and_the_exact_action_permission(): void
    {
        foreach (['admin', 'staff'] as $role) {
            foreach (self::ACTION_PERMISSIONS as $action => $permission) {
                foreach ([
                    [],
                    ['admin.works.access'],
                    ['admin.works.access', 'admin.works.reports.view'],
                    ['admin.works.access', $permission],
                    ['admin.works.reports.view', $permission],
                ] as $permissions) {
                    $this->actingAsRole($role, $permissions);
                    $report = $this->reportForAction($action);

                    $this->patchJson($this->actionEndpoint($report, $action), $this->bodyForAction($action))
                        ->assertForbidden();
                }

                $wrongPermission = collect(self::ACTION_PERMISSIONS)
                    ->first(fn (string $candidate): bool => $candidate !== $permission);
                $this->actingAsRole($role, [
                    'admin.works.access',
                    'admin.works.reports.view',
                    $wrongPermission,
                ]);
                $report = $this->reportForAction($action);
                $this->patchJson($this->actionEndpoint($report, $action), $this->bodyForAction($action))
                    ->assertForbidden();

                $this->actingAsRole($role, $this->permissionsFor($action));
                $report = $this->reportForAction($action);
                $this->patchJson($this->actionEndpoint($report, $action), $this->bodyForAction($action))
                    ->assertOk();
            }
        }
    }

    public function test_list_and_detail_permissions_are_not_required_for_actions(): void
    {
        foreach (self::ACTION_PERMISSIONS as $action => $permission) {
            $this->actingAsRole('admin', [
                'admin.works.access',
                'admin.works.reports.view',
                $permission,
            ]);
            $report = $this->reportForAction($action);

            $this->patchJson($this->actionEndpoint($report, $action), $this->bodyForAction($action))
                ->assertOk();
        }
    }

    public function test_client_designer_and_non_internal_roles_are_always_forbidden(): void
    {
        $permissions = [
            'admin.works.access',
            'admin.works.reports.view',
            ...array_values(self::ACTION_PERMISSIONS),
        ];

        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $permissions);

            foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
                $report = $this->reportForAction($action);
                $this->patchJson($this->actionEndpoint($report, $action), $this->bodyForAction($action))
                    ->assertForbidden();
            }
        }

        Role::findOrCreate('contractor', 'web');
        $this->actingAsRole('contractor', $permissions);

        foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
            $report = $this->reportForAction($action);
            $this->patchJson($this->actionEndpoint($report, $action), $this->bodyForAction($action))
                ->assertForbidden();
        }
    }

    public function test_review_moves_pending_to_under_review_and_preserves_private_fields(): void
    {
        $actor = $this->actingAsRole('admin', $this->permissionsFor('review'));
        $report = WorkReport::factory()->create([
            'reason_code' => 'copyright_claim',
            'details' => 'Private report details.',
            'resolution_notes' => 'Historical note.',
            'dismissed_at' => now()->subDay(),
            'archived_at' => now()->subHours(12),
        ]);
        $report->refresh();
        $immutable = $report->only([
            'work_id', 'reporter_id', 'reason_code', 'details', 'resolution_notes',
            'dismissed_at', 'archived_at',
        ]);

        $this->patchJson($this->actionEndpoint($report, 'review'))
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.report.status', WorkReport::STATUS_UNDER_REVIEW)
            ->assertJsonPath('data.report.reviewer.id', $actor->id)
            ->assertJsonPath('data.report.report_flags.is_under_review', true);

        $report->refresh();
        $this->assertSame($actor->id, $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
        $this->assertEquals($immutable, $report->only(array_keys($immutable)));
    }

    public function test_review_is_idempotent_when_under_review_is_complete(): void
    {
        $this->actingAsRole('super-admin');
        $report = WorkReport::factory()->underReview()->create();
        $snapshot = $this->snapshot($report);
        $auditCount = AuditEvent::query()->count();

        $this->patchJson($this->actionEndpoint($report, 'review'))
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame($snapshot, $this->snapshot($report));
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    public function test_review_safely_completes_missing_reviewer_or_reviewed_at_without_replacing_existing_reviewer(): void
    {
        $actor = $this->actingAsRole('super-admin');
        $existingReviewer = User::factory()->create();
        $withMissingReviewer = WorkReport::factory()->create([
            'status' => WorkReport::STATUS_UNDER_REVIEW,
            'reviewed_by' => null,
            'reviewed_at' => now()->subHour(),
        ]);
        $withMissingTime = WorkReport::factory()->create([
            'status' => WorkReport::STATUS_UNDER_REVIEW,
            'reviewed_by' => $existingReviewer->id,
            'reviewed_at' => null,
        ]);

        $this->patchJson($this->actionEndpoint($withMissingReviewer, 'review'))
            ->assertOk()
            ->assertJsonPath('data.changed', true);
        $this->patchJson($this->actionEndpoint($withMissingTime, 'review'))
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->assertSame($actor->id, $withMissingReviewer->refresh()->reviewed_by);
        $this->assertSame($existingReviewer->id, $withMissingTime->refresh()->reviewed_by);
        $this->assertNotNull($withMissingTime->reviewed_at);
    }

    public function test_review_rejects_dismissed_and_archived_reports_without_changes(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([WorkReport::factory()->dismissed(), WorkReport::factory()->archived()] as $factory) {
            $report = $factory->create();
            $snapshot = $this->snapshot($report);

            $this->patchJson($this->actionEndpoint($report, 'review'))
                ->assertUnprocessable();
            $this->assertSame($snapshot, $this->snapshot($report));
        }
    }

    public function test_dismiss_requires_notes_and_moves_pending_or_under_review_to_dismissed(): void
    {
        $actor = $this->actingAsRole('super-admin');

        $this->patchJson($this->actionEndpoint(WorkReport::factory()->create(), 'dismiss'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resolution_notes');

        $pending = WorkReport::factory()->create();
        $existingReviewer = User::factory()->create();
        $underReview = WorkReport::factory()->underReview()->create([
            'reviewed_by' => $existingReviewer->id,
            'reviewed_at' => now()->subHours(3),
        ]);
        $reviewedAt = $underReview->fresh()->reviewed_at->copy();

        foreach ([$pending, $underReview] as $report) {
            $this->patchJson($this->actionEndpoint($report, 'dismiss'), [
                'resolution_notes' => 'تمت مراجعة البلاغ وإغلاقه.',
            ])
                ->assertOk()
                ->assertJsonPath('data.changed', true)
                ->assertJsonPath('data.report.status', WorkReport::STATUS_DISMISSED)
                ->assertJsonPath('data.report.report_flags.is_dismissed', true);
        }

        $this->assertSame($actor->id, $pending->refresh()->reviewed_by);
        $this->assertNotNull($pending->reviewed_at);
        $this->assertNotNull($pending->dismissed_at);
        $this->assertNull($pending->archived_at);
        $this->assertSame('تمت مراجعة البلاغ وإغلاقه.', $pending->resolution_notes);
        $this->assertSame($existingReviewer->id, $underReview->refresh()->reviewed_by);
        $this->assertTrue($underReview->reviewed_at->equalTo($reviewedAt));
    }

    public function test_dismiss_is_idempotent_for_same_notes_and_updates_only_notes_when_different(): void
    {
        $this->actingAsRole('super-admin');
        $report = WorkReport::factory()->dismissed()->create([
            'resolution_notes' => 'الملاحظة الأصلية للبلاغ.',
        ]);
        $report->refresh();
        $reviewedAt = $report->reviewed_at->copy();
        $dismissedAt = $report->dismissed_at->copy();
        $reviewerId = $report->reviewed_by;

        $this->patchJson($this->actionEndpoint($report, 'dismiss'), [
            'resolution_notes' => 'الملاحظة الأصلية للبلاغ.',
        ])->assertOk()->assertJsonPath('data.changed', false);

        $this->patchJson($this->actionEndpoint($report, 'dismiss'), [
            'resolution_notes' => 'ملاحظة بديلة لمعالجة البلاغ.',
        ])->assertOk()->assertJsonPath('data.changed', true);

        $report->refresh();
        $this->assertSame('ملاحظة بديلة لمعالجة البلاغ.', $report->resolution_notes);
        $this->assertSame($reviewerId, $report->reviewed_by);
        $this->assertTrue($report->reviewed_at->equalTo($reviewedAt));
        $this->assertTrue($report->dismissed_at->equalTo($dismissedAt));
    }

    public function test_dismiss_rejects_archived_reports(): void
    {
        $this->actingAsRole('super-admin');
        $report = WorkReport::factory()->archived()->create();
        $snapshot = $this->snapshot($report);

        $this->patchJson($this->actionEndpoint($report, 'dismiss'), [
            'resolution_notes' => 'محاولة تعديل بلاغ مؤرشف.',
        ])->assertUnprocessable();

        $this->assertSame($snapshot, $this->snapshot($report));
    }

    public function test_archive_moves_dismissed_to_archived_and_preserves_dismissal_data(): void
    {
        $actor = $this->actingAsRole('super-admin');
        $report = WorkReport::factory()->dismissed()->create([
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
        $dismissedAt = $report->fresh()->dismissed_at->copy();
        $notes = $report->resolution_notes;
        $details = $report->details;
        $reasonCode = $report->reason_code;

        $this->patchJson($this->actionEndpoint($report, 'archive'))
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.report.status', WorkReport::STATUS_ARCHIVED)
            ->assertJsonPath('data.report.report_flags.is_archived', true);

        $report->refresh();
        $this->assertNotNull($report->archived_at);
        $this->assertSame($actor->id, $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
        $this->assertTrue($report->dismissed_at->equalTo($dismissedAt));
        $this->assertSame($notes, $report->resolution_notes);
        $this->assertSame($details, $report->details);
        $this->assertSame($reasonCode, $report->reason_code);
    }

    public function test_archive_is_idempotent_and_rejects_open_reports(): void
    {
        $this->actingAsRole('super-admin');
        $archived = WorkReport::factory()->archived()->create();
        $snapshot = $this->snapshot($archived);

        $this->patchJson($this->actionEndpoint($archived, 'archive'))
            ->assertOk()
            ->assertJsonPath('data.changed', false);
        $this->assertSame($snapshot, $this->snapshot($archived));

        foreach ([
            WorkReport::factory()->create(),
            WorkReport::factory()->underReview()->create(),
        ] as $openReport) {
            $this->patchJson($this->actionEndpoint($openReport, 'archive'))
                ->assertUnprocessable();
        }
    }

    public function test_query_unknown_sensitive_and_invalid_body_fields_return_422(): void
    {
        $this->actingAsRole('super-admin');
        $forbiddenFields = [
            'work_id', 'reporter_id', 'reviewed_by', 'reason_code', 'details', 'status',
            'reviewed_at', 'dismissed_at', 'archived_at', 'reports_count', 'email',
            'password', 'token', 'cookie', 'metadata', 'payload', 'internal_notes',
            'rejection_reason', 'change_request_notes', 'description', 'summary', 'delete',
            'force_delete', 'hard_delete', 'restore', 'reopen', 'bulk',
        ];

        foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
            $report = $this->reportForAction($action);
            $this->patchJson($this->actionEndpoint($report, $action).'?token=secret', $this->bodyForAction($action))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('token');

            $body = array_fill_keys($forbiddenFields, 'forbidden');
            if ($action === 'dismiss') {
                $body['resolution_notes'] = 'ملاحظة صحيحة لمعالجة البلاغ.';
            }

            $this->patchJson($this->actionEndpoint($report, $action), $body)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($forbiddenFields);
        }

        foreach (['قصير', str_repeat('a', 2001)] as $invalidNotes) {
            $report = WorkReport::factory()->create();
            $this->patchJson($this->actionEndpoint($report, 'dismiss'), [
                'resolution_notes' => $invalidNotes,
            ])->assertUnprocessable()->assertJsonValidationErrors('resolution_notes');
        }
    }

    public function test_missing_and_nonnumeric_reports_return_404(): void
    {
        $this->actingAsRole('super-admin');

        foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
            $this->patchJson("/api/admin/works/reports/999999/{$action}", $this->bodyForAction($action))
                ->assertNotFound();
            $this->patchJson("/api/admin/works/reports/not-a-number/{$action}", $this->bodyForAction($action))
                ->assertNotFound();
        }
    }

    public function test_action_response_is_safe_and_uses_expected_report_and_work_contracts(): void
    {
        $actor = $this->actingAsRole('super-admin');
        $reporter = User::factory()->create(['name' => 'Safe Reporter', 'email' => 'private-reporter@example.test']);
        $work = Work::factory()->create([
            'reports_count' => 9,
            'description' => 'private-work-description',
            'summary' => 'private-work-summary',
            'internal_notes' => 'private-work-notes',
        ]);
        $report = WorkReport::factory()->create([
            'work_id' => $work->id,
            'reporter_id' => $reporter->id,
            'details' => 'private-report-details',
            'resolution_notes' => 'private-resolution-notes',
        ]);
        WorkReport::factory()->create(['work_id' => $work->id]);

        $response = $this->patchJson($this->actionEndpoint($report, 'review'))
            ->assertOk()
            ->assertJsonPath('data.action', 'review')
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.report.reporter', ['id' => $reporter->id, 'name' => 'Safe Reporter'])
            ->assertJsonPath('data.report.reviewer.id', $actor->id)
            ->assertJsonPath('data.work.legacy_reports_count', 9)
            ->assertJsonPath('data.work.tracked_reports_count', 2)
            ->assertJsonPath('data.report.report_flags', [
                'is_open' => true,
                'is_pending' => false,
                'is_under_review' => true,
                'is_dismissed' => false,
                'is_archived' => false,
                'has_reviewer' => true,
                'needs_attention' => true,
            ]);

        foreach (['details', 'resolution_notes', 'email', 'password', 'token', 'cookie', 'metadata', 'payload', 'description', 'summary', 'internal_notes', 'permissions', 'roles'] as $key) {
            $this->assertNotContains($key, $this->recursiveKeys($response->json()));
        }
        foreach (['private-reporter@example.test', 'private-work-description', 'private-work-summary', 'private-work-notes', 'private-report-details', 'private-resolution-notes'] as $value) {
            $this->assertStringNotContainsString($value, $response->getContent());
        }
    }

    public function test_null_reporter_is_supported_in_action_response(): void
    {
        $this->actingAsRole('super-admin');
        $report = WorkReport::factory()->create(['reporter_id' => null]);

        $this->patchJson($this->actionEndpoint($report, 'review'))
            ->assertOk()
            ->assertJsonPath('data.report.reporter', null);
    }

    public function test_actions_do_not_modify_work_legacy_count_immutable_report_fields_or_other_reports(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create(['reports_count' => 12]);
        $reporter = User::factory()->create();
        $report = WorkReport::factory()->create([
            'work_id' => $work->id,
            'reporter_id' => $reporter->id,
            'reason_code' => 'immutable_reason',
            'details' => 'Immutable private details.',
        ]);
        $otherReport = WorkReport::factory()->create(['work_id' => $work->id]);
        $workSnapshot = $this->snapshot($work);
        $otherSnapshot = $this->snapshot($otherReport);

        $this->patchJson($this->actionEndpoint($report, 'review'))->assertOk();

        $report->refresh();
        $this->assertSame($workSnapshot, $this->snapshot($work));
        $this->assertSame($otherSnapshot, $this->snapshot($otherReport));
        $this->assertSame(12, $work->fresh()->reports_count);
        $this->assertSame($work->id, $report->work_id);
        $this->assertSame($reporter->id, $report->reporter_id);
        $this->assertSame('immutable_reason', $report->reason_code);
        $this->assertSame('Immutable private details.', $report->details);
        $this->assertDatabaseHas('work_reports', ['id' => $report->id]);
        $this->assertDatabaseCount('work_reports', 2);
    }

    public function test_audit_events_use_exact_types_allowlisted_metadata_and_only_record_changes(): void
    {
        $this->actingAsRole('super-admin');
        $reports = [
            'review' => WorkReport::factory()->create(['details' => 'secret-details', 'reason_code' => 'secret_reason']),
            'dismiss' => WorkReport::factory()->create(['details' => 'secret-details', 'reason_code' => 'secret_reason']),
            'archive' => WorkReport::factory()->dismissed()->create(['details' => 'secret-details', 'reason_code' => 'secret_reason']),
        ];
        $eventTypes = [
            'review' => 'works.reports.review_started',
            'dismiss' => 'works.reports.dismissed',
            'archive' => 'works.reports.archived',
        ];
        $allowedMetadata = [
            'report_id', 'work_id', 'action', 'old_status', 'new_status',
            'old_reviewer_id', 'new_reviewer_id', 'old_has_resolution_notes',
            'new_has_resolution_notes', 'old_reviewed_at_present', 'new_reviewed_at_present',
            'old_dismissed_at_present', 'new_dismissed_at_present',
            'old_archived_at_present', 'new_archived_at_present',
        ];

        foreach ($reports as $action => $report) {
            $body = $action === 'dismiss'
                ? ['resolution_notes' => 'نص خاص لا يجب تسجيله في التدقيق.']
                : [];
            $this->patchJson($this->actionEndpoint($report, $action), $body)
                ->assertOk()
                ->assertJsonPath('data.changed', true);

            $event = AuditEvent::query()->where('target_id', $report->id)->sole();
            $this->assertSame($eventTypes[$action], $event->event_type);
            $this->assertSame('works', $event->category);
            $this->assertSame('work_report', $event->target_type);
            $this->assertSame($action, $event->action);
            $this->assertEqualsCanonicalizing($allowedMetadata, array_keys($event->metadata));

            $metadata = json_encode($event->metadata, JSON_THROW_ON_ERROR);
            foreach (['secret-details', 'secret_reason', 'نص خاص لا يجب تسجيله في التدقيق.'] as $secret) {
                $this->assertStringNotContainsString($secret, $metadata);
            }
        }

        $eventCount = AuditEvent::query()->count();
        $this->patchJson($this->actionEndpoint($reports['archive']->refresh(), 'archive'))
            ->assertOk()
            ->assertJsonPath('data.changed', false);
        $this->assertSame($eventCount, AuditEvent::query()->count());
    }

    public function test_action_routes_are_patch_only_numeric_and_preserve_read_routes(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create(['reports_count' => 1]);
        $reports = [
            'review' => WorkReport::factory()->create(['work_id' => $work->id]),
            'dismiss' => WorkReport::factory()->create(['work_id' => $work->id]),
            'archive' => WorkReport::factory()->dismissed()->create(['work_id' => $work->id]),
        ];

        foreach ($reports as $action => $report) {
            $route = Route::getRoutes()->match(Request::create($this->actionEndpoint($report, $action), 'PATCH'));
            $this->assertSame(WorksReportActionController::class.'@'.$action, $route->getActionName());
            $this->assertSame('[0-9]+', $route->wheres['report']);

            foreach (['postJson', 'putJson', 'deleteJson'] as $method) {
                $this->{$method}($this->actionEndpoint($report, $action))->assertMethodNotAllowed();
            }
        }

        $reportsRoute = Route::getRoutes()->match(Request::create('/api/admin/works/reports', 'GET'));
        $detailRoute = Route::getRoutes()->match(Request::create('/api/admin/works/reports/'.$reports['review']->id, 'GET'));
        $listRoute = Route::getRoutes()->match(Request::create('/api/admin/works/'.$work->id.'/reports', 'GET'));
        $this->assertSame(WorksReportsController::class.'@index', $reportsRoute->getActionName());
        $this->assertSame(WorksTrackedReportsController::class.'@show', $detailRoute->getActionName());
        $this->assertSame(WorksTrackedReportsController::class.'@index', $listRoute->getActionName());

        $this->getJson('/api/admin/works/reports')->assertOk();
        $this->getJson('/api/admin/works/reports/'.$reports['review']->id)->assertOk();
        $this->getJson('/api/admin/works/'.$work->id.'/reports')->assertOk();
    }

    private function reportForAction(string $action): WorkReport
    {
        return match ($action) {
            'review', 'dismiss' => WorkReport::factory()->create(),
            'archive' => WorkReport::factory()->dismissed()->create(),
        };
    }

    /** @return array<string, string> */
    private function bodyForAction(string $action): array
    {
        return $action === 'dismiss'
            ? ['resolution_notes' => 'تمت معالجة البلاغ وإغلاقه.']
            : [];
    }

    private function actionEndpoint(WorkReport $report, string $action): string
    {
        return "/api/admin/works/reports/{$report->id}/{$action}";
    }

    /** @return list<string> */
    private function permissionsFor(string $action): array
    {
        return [
            'admin.works.access',
            'admin.works.reports.view',
            self::ACTION_PERMISSIONS[$action],
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

    /** @return array<string, mixed> */
    private function snapshot(Work|WorkReport $model): array
    {
        return collect($model->fresh()->getRawOriginal())->sortKeys()->all();
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
