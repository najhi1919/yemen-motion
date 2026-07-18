<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkReport;
use App\Services\Works\WorksActivityAuditQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorksActivityAuditFoundationTest extends TestCase
{
    use RefreshDatabase;

    private WorksActivityAuditQuery $activityQuery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activityQuery = app(WorksActivityAuditQuery::class);
    }

    public function test_it_reads_only_supported_events_in_the_works_category(): void
    {
        $first = $this->createAuditEvent(['event_type' => 'works.review.started']);
        $second = $this->createAuditEvent(['event_type' => 'works.visibility.published']);

        $this->assertSame(
            [$second->id, $first->id],
            $this->activityQuery->query()->pluck('audit_event_id')->all(),
        );
    }

    public function test_it_excludes_other_categories_and_unknown_event_types(): void
    {
        $included = $this->createAuditEvent(['event_type' => 'works.review.approved']);
        $this->createAuditEvent([
            'event_type' => 'works.review.approved',
            'category' => 'security',
        ]);
        $this->createAuditEvent(['event_type' => 'works.future.unknown']);

        $this->assertSame(
            [$included->id],
            $this->activityQuery->query()->pluck('audit_event_id')->all(),
        );
    }

    public function test_it_requires_supported_event_types_to_match_their_defined_target_scope(): void
    {
        $validEvents = [
            $this->createAuditEvent([
                'event_type' => 'works.review.approved',
                'target_type' => 'work',
            ]),
            $this->createAuditEvent([
                'event_type' => 'works.reports.archived',
                'target_type' => 'work_report',
            ]),
            $this->createAuditEvent([
                'event_type' => 'works.taxonomy.category.created',
                'target_type' => 'work_category',
            ]),
            $this->createAuditEvent([
                'event_type' => 'works.taxonomy.tag.updated',
                'target_type' => 'work_tag',
            ]),
            $this->createAuditEvent([
                'event_type' => 'work.tags.updated',
                'target_type' => 'work',
            ]),
        ];

        $invalidEvents = [
            $this->createAuditEvent([
                'event_type' => 'works.review.approved',
                'target_type' => 'work_tag',
            ]),
            $this->createAuditEvent([
                'event_type' => 'works.reports.archived',
                'target_type' => 'work',
            ]),
            $this->createAuditEvent([
                'event_type' => 'works.taxonomy.category.created',
                'target_type' => 'work',
            ]),
            $this->createAuditEvent([
                'event_type' => 'works.taxonomy.tag.updated',
                'target_type' => 'work_category',
            ]),
            $this->createAuditEvent([
                'event_type' => 'work.tags.updated',
                'target_type' => 'work_report',
            ]),
        ];

        $returnedIds = $this->activityQuery->query()
            ->pluck('audit_event_id')
            ->sort()
            ->values()
            ->all();
        $validIds = collect($validEvents)->pluck('id')->sort()->values()->all();
        $invalidIds = collect($invalidEvents)->pluck('id')->all();

        $this->assertSame($validIds, $returnedIds);

        foreach ($invalidIds as $invalidId) {
            $this->assertNotContains($invalidId, $returnedIds);
        }
    }

    public function test_review_events_have_normalized_definitions(): void
    {
        $expectedTypes = [
            'works.review.started',
            'works.review.reviewer_assigned',
            'works.review.approved',
            'works.review.changes_requested',
            'works.review.rejected',
            'works.review.published',
            'works.review.reopened',
        ];

        $this->assertSame($expectedTypes, $this->eventTypesForGroup('review'));

        $event = $this->createAuditEvent(['event_type' => 'works.review.changes_requested']);
        $row = $this->activityQuery->query(['event_type' => $event->event_type])->sole();

        $this->assertSame('changes_requested', $row->event_key);
        $this->assertSame('review', $row->event_group);
        $this->assertSame('طلب تعديلات على العمل', $row->event_label_ar);
        $this->assertSame('Work changes requested', $row->event_label_en);
        $this->assertSame('work', $row->target_scope);
        $this->assertTrue((bool) $row->requires_work);
        $this->assertTrue((bool) $row->needs_attention);
    }

    public function test_visibility_events_have_normalized_definitions(): void
    {
        $expectedTypes = [
            'works.visibility.published',
            'works.visibility.unpublished',
            'works.visibility.hidden',
            'works.visibility.restored',
            'works.visibility.featured',
            'works.visibility.unfeatured',
            'works.visibility.pinned',
            'works.visibility.unpinned',
        ];

        $this->assertSame($expectedTypes, $this->eventTypesForGroup('visibility'));

        $event = $this->createAuditEvent(['event_type' => 'works.visibility.hidden']);
        $row = $this->activityQuery->query(['event_type' => $event->event_type])->sole();

        $this->assertSame('hidden', $row->event_key);
        $this->assertSame('visibility', $row->event_group);
        $this->assertSame('إخفاء العمل', $row->event_label_ar);
        $this->assertSame('Work hidden', $row->event_label_en);
        $this->assertTrue((bool) $row->needs_attention);
    }

    public function test_report_events_have_normalized_definitions(): void
    {
        $expectedTypes = [
            'works.reports.review_started',
            'works.reports.dismissed',
            'works.reports.archived',
        ];

        $this->assertSame($expectedTypes, $this->eventTypesForGroup('reports'));

        $event = $this->createAuditEvent([
            'event_type' => 'works.reports.review_started',
            'target_type' => 'work_report',
        ]);
        $row = $this->activityQuery->query(['event_type' => $event->event_type])->sole();

        $this->assertSame('review_started', $row->event_key);
        $this->assertSame('reports', $row->event_group);
        $this->assertSame('بدء مراجعة بلاغ العمل', $row->event_label_ar);
        $this->assertSame('work_report', $row->target_scope);
        $this->assertTrue((bool) $row->requires_work);
    }

    public function test_taxonomy_and_assignment_events_have_normalized_definitions(): void
    {
        $this->assertSame([
            'works.taxonomy.category.created',
            'works.taxonomy.category.updated',
            'works.taxonomy.category.disabled',
            'works.taxonomy.tag.created',
            'works.taxonomy.tag.updated',
            'works.taxonomy.tag.disabled',
            'works.taxonomy.tags.merged',
        ], $this->eventTypesForGroup('taxonomy'));
        $this->assertSame([
            'work.category.changed',
            'work.tags.updated',
        ], $this->eventTypesForGroup('taxonomy_assignment'));

        $taxonomy = $this->createAuditEvent([
            'event_type' => 'works.taxonomy.tags.merged',
            'target_type' => 'work_tag',
        ]);
        $assignment = $this->createAuditEvent([
            'event_type' => 'work.tags.updated',
            'target_type' => 'work',
        ]);
        $rows = $this->activityQuery->query()
            ->get()
            ->keyBy('audit_event_id');

        $this->assertSame('taxonomy', $rows[$taxonomy->id]->event_group);
        $this->assertSame('tags_merged', $rows[$taxonomy->id]->event_key);
        $this->assertFalse((bool) $rows[$taxonomy->id]->requires_work);
        $this->assertSame('taxonomy_assignment', $rows[$assignment->id]->event_group);
        $this->assertSame('tags_updated', $rows[$assignment->id]->event_key);
        $this->assertTrue((bool) $rows[$assignment->id]->requires_work);
    }

    public function test_work_targets_resolve_work_id_and_current_snapshot(): void
    {
        $work = Work::factory()->create([
            'designer_id' => null,
            'title' => 'Direct audit work',
            'slug' => 'direct-audit-work',
            'status' => Work::STATUS_APPROVED,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'media_type' => 'gallery',
        ]);
        $event = $this->createAuditEvent([
            'event_type' => 'works.review.approved',
            'target_type' => 'work',
            'target_id' => $work->id,
        ]);

        $row = $this->activityQuery->query()->sole();

        $this->assertSame($event->id, $row->audit_event_id);
        $this->assertSame($work->id, $row->work_id);
        $this->assertSame('Direct audit work', $row->work_title);
        $this->assertSame('direct-audit-work', $row->work_slug);
        $this->assertSame(Work::STATUS_APPROVED, $row->work_status);
        $this->assertSame(Work::VISIBILITY_HIDDEN, $row->work_visibility_status);
        $this->assertSame('gallery', $row->work_media_type);
    }

    public function test_work_report_targets_resolve_work_through_the_report(): void
    {
        $work = Work::factory()->create([
            'designer_id' => null,
            'title' => 'Reported audit work',
            'slug' => 'reported-audit-work',
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

        $row = $this->activityQuery->query()->sole();

        $this->assertSame($event->id, $row->audit_event_id);
        $this->assertSame($report->id, $row->target_id);
        $this->assertSame($work->id, $row->work_id);
        $this->assertSame('Reported audit work', $row->work_title);
        $this->assertSame('reported-audit-work', $row->work_slug);
    }

    public function test_taxonomy_targets_are_retained_without_a_work_id(): void
    {
        $category = $this->createAuditEvent([
            'event_type' => 'works.taxonomy.category.created',
            'target_type' => 'work_category',
            'target_id' => 701,
        ]);
        $tag = $this->createAuditEvent([
            'event_type' => 'works.taxonomy.tag.updated',
            'target_type' => 'work_tag',
            'target_id' => 702,
        ]);
        $rows = $this->activityQuery->query()
            ->get()
            ->keyBy('audit_event_id');

        $this->assertSame('work_category', $rows[$category->id]->target_type);
        $this->assertSame(701, $rows[$category->id]->target_id);
        $this->assertNull($rows[$category->id]->work_id);
        $this->assertSame('work_tag', $rows[$tag->id]->target_type);
        $this->assertSame(702, $rows[$tag->id]->target_id);
        $this->assertNull($rows[$tag->id]->work_id);
    }

    public function test_existing_actor_resolves_actor_name(): void
    {
        $actor = User::factory()->create(['name' => 'Audit Actor']);
        $this->createAuditEvent([
            'actor_id' => $actor->id,
            'actor_role' => 'admin',
        ]);

        $row = $this->activityQuery->query()->sole();

        $this->assertSame($actor->id, $row->actor_id);
        $this->assertSame('Audit Actor', $row->actor_name);
        $this->assertSame('admin', $row->actor_role);
    }

    public function test_missing_actor_or_target_does_not_remove_the_audit_event(): void
    {
        $actor = User::factory()->create();
        $work = Work::factory()->create(['designer_id' => null]);
        $report = WorkReport::factory()->create([
            'work_id' => $work->id,
            'reporter_id' => null,
        ]);
        $event = $this->createAuditEvent([
            'event_type' => 'works.reports.archived',
            'actor_id' => $actor->id,
            'target_type' => 'work_report',
            'target_id' => $report->id,
        ]);

        $actor->delete();
        $report->delete();

        $row = $this->activityQuery->query()->sole();

        $this->assertSame($event->id, $row->audit_event_id);
        $this->assertSame($actor->id, $row->actor_id);
        $this->assertNull($row->actor_name);
        $this->assertSame('work_report', $row->target_type);
        $this->assertSame($report->id, $row->target_id);
        $this->assertNull($row->work_id);
        $this->assertNull($row->work_title);
    }

    public function test_contract_does_not_expose_raw_metadata_or_sensitive_fields(): void
    {
        $this->createAuditEvent([
            'ip_address' => '192.0.2.10',
            'user_agent' => 'Sensitive audit agent',
            'request_id' => 'private-request-id',
            'correlation_id' => 'private-correlation-id',
            'metadata' => [
                'rejection_reason' => 'private rejection reason',
                'change_request_notes' => 'private change request',
                'private_notes' => 'internal only',
                'reporter_email' => 'reporter@example.test',
            ],
        ]);

        $payload = (array) $this->activityQuery->query()->sole();
        $keys = array_keys($payload);
        sort($keys);

        $this->assertSame([
            'action',
            'actor_id',
            'actor_name',
            'actor_role',
            'audit_event_id',
            'event_group',
            'event_key',
            'event_label_ar',
            'event_label_en',
            'event_type',
            'needs_attention',
            'occurred_at',
            'outcome',
            'requires_work',
            'severity',
            'source',
            'target_id',
            'target_scope',
            'target_type',
            'work_id',
            'work_media_type',
            'work_slug',
            'work_status',
            'work_title',
            'work_visibility_status',
        ], $keys);
        $this->assertSame('audit_events', $payload['source']);

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
        ] as $sensitiveKey) {
            $this->assertArrayNotHasKey($sensitiveKey, $payload);
        }
    }

    public function test_supported_filters_are_applied_and_unknown_filters_are_ignored(): void
    {
        $firstActor = User::factory()->create(['name' => 'First Audit Actor']);
        $secondActor = User::factory()->create(['name' => 'Second Audit Actor']);
        $firstWork = Work::factory()->create([
            'designer_id' => null,
            'title' => 'Searchable Alpha Work',
            'slug' => 'searchable-alpha-work',
        ]);
        $secondWork = Work::factory()->create([
            'designer_id' => null,
            'title' => 'Beta Work',
            'slug' => 'unique-beta-slug',
        ]);
        $report = WorkReport::factory()->create([
            'work_id' => $firstWork->id,
            'reporter_id' => null,
        ]);
        $review = $this->createAuditEvent([
            'event_type' => 'works.review.started',
            'actor_id' => $firstActor->id,
            'target_type' => 'work',
            'target_id' => $firstWork->id,
            'action' => 'start',
            'outcome' => 'success',
            'occurred_at' => '2026-07-18 10:00:00',
            'metadata' => ['private_notes' => 'metadata-only-token'],
        ]);
        $visibility = $this->createAuditEvent([
            'event_type' => 'works.visibility.hidden',
            'actor_id' => $secondActor->id,
            'target_type' => 'work',
            'target_id' => $secondWork->id,
            'action' => 'hide',
            'outcome' => 'failure',
            'occurred_at' => '2026-07-18 11:00:00',
        ]);
        $reportEvent = $this->createAuditEvent([
            'event_type' => 'works.reports.review_started',
            'actor_id' => $firstActor->id,
            'target_type' => 'work_report',
            'target_id' => $report->id,
            'action' => 'review',
            'outcome' => 'success',
            'occurred_at' => '2026-07-18 12:00:00',
        ]);

        $this->assertQueryIds([$review->id], ['event_type' => 'works.review.started']);
        $this->assertQueryIds([$review->id], ['event_group' => 'review']);
        $this->assertQueryIds([$reportEvent->id, $review->id], ['actor_id' => $firstActor->id]);
        $this->assertQueryIds([$reportEvent->id], ['target_type' => 'work_report']);
        $this->assertQueryIds([$visibility->id], ['target_id' => $secondWork->id]);
        $this->assertQueryIds([$reportEvent->id, $review->id], ['work_id' => $firstWork->id]);
        $this->assertQueryIds([$review->id], ['q' => 'works.review.started']);
        $this->assertQueryIds([$visibility->id], ['q' => 'hide']);
        $this->assertQueryIds([$reportEvent->id, $review->id], ['q' => 'First Audit Actor']);
        $this->assertQueryIds([$reportEvent->id, $review->id], ['q' => 'Searchable Alpha']);
        $this->assertQueryIds([$visibility->id], ['q' => 'unique-beta-slug']);
        $this->assertQueryIds([], ['q' => 'metadata-only-token']);
        $this->assertQueryIds(
            [$visibility->id, $review->id],
            ['from' => '2026-07-18 10:00:00', 'to' => '2026-07-18 11:00:00'],
        );
        $this->assertQueryIds([$visibility->id], ['outcome' => 'failure']);
        $this->assertQueryIds(
            [$reportEvent->id, $visibility->id, $review->id],
            ['unknown_filter' => 'ignored'],
        );
    }

    public function test_default_order_is_deterministic_by_time_then_audit_event_id(): void
    {
        $older = $this->createAuditEvent(['occurred_at' => '2026-07-18 09:00:00']);
        $firstAtSameTime = $this->createAuditEvent(['occurred_at' => '2026-07-18 10:00:00']);
        $secondAtSameTime = $this->createAuditEvent(['occurred_at' => '2026-07-18 10:00:00']);

        $this->assertSame(
            [$secondAtSameTime->id, $firstAtSameTime->id, $older->id],
            $this->activityQuery->query()->pluck('audit_event_id')->all(),
        );
        $this->assertSame(
            [$older->id, $firstAtSameTime->id, $secondAtSameTime->id],
            $this->activityQuery->query([], 'asc')->pluck('audit_event_id')->all(),
        );
        $this->assertSame(
            [$secondAtSameTime->id, $firstAtSameTime->id, $older->id],
            $this->activityQuery->query([], 'unsupported')->pluck('audit_event_id')->all(),
        );
    }

    public function test_joins_do_not_duplicate_audit_event_rows(): void
    {
        $work = Work::factory()->create(['designer_id' => null]);
        $reports = WorkReport::factory()->count(2)->create([
            'work_id' => $work->id,
            'reporter_id' => null,
        ]);
        $first = $this->createAuditEvent([
            'event_type' => 'works.reports.review_started',
            'target_type' => 'work_report',
            'target_id' => $reports[0]->id,
        ]);
        $second = $this->createAuditEvent([
            'event_type' => 'works.reports.dismissed',
            'target_type' => 'work_report',
            'target_id' => $reports[1]->id,
        ]);

        $rows = $this->activityQuery->query()->get();

        $this->assertCount(2, $rows);
        $this->assertSame(
            [$first->id, $second->id],
            $rows->pluck('audit_event_id')->sort()->values()->all(),
        );
        $this->assertSame(2, $rows->pluck('audit_event_id')->unique()->count());
    }

    public function test_registry_contains_exactly_event_types_written_by_current_works_controllers(): void
    {
        $expectedTypes = [
            'works.review.started',
            'works.review.reviewer_assigned',
            'works.review.approved',
            'works.review.changes_requested',
            'works.review.rejected',
            'works.review.published',
            'works.review.reopened',
            'works.visibility.published',
            'works.visibility.unpublished',
            'works.visibility.hidden',
            'works.visibility.restored',
            'works.visibility.featured',
            'works.visibility.unfeatured',
            'works.visibility.pinned',
            'works.visibility.unpinned',
            'works.reports.review_started',
            'works.reports.dismissed',
            'works.reports.archived',
            'works.taxonomy.category.created',
            'works.taxonomy.category.updated',
            'works.taxonomy.category.disabled',
            'works.taxonomy.tag.created',
            'works.taxonomy.tag.updated',
            'works.taxonomy.tag.disabled',
            'works.taxonomy.tags.merged',
            'work.category.changed',
            'work.tags.updated',
        ];

        $this->assertSame($expectedTypes, $this->activityQuery->supportedEventTypes());
        $this->assertCount(27, $this->activityQuery->definitions());

        foreach ($this->activityQuery->definitions() as $eventType => $definition) {
            $this->assertSame($eventType, $definition['event_type']);
            $this->assertContains($definition['event_group'], [
                'review',
                'visibility',
                'reports',
                'taxonomy',
                'taxonomy_assignment',
            ]);
            $this->assertNotSame('', $definition['event_key']);
            $this->assertNotSame('', $definition['label_ar']);
            $this->assertNotSame('', $definition['label_en']);
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
            'user_agent' => 'Works activity audit foundation test',
            'request_id' => 'private-request',
            'correlation_id' => 'private-correlation',
            'metadata' => ['private_notes' => 'not exposed'],
            'occurred_at' => Carbon::parse('2026-07-18 12:00:00'),
        ], $overrides));
    }

    /**
     * @return list<string>
     */
    private function eventTypesForGroup(string $group): array
    {
        return array_values(array_map(
            static fn (array $definition): string => $definition['event_type'],
            array_filter(
                $this->activityQuery->definitions(),
                static fn (array $definition): bool => $definition['event_group'] === $group,
            ),
        ));
    }

    /**
     * @param list<int> $expectedIds
     * @param array<string, mixed> $filters
     */
    private function assertQueryIds(array $expectedIds, array $filters): void
    {
        $this->assertSame(
            $expectedIds,
            $this->activityQuery->query($filters)->pluck('audit_event_id')->all(),
        );
    }
}
