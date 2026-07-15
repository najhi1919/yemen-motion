<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksReviewActionController;
use App\Http\Controllers\Api\Admin\WorksReviewQueueController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use Carbon\Carbon;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksReviewActionsApiTest extends TestCase
{
    use RefreshDatabase;

    private const ACTION_PERMISSIONS = [
        'start' => 'admin.works.review.start',
        'assign-reviewer' => 'admin.works.review.assign_reviewer',
        'approve' => 'admin.works.review.approve',
        'request-changes' => 'admin.works.review.request_changes',
        'reject' => 'admin.works.review.reject',
        'publish' => 'admin.works.review.publish_after_approval',
        'reopen' => 'admin.works.review.reopen',
    ];

    private const AUDIT_EVENT_TYPES = [
        'start' => 'works.review.started',
        'assign-reviewer' => 'works.review.reviewer_assigned',
        'approve' => 'works.review.approved',
        'request-changes' => 'works.review.changes_requested',
        'reject' => 'works.review.rejected',
        'publish' => 'works.review.published',
        'reopen' => 'works.review.reopened',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unauthenticated_requests_get_401_for_every_action(): void
    {
        foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
            [$work, $payload] = $this->actionCase($action);

            $this->patchJson($this->actionUrl($work, $action), $payload)->assertUnauthorized();
        }
    }

    public function test_super_admin_can_execute_all_seven_actions(): void
    {
        $this->actingAsRole('super-admin');

        foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
            [$work, $payload] = $this->actionCase($action);

            $this->patchJson($this->actionUrl($work, $action), $payload)
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.changed', true);
        }

        $this->assertCount(7, AuditEvent::query()->get());
    }

    public function test_admin_and_staff_without_permissions_or_with_access_only_get_403(): void
    {
        foreach (['admin', 'staff'] as $role) {
            foreach ([[], ['admin.works.access']] as $permissions) {
                $this->actingAsRole($role, $permissions);
                [$work, $payload] = $this->actionCase('start');

                $this->patchJson($this->actionUrl($work, 'start'), $payload)->assertForbidden();
            }
        }
    }

    public function test_admin_and_staff_need_access_and_the_exact_action_permission(): void
    {
        foreach (['admin', 'staff'] as $role) {
            foreach (self::ACTION_PERMISSIONS as $action => $permission) {
                $this->actingAsRole($role, ['admin.works.access', $permission]);
                [$work, $payload] = $this->actionCase($action);
                $this->patchJson($this->actionUrl($work, $action), $payload)->assertOk();

                $wrongPermission = $permission === 'admin.works.review.start'
                    ? 'admin.works.review.reject'
                    : 'admin.works.review.start';
                $this->actingAsRole($role, ['admin.works.access', $wrongPermission]);
                [$work, $payload] = $this->actionCase($action);
                $this->patchJson($this->actionUrl($work, $action), $payload)->assertForbidden();
            }
        }
    }

    public function test_external_and_non_internal_roles_are_always_forbidden(): void
    {
        $permissions = ['admin.works.access', ...array_values(self::ACTION_PERMISSIONS)];

        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $permissions);
            [$work, $payload] = $this->actionCase('start');
            $this->patchJson($this->actionUrl($work, 'start'), $payload)->assertForbidden();
        }

        Role::create(['name' => 'contractor', 'guard_name' => 'web']);
        $this->actingAsRole('contractor', $permissions);
        [$work, $payload] = $this->actionCase('start');
        $this->patchJson($this->actionUrl($work, 'start'), $payload)->assertForbidden();
    }

    public function test_start_moves_submitted_to_in_review_and_assigns_actor_when_unassigned(): void
    {
        $actor = $this->actingAsRole('super-admin');
        $submittedAt = now()->subDay()->startOfSecond();
        $work = Work::factory()->submitted()->create([
            'reviewer_id' => null,
            'submitted_at' => $submittedAt,
            'reviewed_at' => null,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
        ]);

        $this->patchJson($this->actionUrl($work, 'start'))
            ->assertOk()
            ->assertJsonPath('data.action', 'start')
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.work.status', Work::STATUS_IN_REVIEW)
            ->assertJsonPath('data.work.visibility_status', Work::VISIBILITY_HIDDEN)
            ->assertJsonPath('data.work.reviewer.id', $actor->id)
            ->assertJsonPath('data.work.reviewed_at', null);

        $fresh = $work->fresh();
        $this->assertSame($actor->id, $fresh->reviewer_id);
        $this->assertTrue($fresh->submitted_at->equalTo($submittedAt));
        $this->assertNull($fresh->reviewed_at);
    }

    public function test_start_preserves_existing_reviewer_and_is_idempotent_in_review(): void
    {
        $this->actingAsRole('super-admin');
        $reviewer = User::factory()->create();
        $work = Work::factory()->submitted()->create(['reviewer_id' => $reviewer->id]);

        $this->patchJson($this->actionUrl($work, 'start'))
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.work.reviewer.id', $reviewer->id);

        $this->patchJson($this->actionUrl($work, 'start'))
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame($reviewer->id, $work->fresh()->reviewer_id);
        $this->assertSame(1, AuditEvent::query()->count());
    }

    public function test_start_assigns_actor_to_unassigned_in_review_and_rejects_other_statuses(): void
    {
        $actor = $this->actingAsRole('super-admin');
        $work = Work::factory()->inReview()->create(['reviewer_id' => null]);

        $this->patchJson($this->actionUrl($work, 'start'))
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.work.reviewer.id', $actor->id);

        foreach ($this->statusesExcept([Work::STATUS_SUBMITTED, Work::STATUS_IN_REVIEW]) as $status) {
            $invalid = Work::factory()->create(['status' => $status]);
            $this->patchJson($this->actionUrl($invalid, 'start'))->assertUnprocessable();
        }
    }

    public function test_assign_reviewer_accepts_internal_users_on_supported_states_and_is_idempotent(): void
    {
        $this->actingAsRole('super-admin');

        foreach (['super-admin', 'admin', 'staff'] as $reviewerRole) {
            $reviewer = User::factory()->create();
            $reviewer->assignRole($reviewerRole);

            foreach ([Work::STATUS_SUBMITTED, Work::STATUS_IN_REVIEW, Work::STATUS_CHANGES_REQUESTED] as $status) {
                $work = Work::factory()->create([
                    'status' => $status,
                    'visibility_status' => Work::VISIBILITY_HIDDEN,
                    'reviewer_id' => null,
                    'reviewed_at' => null,
                ]);

                $this->patchJson($this->actionUrl($work, 'assign-reviewer'), ['reviewer_id' => $reviewer->id])
                    ->assertOk()
                    ->assertJsonPath('data.changed', true)
                    ->assertJsonPath('data.work.status', $status)
                    ->assertJsonPath('data.work.reviewer.id', $reviewer->id)
                    ->assertJsonPath('data.work.reviewed_at', null);

                $this->patchJson($this->actionUrl($work, 'assign-reviewer'), ['reviewer_id' => $reviewer->id])
                    ->assertOk()
                    ->assertJsonPath('data.changed', false);
            }
        }
    }

    public function test_assign_reviewer_rejects_external_non_internal_and_unsupported_targets(): void
    {
        $this->actingAsRole('super-admin');

        foreach (['client', 'designer'] as $role) {
            $reviewer = User::factory()->create();
            $reviewer->assignRole($role);
            $work = Work::factory()->submitted()->create();

            $this->patchJson($this->actionUrl($work, 'assign-reviewer'), ['reviewer_id' => $reviewer->id])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('reviewer_id');
        }

        $mixedRoleReviewer = User::factory()->create();
        $mixedRoleReviewer->assignRole(['staff', 'designer']);
        $work = Work::factory()->submitted()->create();
        $this->patchJson($this->actionUrl($work, 'assign-reviewer'), [
            'reviewer_id' => $mixedRoleReviewer->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('reviewer_id');

        Role::create(['name' => 'outside-reviewer', 'guard_name' => 'web']);
        $reviewer = User::factory()->create();
        $reviewer->assignRole('outside-reviewer');
        $work = Work::factory()->submitted()->create();
        $this->patchJson($this->actionUrl($work, 'assign-reviewer'), ['reviewer_id' => $reviewer->id])
            ->assertUnprocessable();

        $internal = User::factory()->create();
        $internal->assignRole('staff');
        foreach ($this->statusesExcept([
            Work::STATUS_SUBMITTED,
            Work::STATUS_IN_REVIEW,
            Work::STATUS_CHANGES_REQUESTED,
        ]) as $status) {
            $invalid = Work::factory()->create(['status' => $status]);
            $this->patchJson($this->actionUrl($invalid, 'assign-reviewer'), ['reviewer_id' => $internal->id])
                ->assertUnprocessable();
        }
    }

    public function test_assign_reviewer_requires_an_existing_integer_reviewer_and_rejects_extra_fields(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([
            [],
            ['reviewer_id' => 'not-an-integer'],
            ['reviewer_id' => 999999],
        ] as $payload) {
            $work = Work::factory()->submitted()->create(['reviewer_id' => null]);

            $this->patchJson($this->actionUrl($work, 'assign-reviewer'), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('reviewer_id');
            $this->assertNull($work->fresh()->reviewer_id);
        }

        $reviewer = User::factory()->create();
        $reviewer->assignRole('staff');
        $work = Work::factory()->submitted()->create(['reviewer_id' => null]);
        $this->patchJson($this->actionUrl($work, 'assign-reviewer'), [
            'reviewer_id' => $reviewer->id,
            'status' => Work::STATUS_APPROVED,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertNull($work->fresh()->reviewer_id);
    }

    public function test_approve_makes_in_review_work_approved_hidden_and_clears_prior_decision_text(): void
    {
        Carbon::setTestNow('2026-07-16 12:00:00');
        $actor = $this->actingAsRole('super-admin');
        $work = Work::factory()->inReview()->create([
            'reviewer_id' => null,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'rejected_at' => now()->subDay(),
            'rejection_reason' => 'سبب رفض تاريخي خاص',
            'change_request_notes' => 'ملاحظات تعديل تاريخية خاصة',
            'is_featured' => true,
            'is_pinned' => true,
        ]);

        $this->patchJson($this->actionUrl($work, 'approve'))
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.work.status', Work::STATUS_APPROVED)
            ->assertJsonPath('data.work.visibility_status', Work::VISIBILITY_HIDDEN)
            ->assertJsonPath('data.work.reviewer.id', $actor->id)
            ->assertJsonPath('data.work.is_featured', true)
            ->assertJsonPath('data.work.is_pinned', true)
            ->assertJsonPath('data.work.published_at', null);

        $fresh = $work->fresh();
        $this->assertTrue($fresh->reviewed_at->equalTo(now()));
        $this->assertTrue($fresh->approved_at->equalTo(now()));
        $this->assertNull($fresh->rejected_at);
        $this->assertNull($fresh->rejection_reason);
        $this->assertNull($fresh->change_request_notes);
    }

    public function test_approve_is_idempotent_and_rejects_unsupported_states(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->approved()->create();
        $reviewedAt = $work->reviewed_at;
        $approvedAt = $work->approved_at;

        $this->patchJson($this->actionUrl($work, 'approve'))
            ->assertOk()
            ->assertJsonPath('data.changed', false);
        $this->assertTrue($work->fresh()->reviewed_at->equalTo($reviewedAt));
        $this->assertTrue($work->fresh()->approved_at->equalTo($approvedAt));

        foreach ($this->statusesExcept([Work::STATUS_IN_REVIEW, Work::STATUS_APPROVED]) as $status) {
            $invalid = Work::factory()->create(['status' => $status]);
            $this->patchJson($this->actionUrl($invalid, 'approve'))->assertUnprocessable();
        }
    }

    public function test_request_changes_stores_private_notes_without_returning_them_and_supports_safe_repetition(): void
    {
        Carbon::setTestNow('2026-07-16 13:00:00');
        $actor = $this->actingAsRole('super-admin');
        $notes = 'ملاحظات خاصة مطلوبة لمعالجة العمل';
        $updatedNotes = 'ملاحظات خاصة محدثة لمعالجة العمل';
        $work = Work::factory()->inReview()->create([
            'reviewer_id' => null,
            'approved_at' => now()->subDay(),
            'rejected_at' => now()->subDay(),
            'rejection_reason' => 'سبب خاص سابق',
        ]);

        $response = $this->patchJson($this->actionUrl($work, 'request-changes'), [
            'change_request_notes' => $notes,
        ])->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.work.status', Work::STATUS_CHANGES_REQUESTED)
            ->assertJsonPath('data.work.visibility_status', Work::VISIBILITY_HIDDEN)
            ->assertJsonPath('data.work.reviewer.id', $actor->id);

        $this->assertStringNotContainsString($notes, $response->getContent());
        $fresh = $work->fresh();
        $reviewedAt = $fresh->reviewed_at;
        $this->assertSame($notes, $fresh->change_request_notes);
        $this->assertNull($fresh->rejection_reason);
        $this->assertNull($fresh->approved_at);
        $this->assertNull($fresh->rejected_at);

        $this->patchJson($this->actionUrl($work, 'request-changes'), ['change_request_notes' => $notes])
            ->assertOk()->assertJsonPath('data.changed', false);
        $this->patchJson($this->actionUrl($work, 'request-changes'), ['change_request_notes' => $updatedNotes])
            ->assertOk()->assertJsonPath('data.changed', true);

        $fresh = $work->fresh();
        $this->assertSame($updatedNotes, $fresh->change_request_notes);
        $this->assertTrue($fresh->reviewed_at->equalTo($reviewedAt));
    }

    public function test_request_changes_validates_input_and_rejects_unsupported_states(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->inReview()->create();

        foreach ([[], ['change_request_notes' => 'قصير']] as $payload) {
            $this->patchJson($this->actionUrl($work, 'request-changes'), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('change_request_notes');
        }

        foreach ($this->statusesExcept([Work::STATUS_IN_REVIEW, Work::STATUS_CHANGES_REQUESTED]) as $status) {
            $invalid = Work::factory()->create(['status' => $status]);
            $this->patchJson($this->actionUrl($invalid, 'request-changes'), [
                'change_request_notes' => 'ملاحظات تعديل صحيحة',
            ])->assertUnprocessable();
        }
    }

    public function test_reject_records_decision_without_exposing_reason_and_supports_safe_repetition(): void
    {
        Carbon::setTestNow('2026-07-16 14:00:00');
        $actor = $this->actingAsRole('super-admin');
        $reason = 'سبب رفض خاص لا يجوز كشفه';
        $updatedReason = 'سبب رفض خاص محدث لا يجوز كشفه';
        $work = Work::factory()->inReview()->create([
            'reviewer_id' => null,
            'approved_at' => now()->subDay(),
            'change_request_notes' => 'ملاحظات خاصة سابقة',
        ]);

        $response = $this->patchJson($this->actionUrl($work, 'reject'), [
            'rejection_reason' => $reason,
        ])->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.work.status', Work::STATUS_REJECTED)
            ->assertJsonPath('data.work.visibility_status', Work::VISIBILITY_HIDDEN)
            ->assertJsonPath('data.work.reviewer.id', $actor->id);

        $this->assertStringNotContainsString($reason, $response->getContent());
        $fresh = $work->fresh();
        $rejectedAt = $fresh->rejected_at;
        $this->assertTrue($fresh->reviewed_at->equalTo(now()));
        $this->assertTrue($fresh->rejected_at->equalTo(now()));
        $this->assertSame($reason, $fresh->rejection_reason);
        $this->assertNull($fresh->change_request_notes);
        $this->assertNull($fresh->approved_at);

        $this->patchJson($this->actionUrl($work, 'reject'), ['rejection_reason' => $reason])
            ->assertOk()->assertJsonPath('data.changed', false);
        $this->patchJson($this->actionUrl($work, 'reject'), ['rejection_reason' => $updatedReason])
            ->assertOk()->assertJsonPath('data.changed', true);

        $fresh = $work->fresh();
        $this->assertSame($updatedReason, $fresh->rejection_reason);
        $this->assertTrue($fresh->rejected_at->equalTo($rejectedAt));
    }

    public function test_reject_validates_input_and_rejects_unsupported_states(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->inReview()->create();

        foreach ([[], ['rejection_reason' => 'قصير']] as $payload) {
            $this->patchJson($this->actionUrl($work, 'reject'), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('rejection_reason');
        }

        foreach ($this->statusesExcept([Work::STATUS_IN_REVIEW, Work::STATUS_REJECTED]) as $status) {
            $invalid = Work::factory()->create(['status' => $status]);
            $this->patchJson($this->actionUrl($invalid, 'reject'), [
                'rejection_reason' => 'سبب رفض صحيح وآمن',
            ])->assertUnprocessable();
        }
    }

    public function test_publish_after_approval_publishes_publicly_and_preserves_review_state_and_promotions(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->approved()->create([
            'published_at' => null,
            'is_featured' => true,
            'is_pinned' => true,
        ]);
        $reviewedAt = $work->reviewed_at;
        $approvedAt = $work->approved_at;
        $reviewerId = $work->reviewer_id;

        $this->patchJson($this->actionUrl($work, 'publish'))
            ->assertOk()
            ->assertJsonPath('data.action', 'publish')
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.work.status', Work::STATUS_PUBLISHED)
            ->assertJsonPath('data.work.visibility_status', Work::VISIBILITY_PUBLIC)
            ->assertJsonPath('data.work.is_featured', true)
            ->assertJsonPath('data.work.is_pinned', true);

        $fresh = $work->fresh();
        $this->assertNotNull($fresh->published_at);
        $this->assertTrue($fresh->reviewed_at->equalTo($reviewedAt));
        $this->assertTrue($fresh->approved_at->equalTo($approvedAt));
        $this->assertSame($reviewerId, $fresh->reviewer_id);

        $this->patchJson($this->actionUrl($work, 'publish'))
            ->assertOk()->assertJsonPath('data.changed', false);
    }

    public function test_publish_rejects_published_hidden_archived_and_all_other_unsupported_states(): void
    {
        $this->actingAsRole('super-admin');
        $publishedHidden = Work::factory()->published()->create(['visibility_status' => Work::VISIBILITY_HIDDEN]);
        $this->patchJson($this->actionUrl($publishedHidden, 'publish'))->assertUnprocessable();

        foreach ($this->statusesExcept([Work::STATUS_APPROVED, Work::STATUS_PUBLISHED]) as $status) {
            $invalid = Work::factory()->create(['status' => $status]);
            $this->patchJson($this->actionUrl($invalid, 'publish'))->assertUnprocessable();
        }
    }

    public function test_reopen_returns_supported_decisions_to_in_review_and_preserves_history(): void
    {
        $actor = $this->actingAsRole('super-admin');

        foreach ([Work::STATUS_CHANGES_REQUESTED, Work::STATUS_REJECTED, Work::STATUS_APPROVED] as $status) {
            $reviewedAt = now()->subDays(4)->startOfSecond();
            $approvedAt = now()->subDays(3)->startOfSecond();
            $rejectedAt = now()->subDays(2)->startOfSecond();
            $work = Work::factory()->create([
                'status' => $status,
                'visibility_status' => Work::VISIBILITY_PUBLIC,
                'reviewer_id' => null,
                'reviewed_at' => $reviewedAt,
                'approved_at' => $approvedAt,
                'rejected_at' => $rejectedAt,
                'rejection_reason' => 'سبب تاريخي محفوظ',
                'change_request_notes' => 'ملاحظات تاريخية محفوظة',
            ]);

            $this->patchJson($this->actionUrl($work, 'reopen'))
                ->assertOk()
                ->assertJsonPath('data.changed', true)
                ->assertJsonPath('data.work.status', Work::STATUS_IN_REVIEW)
                ->assertJsonPath('data.work.visibility_status', Work::VISIBILITY_HIDDEN)
                ->assertJsonPath('data.work.reviewer.id', $actor->id);

            $fresh = $work->fresh();
            $this->assertTrue($fresh->reviewed_at->equalTo($reviewedAt));
            $this->assertTrue($fresh->approved_at->equalTo($approvedAt));
            $this->assertTrue($fresh->rejected_at->equalTo($rejectedAt));
            $this->assertSame('سبب تاريخي محفوظ', $fresh->rejection_reason);
            $this->assertSame('ملاحظات تاريخية محفوظة', $fresh->change_request_notes);
        }
    }

    public function test_reopen_is_idempotent_in_review_assigns_actor_if_needed_and_rejects_other_states(): void
    {
        $actor = $this->actingAsRole('super-admin');
        $unassigned = Work::factory()->inReview()->create(['reviewer_id' => null]);
        $this->patchJson($this->actionUrl($unassigned, 'reopen'))
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.work.reviewer.id', $actor->id);
        $this->patchJson($this->actionUrl($unassigned, 'reopen'))
            ->assertOk()->assertJsonPath('data.changed', false);

        foreach ([
            Work::STATUS_DRAFT,
            Work::STATUS_SUBMITTED,
            Work::STATUS_PUBLISHED,
            Work::STATUS_HIDDEN,
            Work::STATUS_ARCHIVED,
        ] as $status) {
            $invalid = Work::factory()->create(['status' => $status]);
            $this->patchJson($this->actionUrl($invalid, 'reopen'))->assertUnprocessable();
        }
    }

    public function test_action_response_shape_and_relations_are_safe_and_flags_are_correct(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create([
            'name' => 'Safe Designer',
            'email' => 'designer-private@example.test',
        ]);
        $work = Work::factory()->inReview()->create([
            'title' => 'Safe Review Work',
            'summary' => 'Safe summary',
            'designer_id' => $designer->id,
            'reviewer_id' => null,
            'description' => 'private-description-marker',
            'internal_notes' => 'private-internal-marker',
            'rejection_reason' => 'private-rejection-marker',
            'change_request_notes' => 'private-change-marker',
            'reports_count' => 2,
        ]);

        $response = $this->patchJson($this->actionUrl($work, 'approve'))
            ->assertOk()
            ->assertJsonPath('message', 'تم تنفيذ إجراء المراجعة بنجاح')
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.work.designer', ['id' => $designer->id, 'name' => $designer->name])
            ->assertJsonPath('data.work.review_flags', [
                'assigned' => true,
                'in_queue' => false,
                'decision_made' => true,
                'is_published' => false,
                'has_reports' => true,
                'needs_attention' => true,
            ]);

        $workPayload = $response->json('data.work');
        $this->assertSame([
            'approved_at',
            'category_id',
            'created_at',
            'designer',
            'id',
            'is_featured',
            'is_pinned',
            'likes_count',
            'media_type',
            'published_at',
            'rejected_at',
            'reports_count',
            'review_flags',
            'reviewed_at',
            'reviewer',
            'slug',
            'status',
            'submitted_at',
            'summary',
            'title',
            'updated_at',
            'views_count',
            'visibility_status',
        ], collect(array_keys($workPayload))->sort()->values()->all());
        $this->assertSame(['id', 'name'], array_keys($workPayload['designer']));
        $this->assertSame(['id', 'name'], array_keys($workPayload['reviewer']));

        $keys = $this->recursiveKeys($response->json());
        foreach ($this->forbiddenResponseKeys() as $key) {
            $this->assertNotContains($key, $keys);
        }

        foreach ([
            'designer-private@example.test',
            'private-description-marker',
            'private-internal-marker',
            'private-rejection-marker',
            'private-change-marker',
        ] as $value) {
            $this->assertStringNotContainsString($value, $response->getContent());
        }
    }

    public function test_query_unknown_and_sensitive_body_fields_return_422_without_changes(): void
    {
        $this->actingAsRole('super-admin');
        $sensitiveFields = [
            'email', 'password', 'token', 'cookie', 'metadata', 'payload', 'description',
            'summary', 'internal_notes', 'delete', 'force_delete', 'hard_delete', 'archive',
            'visibility_status', 'is_featured', 'is_pinned', 'reports_count', 'designer_id', 'status',
        ];

        $queryWork = Work::factory()->submitted()->create();
        $this->patchJson($this->actionUrl($queryWork, 'start').'?unexpected=1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unexpected');
        $this->assertSame(Work::STATUS_SUBMITTED, $queryWork->fresh()->status);

        foreach ($sensitiveFields as $field) {
            $work = Work::factory()->submitted()->create();
            $this->patchJson($this->actionUrl($work, 'start'), [$field => 'forbidden-value'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
            $this->assertSame(Work::STATUS_SUBMITTED, $work->fresh()->status);
        }

        foreach (['start', 'approve', 'publish', 'reopen'] as $action) {
            [$work] = $this->actionCase($action);
            $this->patchJson($this->actionUrl($work, $action), ['extra' => 'value'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('extra');
        }

        $reviewer = User::factory()->create();
        $reviewer->assignRole('staff');
        $work = Work::factory()->submitted()->create();
        $this->patchJson($this->actionUrl($work, 'assign-reviewer'), [
            'reviewer_id' => $reviewer->id,
            'extra' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('extra');
    }

    public function test_missing_work_routes_and_http_methods_are_safe_and_review_read_route_still_works(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson('/api/admin/works/999999/review/start')->assertNotFound();
        $this->patchJson('/api/admin/works/not-a-number/review/start')->assertNotFound();

        $work = Work::factory()->submitted()->create();
        $url = $this->actionUrl($work, 'start');
        $this->postJson($url)->assertStatus(405);
        $this->putJson($url)->assertStatus(405);
        $this->deleteJson($url)->assertStatus(405);

        $this->getJson('/api/admin/works/review')
            ->assertOk()
            ->assertJsonPath('success', true);

        $readRoute = Route::getRoutes()->match(Request::create('/api/admin/works/review', 'GET'));
        $this->assertSame(WorksReviewQueueController::class.'@index', $readRoute->getActionName());
    }

    public function test_all_action_routes_use_patch_number_constraint_and_expected_controller_methods(): void
    {
        $methods = [
            'start' => 'start',
            'assign-reviewer' => 'assignReviewer',
            'approve' => 'approve',
            'request-changes' => 'requestChanges',
            'reject' => 'reject',
            'publish' => 'publishAfterApproval',
            'reopen' => 'reopen',
        ];

        foreach ($methods as $segment => $method) {
            $route = Route::getRoutes()->match(Request::create(
                "/api/admin/works/1/review/{$segment}",
                'PATCH',
            ));

            $this->assertSame(['PATCH'], $route->methods());
            $this->assertSame(WorksReviewActionController::class.'@'.$method, $route->getActionName());
            $this->assertSame('[0-9]+', $route->wheres['work'] ?? null);
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        }
    }

    public function test_actions_never_delete_or_mutate_designer_counters_or_promotion_flags(): void
    {
        $this->actingAsRole('super-admin');

        foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
            [$work, $payload] = $this->actionCase($action);
            $designerId = $work->designer_id;
            $work->forceFill([
                'views_count' => 31,
                'likes_count' => 17,
                'reports_count' => 4,
                'is_featured' => true,
                'is_pinned' => true,
            ])->save();

            $this->patchJson($this->actionUrl($work, $action), $payload)->assertOk();

            $fresh = Work::query()->findOrFail($work->id);
            $this->assertSame($designerId, $fresh->designer_id);
            $this->assertSame(31, $fresh->views_count);
            $this->assertSame(17, $fresh->likes_count);
            $this->assertSame(4, $fresh->reports_count);
            $this->assertTrue($fresh->is_featured);
            $this->assertTrue($fresh->is_pinned);
        }
    }

    public function test_audit_events_use_exact_types_safe_allowlist_and_only_record_changes(): void
    {
        $this->actingAsRole('super-admin');

        foreach (self::AUDIT_EVENT_TYPES as $action => $eventType) {
            [$work, $payload] = $this->actionCase($action);
            $privateText = $action === 'request-changes'
                ? (string) $payload['change_request_notes']
                : ($action === 'reject' ? (string) $payload['rejection_reason'] : null);

            $this->patchJson($this->actionUrl($work, $action), $payload)
                ->assertOk()
                ->assertJsonPath('data.changed', true);

            $event = AuditEvent::query()->where('target_id', $work->id)->sole();
            $this->assertSame($eventType, $event->event_type);
            $this->assertSame([
                'action',
                'new_has_change_request_notes',
                'new_has_rejection_reason',
                'new_reviewer_id',
                'new_status',
                'new_visibility_status',
                'old_has_change_request_notes',
                'old_has_rejection_reason',
                'old_reviewer_id',
                'old_status',
                'old_visibility_status',
                'work_id',
            ], collect(array_keys($event->metadata))->sort()->values()->all());

            $metadataJson = json_encode($event->metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            foreach (['title', 'slug', 'summary', 'description', 'internal_notes'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, strtolower($metadataJson));
            }
            if ($privateText !== null) {
                $this->assertStringNotContainsString($privateText, $metadataJson);
            }
        }

        $eventCount = AuditEvent::query()->count();
        $approved = Work::factory()->approved()->create();
        $this->patchJson($this->actionUrl($approved, 'approve'))
            ->assertOk()->assertJsonPath('data.changed', false);
        $this->assertSame($eventCount, AuditEvent::query()->count());
    }

    /** @return array{Work, array<string, mixed>} */
    private function actionCase(string $action): array
    {
        return match ($action) {
            'start' => [Work::factory()->submitted()->create(['reviewer_id' => null]), []],
            'assign-reviewer' => $this->assignReviewerCase(),
            'approve' => [Work::factory()->inReview()->create(), []],
            'request-changes' => [
                Work::factory()->inReview()->create(),
                ['change_request_notes' => 'ملاحظات تعديل خاصة صالحة للاختبار'],
            ],
            'reject' => [
                Work::factory()->inReview()->create(),
                ['rejection_reason' => 'سبب رفض خاص صالح للاختبار'],
            ],
            'publish' => [Work::factory()->approved()->create(), []],
            'reopen' => [Work::factory()->rejected()->create(), []],
            default => throw new \InvalidArgumentException('Unknown review action.'),
        };
    }

    /** @return array{Work, array{reviewer_id: int}} */
    private function assignReviewerCase(): array
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('staff');

        return [
            Work::factory()->submitted()->create(['reviewer_id' => null]),
            ['reviewer_id' => $reviewer->id],
        ];
    }

    /** @param list<string> $allowed @return list<string> */
    private function statusesExcept(array $allowed): array
    {
        return array_values(array_diff([
            Work::STATUS_DRAFT,
            Work::STATUS_SUBMITTED,
            Work::STATUS_IN_REVIEW,
            Work::STATUS_CHANGES_REQUESTED,
            Work::STATUS_APPROVED,
            Work::STATUS_PUBLISHED,
            Work::STATUS_REJECTED,
            Work::STATUS_HIDDEN,
            Work::STATUS_ARCHIVED,
        ], $allowed));
    }

    private function actionUrl(Work $work, string $action): string
    {
        return "/api/admin/works/{$work->id}/review/{$action}";
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
    private function forbiddenResponseKeys(): array
    {
        return [
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
            'model',
            'users',
            'roles',
        ];
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
                $keys[] = strtolower($key);
            }

            $keys = [...$keys, ...$this->recursiveKeys($nestedValue)];
        }

        return array_values(array_unique($keys));
    }
}
