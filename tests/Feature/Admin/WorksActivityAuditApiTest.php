<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkReport;
use App\Services\Works\WorksActivityAuditQuery;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorksActivityAuditApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_default_source_remains_lifecycle(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create(['designer_id' => null]);
        $this->createAuditEvent();

        $this->getJson('/api/admin/works/activity')
            ->assertOk()
            ->assertJsonPath('data.activity_source.mode', 'lifecycle')
            ->assertJsonPath('data.activity_source.source', 'work_lifecycle_timestamps')
            ->assertJsonPath('data.activity_source.dedicated_log_available', false)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.items.0.work_id', $work->id);
    }

    public function test_explicit_lifecycle_source_returns_the_legacy_contract(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create(['designer_id' => null]);

        $response = $this->getJson($this->endpoint([
            'source' => 'lifecycle',
            'event_type' => 'created',
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', 'work-'.$work->id.'-created')
            ->assertJsonPath('data.items.0.event_type', 'created')
            ->assertJsonPath('data.activity_source.mode', 'lifecycle');

        $this->assertArrayNotHasKey('event_catalog', $response->json('data'));
        $this->assertSame([
            'activity_flags',
            'category_id',
            'designer',
            'event_at',
            'event_label',
            'event_type',
            'id',
            'likes_count',
            'media_type',
            'reports_count',
            'reviewer',
            'slug',
            'status',
            'title',
            'views_count',
            'visibility_status',
            'work_id',
        ], $this->sortedKeys($response->json('data.items.0')));
    }

    public function test_audit_source_returns_supported_audit_events(): void
    {
        $this->actingAsRole('super-admin');
        $event = $this->createAuditEvent();
        $this->createAuditEvent([
            'event_type' => 'works.unknown.event',
        ]);
        $this->createAuditEvent([
            'category' => 'security',
        ]);

        $response = $this->getJson($this->auditEndpoint())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', 'audit-'.$event->id)
            ->assertJsonPath('data.items.0.audit_event_id', $event->id)
            ->assertJsonPath('data.items.0.event_type', 'works.review.started');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/',
            $response->json('data.items.0.event_at'),
        );
        $this->assertSame([
            'action',
            'activity_flags',
            'actor',
            'audit_event_id',
            'event_at',
            'event_group',
            'event_key',
            'event_label_ar',
            'event_label_en',
            'event_type',
            'id',
            'outcome',
            'severity',
            'source',
            'target',
            'work',
        ], $this->sortedKeys($response->json('data.items.0')));
        $this->assertSame([
            'actor_missing',
            'needs_attention',
            'requires_work',
            'work_missing',
        ], $this->sortedKeys($response->json('data.items.0.activity_flags')));
        $this->assertSame(
            ['id', 'scope', 'type'],
            $this->sortedKeys($response->json('data.items.0.target')),
        );
    }

    public function test_audit_response_identifies_its_actual_source(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson($this->auditEndpoint())
            ->assertOk()
            ->assertJsonPath('data.activity_source', [
                'dedicated_log_available' => true,
                'legacy_source_available' => true,
                'source' => 'audit_events',
                'mode' => 'audit',
            ])
            ->assertJsonPath('data.filters.source', 'audit');
    }

    public function test_review_events_are_exposed_in_audit_mode(): void
    {
        $this->actingAsRole('super-admin');
        $event = $this->createAuditEvent([
            'event_type' => 'works.review.changes_requested',
            'action' => 'request_changes',
        ]);

        $this->getJson($this->auditEndpoint())
            ->assertOk()
            ->assertJsonPath('data.items.0.audit_event_id', $event->id)
            ->assertJsonPath('data.items.0.event_group', 'review')
            ->assertJsonPath('data.items.0.event_key', 'changes_requested')
            ->assertJsonPath('data.items.0.activity_flags.needs_attention', true);
    }

    public function test_visibility_events_are_exposed_in_audit_mode(): void
    {
        $this->actingAsRole('super-admin');
        $event = $this->createAuditEvent([
            'event_type' => 'works.visibility.hidden',
            'action' => 'hide',
        ]);

        $this->getJson($this->auditEndpoint())
            ->assertOk()
            ->assertJsonPath('data.items.0.audit_event_id', $event->id)
            ->assertJsonPath('data.items.0.event_group', 'visibility')
            ->assertJsonPath('data.items.0.target.type', 'work');
    }

    public function test_report_events_resolve_work_through_work_report(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create([
            'designer_id' => null,
            'title' => 'Audit Report Work',
            'slug' => 'audit-report-work',
        ]);
        $report = WorkReport::factory()->create([
            'work_id' => $work->id,
            'reporter_id' => null,
        ]);
        $event = $this->createAuditEvent([
            'event_type' => 'works.reports.dismissed',
            'target_type' => 'work_report',
            'target_id' => $report->id,
        ]);

        $this->getJson($this->auditEndpoint())
            ->assertOk()
            ->assertJsonPath('data.items.0.audit_event_id', $event->id)
            ->assertJsonPath('data.items.0.target.id', $report->id)
            ->assertJsonPath('data.items.0.work.id', $work->id)
            ->assertJsonPath('data.items.0.work.title', 'Audit Report Work');
    }

    public function test_taxonomy_events_are_exposed_without_a_work(): void
    {
        $this->actingAsRole('super-admin');
        $event = $this->createAuditEvent([
            'event_type' => 'works.taxonomy.category.created',
            'target_type' => 'work_category',
            'target_id' => 815,
        ]);

        $this->getJson($this->auditEndpoint())
            ->assertOk()
            ->assertJsonPath('data.items.0.audit_event_id', $event->id)
            ->assertJsonPath('data.items.0.event_group', 'taxonomy')
            ->assertJsonPath('data.items.0.target.type', 'work_category')
            ->assertJsonPath('data.items.0.work', null)
            ->assertJsonPath('data.items.0.activity_flags.requires_work', false)
            ->assertJsonPath('data.items.0.activity_flags.work_missing', false);
    }

    public function test_assignment_events_resolve_work_directly(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create(['designer_id' => null]);
        $event = $this->createAuditEvent([
            'event_type' => 'work.category.changed',
            'target_id' => $work->id,
            'action' => 'category_change',
        ]);

        $this->getJson($this->auditEndpoint())
            ->assertOk()
            ->assertJsonPath('data.items.0.audit_event_id', $event->id)
            ->assertJsonPath('data.items.0.event_group', 'taxonomy_assignment')
            ->assertJsonPath('data.items.0.work.id', $work->id);
    }

    public function test_actor_payload_contains_only_id_name_and_role(): void
    {
        $this->actingAsRole('super-admin');
        $actor = User::factory()->create([
            'name' => 'Visible Audit Actor',
            'email' => 'private-audit-actor@example.test',
        ]);
        $this->createAuditEvent([
            'actor_id' => $actor->id,
            'actor_role' => 'admin',
        ]);

        $response = $this->getJson($this->auditEndpoint())
            ->assertOk()
            ->assertJsonPath('data.items.0.actor', [
                'id' => $actor->id,
                'name' => 'Visible Audit Actor',
                'role' => 'admin',
            ]);

        $this->assertSame(['id', 'name', 'role'], array_keys($response->json('data.items.0.actor')));
        $this->assertStringNotContainsString($actor->email, $response->getContent());
    }

    public function test_missing_actor_does_not_remove_the_event(): void
    {
        $this->actingAsRole('super-admin');
        $actor = User::factory()->create();
        $event = $this->createAuditEvent(['actor_id' => $actor->id]);
        $actor->delete();

        $this->getJson($this->auditEndpoint())
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.audit_event_id', $event->id)
            ->assertJsonPath('data.items.0.actor', null)
            ->assertJsonPath('data.items.0.activity_flags.actor_missing', true);
    }

    public function test_missing_target_or_work_does_not_remove_the_event(): void
    {
        $this->actingAsRole('super-admin');
        $event = $this->createAuditEvent([
            'target_id' => 999_999_991,
        ]);

        $this->getJson($this->auditEndpoint())
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.audit_event_id', $event->id)
            ->assertJsonPath('data.items.0.target.id', 999_999_991)
            ->assertJsonPath('data.items.0.work', null)
            ->assertJsonPath('data.items.0.activity_flags.work_missing', true);
    }

    public function test_audit_payload_never_exposes_metadata_or_sensitive_fields(): void
    {
        $this->actingAsRole('super-admin');
        $this->createAuditEvent([
            'ip_address' => '192.0.2.55',
            'user_agent' => 'private-audit-user-agent',
            'request_id' => 'private-audit-request',
            'correlation_id' => 'private-audit-correlation',
            'metadata' => [
                'rejection_reason' => 'private-audit-rejection',
                'change_request_notes' => 'private-audit-change',
                'private_notes' => 'private-audit-notes',
                'reporter_email' => 'private-reporter@example.test',
            ],
        ]);

        $response = $this->getJson($this->auditEndpoint())->assertOk();
        $keys = $this->recursiveKeys($response->json());
        $json = $response->getContent();

        foreach ([
            'metadata',
            'ip_address',
            'user_agent',
            'request_id',
            'correlation_id',
            'rejection_reason',
            'change_request_notes',
            'private_notes',
            'reporter_email',
        ] as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $keys);
        }

        foreach ([
            'private-audit-user-agent',
            'private-audit-request',
            'private-audit-correlation',
            'private-audit-rejection',
            'private-audit-change',
            'private-audit-notes',
            'private-reporter@example.test',
        ] as $forbiddenValue) {
            $this->assertStringNotContainsString($forbiddenValue, $json);
        }
    }

    public function test_audit_pagination_runs_with_database_paginator_contract(): void
    {
        $this->actingAsRole('super-admin');

        foreach (range(1, 5) as $targetId) {
            $this->createAuditEvent([
                'event_type' => 'works.taxonomy.tag.updated',
                'target_type' => 'work_tag',
                'target_id' => $targetId,
            ]);
        }

        $this->getJson($this->auditEndpoint([
            'per_page' => 2,
            'page' => 2,
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.pagination', [
                'current_page' => 2,
                'per_page' => 2,
                'total' => 5,
                'last_page' => 3,
            ]);
        $this->getJson($this->auditEndpoint(['per_page' => 51]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_audit_summary_honors_active_filters(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create(['designer_id' => null]);
        $this->createAuditEvent([
            'event_type' => 'works.review.changes_requested',
            'target_id' => $work->id,
        ]);
        $this->createAuditEvent([
            'event_type' => 'works.visibility.published',
            'target_id' => $work->id,
        ]);
        $this->createAuditEvent([
            'event_type' => 'works.taxonomy.tag.created',
            'target_type' => 'work_tag',
            'target_id' => 50,
        ]);

        $this->getJson($this->auditEndpoint(['event_group' => 'review']))
            ->assertOk()
            ->assertJsonPath('data.summary', [
                'total_events' => 1,
                'unique_works' => 1,
                'review_events' => 1,
                'visibility_events' => 0,
                'report_events' => 0,
                'taxonomy_events' => 0,
                'taxonomy_assignment_events' => 0,
                'attention_events' => 1,
            ]);
    }

    public function test_event_catalog_matches_the_query_registry(): void
    {
        $this->actingAsRole('super-admin');
        $service = app(WorksActivityAuditQuery::class);

        $response = $this->getJson($this->auditEndpoint())
            ->assertOk()
            ->assertJsonCount(5, 'data.event_catalog.groups')
            ->assertJsonCount(27, 'data.event_catalog.events');

        $this->assertSame($service->eventCatalog(), $response->json('data.event_catalog'));
        $this->assertSame(
            $service->supportedEventTypes(),
            $response->json('data.event_catalog.events.*.event_type'),
        );
        $this->assertArrayNotHasKey(
            'severity_fallback',
            $response->json('data.event_catalog.events.0'),
        );
    }

    public function test_q_searches_only_approved_audit_columns(): void
    {
        $this->actingAsRole('super-admin');
        $actor = User::factory()->create(['name' => 'Needle Audit Actor']);
        $work = Work::factory()->create([
            'designer_id' => null,
            'title' => 'Needle Audit Work',
            'slug' => 'needle-audit-slug',
        ]);
        $event = $this->createAuditEvent([
            'event_type' => 'works.review.started',
            'actor_id' => $actor->id,
            'target_id' => $work->id,
            'action' => 'needle_action',
        ]);

        foreach ([
            'works.review.started',
            'needle_action',
            'Needle Audit Actor',
            'Needle Audit Work',
            'needle-audit-slug',
        ] as $query) {
            $this->getJson($this->auditEndpoint(['q' => $query]))
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 1)
                ->assertJsonPath('data.items.0.audit_event_id', $event->id);
        }
    }

    public function test_q_never_searches_metadata(): void
    {
        $this->actingAsRole('super-admin');
        $this->createAuditEvent([
            'metadata' => ['private_notes' => 'metadata-search-marker'],
        ]);

        $this->getJson($this->auditEndpoint(['q' => 'metadata-search-marker']))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0)
            ->assertJsonPath('data.summary.total_events', 0)
            ->assertJsonPath('data.items', []);
    }

    public function test_event_type_filter_uses_the_audit_registry(): void
    {
        $this->actingAsRole('super-admin');
        $expected = $this->createAuditEvent(['event_type' => 'works.review.approved']);
        $this->createAuditEvent(['event_type' => 'works.visibility.hidden']);

        $this->getJson($this->auditEndpoint(['event_type' => 'works.review.approved']))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.audit_event_id', $expected->id);
    }

    public function test_event_group_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = $this->createAuditEvent(['event_type' => 'works.visibility.hidden']);
        $this->createAuditEvent(['event_type' => 'works.review.approved']);

        $this->getJson($this->auditEndpoint(['event_group' => 'visibility']))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.audit_event_id', $expected->id);
    }

    public function test_actor_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expectedActor = User::factory()->create();
        $otherActor = User::factory()->create();
        $expected = $this->createAuditEvent(['actor_id' => $expectedActor->id]);
        $this->createAuditEvent(['actor_id' => $otherActor->id]);

        $this->getJson($this->auditEndpoint(['actor_id' => $expectedActor->id]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.audit_event_id', $expected->id);
    }

    public function test_target_type_and_target_id_filters_work_together(): void
    {
        $this->actingAsRole('super-admin');
        $expected = $this->createAuditEvent([
            'event_type' => 'works.taxonomy.tag.updated',
            'target_type' => 'work_tag',
            'target_id' => 42,
        ]);
        $this->createAuditEvent([
            'event_type' => 'works.taxonomy.tag.updated',
            'target_type' => 'work_tag',
            'target_id' => 43,
        ]);

        $this->getJson($this->auditEndpoint([
            'target_type' => 'work_tag',
            'target_id' => 42,
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.audit_event_id', $expected->id);

        foreach (['actor_id', 'target_id', 'work_id'] as $idFilter) {
            $this->getJson($this->auditEndpoint([$idFilter => 0]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($idFilter);
        }
    }

    public function test_work_id_filter_matches_direct_and_report_work_resolution(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create(['designer_id' => null]);
        $otherWork = Work::factory()->create(['designer_id' => null]);
        $report = WorkReport::factory()->create([
            'work_id' => $work->id,
            'reporter_id' => null,
        ]);
        $direct = $this->createAuditEvent(['target_id' => $work->id]);
        $throughReport = $this->createAuditEvent([
            'event_type' => 'works.reports.review_started',
            'target_type' => 'work_report',
            'target_id' => $report->id,
        ]);
        $this->createAuditEvent(['target_id' => $otherWork->id]);

        $response = $this->getJson($this->auditEndpoint(['work_id' => $work->id]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2);

        $this->assertSame(
            [$direct->id, $throughReport->id],
            collect($response->json('data.items.*.audit_event_id'))->sort()->values()->all(),
        );
    }

    public function test_outcome_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = $this->createAuditEvent(['outcome' => 'failure']);
        $this->createAuditEvent(['outcome' => 'success']);

        $this->getJson($this->auditEndpoint(['outcome' => 'failure']))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.audit_event_id', $expected->id);
    }

    public function test_from_and_to_filter_occurred_at(): void
    {
        $this->actingAsRole('super-admin');
        $this->createAuditEvent(['occurred_at' => '2026-06-30 23:59:59']);
        $expected = $this->createAuditEvent(['occurred_at' => '2026-07-15 10:00:00']);
        $this->createAuditEvent(['occurred_at' => '2026-08-01 00:00:00']);

        $this->getJson($this->auditEndpoint([
            'from' => '2026-07-01',
            'to' => '2026-07-31',
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.audit_event_id', $expected->id);
    }

    public function test_all_allowed_audit_sort_columns_are_supported(): void
    {
        $this->actingAsRole('super-admin');
        $actors = [
            User::factory()->create(['name' => 'Charlie Actor']),
            User::factory()->create(['name' => 'Alpha Actor']),
            User::factory()->create(['name' => 'Bravo Actor']),
        ];
        $works = [
            Work::factory()->create(['designer_id' => null, 'title' => 'Charlie Work']),
            Work::factory()->create(['designer_id' => null, 'title' => 'Alpha Work']),
            Work::factory()->create(['designer_id' => null, 'title' => 'Bravo Work']),
        ];
        $events = [
            $this->createAuditEvent([
                'event_type' => 'works.review.started',
                'actor_id' => $actors[0]->id,
                'target_id' => $works[0]->id,
                'occurred_at' => '2026-07-18 10:00:00',
            ]),
            $this->createAuditEvent([
                'event_type' => 'works.visibility.hidden',
                'actor_id' => $actors[1]->id,
                'target_id' => $works[1]->id,
                'occurred_at' => '2026-07-18 11:00:00',
            ]),
            $this->createAuditEvent([
                'event_type' => 'work.tags.updated',
                'actor_id' => $actors[2]->id,
                'target_id' => $works[2]->id,
                'occurred_at' => '2026-07-18 12:00:00',
            ]),
        ];
        $expected = [
            'event_at' => [$events[0]->id, $events[1]->id, $events[2]->id],
            'audit_event_id' => [$events[0]->id, $events[1]->id, $events[2]->id],
            'event_type' => [$events[2]->id, $events[0]->id, $events[1]->id],
            'actor_name' => [$events[1]->id, $events[2]->id, $events[0]->id],
            'work_id' => [$events[0]->id, $events[1]->id, $events[2]->id],
            'work_title' => [$events[1]->id, $events[2]->id, $events[0]->id],
        ];

        foreach ($expected as $sort => $expectedIds) {
            $response = $this->getJson($this->auditEndpoint([
                'sort' => $sort,
                'direction' => 'asc',
                'per_page' => 50,
            ]))
                ->assertOk()
                ->assertJsonPath('data.filters.sort', $sort);

            $this->assertSame($expectedIds, $response->json('data.items.*.audit_event_id'));
        }
    }

    public function test_audit_ordering_is_deterministic_on_audit_event_id(): void
    {
        $this->actingAsRole('super-admin');
        $first = $this->createAuditEvent(['occurred_at' => '2026-07-18 12:00:00']);
        $second = $this->createAuditEvent(['occurred_at' => '2026-07-18 12:00:00']);

        $this->getJson($this->auditEndpoint())
            ->assertOk()
            ->assertJsonPath('data.items.0.audit_event_id', $second->id)
            ->assertJsonPath('data.items.1.audit_event_id', $first->id);
        $this->getJson($this->auditEndpoint(['direction' => 'asc']))
            ->assertOk()
            ->assertJsonPath('data.items.0.audit_event_id', $first->id)
            ->assertJsonPath('data.items.1.audit_event_id', $second->id);
    }

    public function test_invalid_source_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson($this->endpoint(['source' => 'combined']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source');
    }

    public function test_invalid_audit_event_type_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson($this->auditEndpoint(['event_type' => 'works.audit.unknown']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_type');
    }

    public function test_lifecycle_event_type_is_rejected_in_audit_mode(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson($this->auditEndpoint(['event_type' => 'created']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_type');
        $this->getJson($this->auditEndpoint(['sort' => 'status']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    public function test_audit_event_type_is_rejected_in_lifecycle_mode(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson($this->endpoint([
            'source' => 'lifecycle',
            'event_type' => 'work.tags.updated',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_type');
        $this->getJson($this->endpoint([
            'source' => 'lifecycle',
            'sort' => 'actor_name',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    public function test_audit_only_filter_is_rejected_in_lifecycle_mode(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson($this->endpoint([
            'source' => 'lifecycle',
            'event_group' => 'review',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_group');
    }

    public function test_lifecycle_only_filter_is_rejected_in_audit_mode(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson($this->auditEndpoint(['status' => Work::STATUS_DRAFT]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_unknown_query_parameter_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson($this->auditEndpoint(['unexpected' => 'value']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unexpected');
    }

    public function test_unauthenticated_audit_request_returns_401(): void
    {
        $this->getJson($this->auditEndpoint())->assertUnauthorized();
    }

    public function test_admin_and_staff_without_required_permissions_return_403(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role);
            $this->getJson($this->auditEndpoint())->assertForbidden();

            $this->actingAsRole($role, [
                'admin.works.access',
                'admin.works.activity.view',
            ]);
            $this->getJson($this->auditEndpoint())->assertForbidden();
        }
    }

    public function test_admin_and_staff_with_required_permissions_can_read_audit_activity(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, $this->activityPermissions());

            $this->getJson($this->auditEndpoint())
                ->assertOk()
                ->assertJsonPath('data.activity_source.mode', 'audit');
        }
    }

    public function test_client_and_designer_remain_forbidden_with_accidental_permissions(): void
    {
        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $this->activityPermissions());
            $this->getJson($this->auditEndpoint())->assertForbidden();
        }
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createAuditEvent(array $overrides = []): AuditEvent
    {
        return AuditEvent::query()->create(array_merge([
            'event_type' => 'works.review.started',
            'category' => 'works',
            'severity' => 'notice',
            'actor_type' => 'user',
            'actor_id' => null,
            'actor_role' => null,
            'target_type' => 'work',
            'target_id' => 999_999,
            'action' => 'start',
            'outcome' => 'success',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Works activity audit API test',
            'request_id' => 'private-request',
            'correlation_id' => 'private-correlation',
            'metadata' => ['private_notes' => 'not exposed'],
            'occurred_at' => Carbon::parse('2026-07-18 12:00:00'),
        ], $overrides));
    }

    /**
     * @param array<string, bool|int|string> $parameters
     */
    private function auditEndpoint(array $parameters = []): string
    {
        return $this->endpoint([
            'source' => 'audit',
            ...$parameters,
        ]);
    }

    /**
     * @param array<string, bool|int|string> $parameters
     */
    private function endpoint(array $parameters): string
    {
        return '/api/admin/works/activity?'.http_build_query($parameters);
    }

    /**
     * @return list<string>
     */
    private function activityPermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.activity.view',
            'admin.works.activity.list',
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
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private function sortedKeys(array $payload): array
    {
        $keys = array_keys($payload);
        sort($keys);

        return $keys;
    }

    /**
     * @param array<string, mixed> $payload
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
