<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksActivityController;
use App\Models\User;
use App\Models\Work;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorksActivityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/admin/works/activity')
            ->assertUnauthorized();
    }

    public function test_super_admin_can_list_activity(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['designer_id' => null]);

        $this->getJson('/api/admin/works/activity')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_admin_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/admin/works/activity')
            ->assertForbidden();
    }

    public function test_staff_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/admin/works/activity')
            ->assertForbidden();
    }

    public function test_admin_with_access_only_gets_403(): void
    {
        $this->actingAsRole('admin', ['admin.works.access']);

        $this->getJson('/api/admin/works/activity')
            ->assertForbidden();
    }

    public function test_admin_with_access_and_activity_view_only_gets_403(): void
    {
        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.activity.view',
        ]);

        $this->getJson('/api/admin/works/activity')
            ->assertForbidden();
    }

    public function test_admin_and_staff_with_required_permissions_can_list(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, $this->activityPermissions());

            $this->getJson('/api/admin/works/activity')
                ->assertOk()
                ->assertJsonPath('success', true);
        }
    }

    public function test_client_and_designer_with_accidental_permissions_get_403(): void
    {
        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $this->activityPermissions());

            $this->getJson('/api/admin/works/activity')
                ->assertForbidden();
        }
    }

    public function test_generated_events_come_from_lifecycle_timestamps(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create([
            'designer_id' => null,
            'reviewer_id' => null,
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-02 10:00:00',
            'submitted_at' => '2026-01-03 10:00:00',
            'reviewed_at' => '2026-01-04 10:00:00',
            'approved_at' => '2026-01-05 10:00:00',
            'published_at' => '2026-01-06 10:00:00',
            'rejected_at' => '2026-01-07 10:00:00',
            'hidden_at' => '2026-01-08 10:00:00',
            'archived_at' => '2026-01-09 10:00:00',
        ])->refresh();

        $response = $this->getJson($this->endpoint([
            'sort' => 'event_at',
            'direction' => 'asc',
            'per_page' => 50,
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 9)
            ->assertJsonCount(9, 'data.items');

        $items = collect($response->json('data.items'))->keyBy('event_type');
        $events = [
            'created' => ['created_at', 'إنشاء العمل'],
            'updated' => ['updated_at', 'تحديث العمل'],
            'submitted' => ['submitted_at', 'إرسال للمراجعة'],
            'reviewed' => ['reviewed_at', 'مراجعة العمل'],
            'approved' => ['approved_at', 'اعتماد العمل'],
            'published' => ['published_at', 'نشر العمل'],
            'rejected' => ['rejected_at', 'رفض العمل'],
            'hidden' => ['hidden_at', 'إخفاء العمل'],
            'archived' => ['archived_at', 'أرشفة العمل'],
        ];

        $this->assertSame(array_keys($events), $response->json('data.items.*.event_type'));

        foreach ($events as $eventType => [$timestampColumn, $label]) {
            $item = $items->get($eventType);

            $this->assertNotNull($item);
            $this->assertSame('work-'.$work->id.'-'.$eventType, $item['id']);
            $this->assertSame($work->id, $item['work_id']);
            $this->assertSame($label, $item['event_label']);
            $this->assertTrue(
                Carbon::parse($work->{$timestampColumn})->equalTo(Carbon::parse($item['event_at'])),
            );
        }
    }

    public function test_no_event_is_generated_for_null_lifecycle_timestamps(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create([
            'designer_id' => null,
            'reviewer_id' => null,
            'submitted_at' => null,
            'reviewed_at' => null,
            'approved_at' => null,
            'published_at' => null,
            'rejected_at' => null,
            'hidden_at' => null,
            'archived_at' => null,
        ]);
        $allNullWork = Work::factory()->create();

        DB::table('works')
            ->where('id', $allNullWork->id)
            ->update([
                'created_at' => null,
                'updated_at' => null,
                'submitted_at' => null,
                'reviewed_at' => null,
                'approved_at' => null,
                'published_at' => null,
                'rejected_at' => null,
                'hidden_at' => null,
                'archived_at' => null,
            ]);

        $response = $this->getJson('/api/admin/works/activity?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2);

        $this->assertSame(
            ['created', 'updated'],
            collect($response->json('data.items'))
                ->where('work_id', $work->id)
                ->pluck('event_type')
                ->sort()
                ->values()
                ->all(),
        );
        $this->assertFalse(
            collect($response->json('data.items'))->contains('work_id', $allNullWork->id),
        );
    }

    public function test_response_uses_safe_paginated_summary_filter_and_activity_source_shape(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create(['name' => 'Activity Designer']);
        $reviewer = User::factory()->create(['name' => 'Activity Reviewer']);
        $work = Work::factory()->published()->featured()->create([
            'title' => 'Published Activity Work',
            'slug' => 'published-activity-work',
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
            'category_id' => 8,
            'media_type' => 'image',
            'reports_count' => 3,
            'views_count' => 12,
            'likes_count' => 4,
        ]);

        $response = $this->getJson('/api/admin/works/activity')
            ->assertOk()
            ->assertJsonPath('message', 'تم جلب سجل الأعمال بنجاح')
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.pagination', [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 6,
                'last_page' => 1,
            ])
            ->assertJsonPath('data.filters', [
                'q' => null,
                'event_type' => null,
                'status' => null,
                'visibility_status' => null,
                'media_type' => null,
                'designer_id' => null,
                'reviewer_id' => null,
                'category_id' => null,
                'reported' => null,
                'promoted' => null,
                'from' => null,
                'to' => null,
                'sort' => 'event_at',
                'direction' => 'desc',
            ])
            ->assertJsonPath('data.activity_source', [
                'dedicated_log_available' => false,
                'source' => 'work_lifecycle_timestamps',
                'mode' => 'lifecycle',
                'reason' => 'لا يوجد جدول سجل أعمال مستقل حاليًا؛ هذه القائمة مشتقة من تواريخ دورة حياة الأعمال.',
            ]);

        $item = collect($response->json('data.items'))->firstWhere('event_type', 'published');

        $this->assertNotNull($item);
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
        ], collect(array_keys($item))->sort()->values()->all());
        $this->assertSame([
            'is_promoted',
            'is_reported',
            'is_review_event',
            'is_visibility_event',
            'needs_attention',
        ], collect(array_keys($item['activity_flags']))->sort()->values()->all());
        $this->assertSame(
            ['activity_source', 'filters', 'items', 'pagination', 'summary'],
            collect(array_keys($response->json('data')))->sort()->values()->all(),
        );
        $this->assertSame($work->id, $item['work_id']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/',
            $item['event_at'],
        );
    }

    public function test_response_does_not_expose_sensitive_fields_or_raw_work_rows(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create([
            'name' => 'Visible Activity Designer',
            'email' => 'private-activity-designer@example.test',
            'password' => 'private-activity-password-marker',
        ]);
        Work::factory()->create([
            'designer_id' => $designer->id,
            'summary' => 'private-activity-summary-marker',
            'description' => 'private-activity-description-marker',
            'internal_notes' => 'private-activity-internal-marker',
            'rejection_reason' => 'private-activity-rejection-marker',
            'change_request_notes' => 'private-activity-change-marker',
        ]);

        $response = $this->getJson('/api/admin/works/activity')
            ->assertOk();
        $keys = $this->recursiveKeys($response->json());
        $json = strtolower($response->getContent());

        foreach ([
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
            'raw_model',
            'rows',
            'work',
            'works',
            'user',
            'users',
        ] as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $keys);
        }

        foreach ([
            'private-activity-designer@example.test',
            'private-activity-password-marker',
            'private-activity-summary-marker',
            'private-activity-description-marker',
            'private-activity-internal-marker',
            'private-activity-rejection-marker',
            'private-activity-change-marker',
        ] as $forbiddenValue) {
            $this->assertStringNotContainsString($forbiddenValue, $json);
        }
    }

    public function test_designer_and_reviewer_expose_only_id_and_name(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create([
            'name' => 'Limited Activity Designer',
            'email' => 'limited-activity-designer@example.test',
        ]);
        $reviewer = User::factory()->create([
            'name' => 'Limited Activity Reviewer',
            'email' => 'limited-activity-reviewer@example.test',
        ]);
        Work::factory()->create([
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
        ]);

        $response = $this->getJson($this->endpoint(['event_type' => 'created']))
            ->assertOk()
            ->assertJsonPath('data.items.0.designer', [
                'id' => $designer->id,
                'name' => $designer->name,
            ])
            ->assertJsonPath('data.items.0.reviewer', [
                'id' => $reviewer->id,
                'name' => $reviewer->name,
            ]);

        $this->assertSame(['id', 'name'], array_keys($response->json('data.items.0.designer')));
        $this->assertSame(['id', 'name'], array_keys($response->json('data.items.0.reviewer')));
        $this->assertStringNotContainsString($designer->email, $response->getContent());
        $this->assertStringNotContainsString($reviewer->email, $response->getContent());
    }

    public function test_missing_designer_and_reviewer_are_returned_as_null(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create([
            'designer_id' => null,
            'reviewer_id' => null,
        ]);

        $this->getJson($this->endpoint(['event_type' => 'created']))
            ->assertOk()
            ->assertJsonPath('data.items.0.designer', null)
            ->assertJsonPath('data.items.0.reviewer', null);
    }

    public function test_search_matches_title_and_slug_only(): void
    {
        $this->actingAsRole('super-admin');
        $titleWork = Work::factory()->create([
            'title' => 'Nebula Activity Work',
            'slug' => 'ordinary-activity-title',
        ]);
        $slugWork = Work::factory()->create([
            'title' => 'Ordinary Activity Slug',
            'slug' => 'kinetic-activity-target',
        ]);

        foreach ([
            'Nebula' => $titleWork,
            'activity-target' => $slugWork,
        ] as $query => $expected) {
            $this->assertSingleCreatedEvent(['q' => $query], $expected);

            $this->getJson($this->endpoint([
                'q' => $query,
                'event_type' => 'created',
            ]))
                ->assertOk()
                ->assertJsonPath('data.filters.q', $query);
        }
    }

    public function test_search_does_not_match_summary_description_private_notes_or_user_fields(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create([
            'name' => 'activity-user-name-secret',
            'email' => 'activity-email-secret@example.test',
        ]);
        Work::factory()->create([
            'designer_id' => $designer->id,
            'title' => 'Public Activity Title',
            'slug' => 'public-activity-title',
            'summary' => 'activity-summary-secret',
            'description' => 'activity-description-secret',
            'internal_notes' => 'activity-internal-secret',
            'rejection_reason' => 'activity-rejection-secret',
            'change_request_notes' => 'activity-change-secret',
        ]);

        foreach ([
            'activity-summary-secret',
            'activity-description-secret',
            'activity-internal-secret',
            'activity-rejection-secret',
            'activity-change-secret',
            'activity-user-name-secret',
            'activity-email-secret',
        ] as $query) {
            $this->getJson($this->endpoint([
                'q' => $query,
                'event_type' => 'created',
            ]))
                ->assertOk()
                ->assertJsonPath('data.pagination.total', 0)
                ->assertJsonPath('data.summary.total_events', 0)
                ->assertJsonPath('data.items', []);
        }
    }

    public function test_short_search_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/activity?q=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_event_type_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->published()->create();

        $this->getJson($this->endpoint(['event_type' => 'published']))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.summary.total_events', 1)
            ->assertJsonPath('data.items.0.id', 'work-'.$work->id.'-published')
            ->assertJsonPath('data.items.0.event_type', 'published')
            ->assertJsonPath('data.filters.event_type', 'published');
    }

    public function test_status_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->published()->create();
        Work::factory()->submitted()->create();

        $this->assertSingleCreatedEvent(['status' => Work::STATUS_PUBLISHED], $expected);
    }

    public function test_visibility_status_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->published()->create();
        Work::factory()->submitted()->create();

        $this->assertSingleCreatedEvent([
            'visibility_status' => Work::VISIBILITY_PUBLIC,
        ], $expected);
    }

    public function test_media_type_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->create(['media_type' => 'video']);
        Work::factory()->create(['media_type' => 'image']);

        $this->assertSingleCreatedEvent(['media_type' => 'video'], $expected);
    }

    public function test_designer_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create();
        $otherDesigner = User::factory()->create();
        $expected = Work::factory()->create(['designer_id' => $designer->id]);
        Work::factory()->create(['designer_id' => $otherDesigner->id]);

        $this->assertSingleCreatedEvent(['designer_id' => $designer->id], $expected);
    }

    public function test_reviewer_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $reviewer = User::factory()->create();
        $otherReviewer = User::factory()->create();
        $expected = Work::factory()->create(['reviewer_id' => $reviewer->id]);
        Work::factory()->create(['reviewer_id' => $otherReviewer->id]);

        $this->assertSingleCreatedEvent(['reviewer_id' => $reviewer->id], $expected);
    }

    public function test_category_id_filter_works(): void
    {
        $this->actingAsRole('super-admin');
        $expected = Work::factory()->create(['category_id' => 18]);
        Work::factory()->create(['category_id' => 27]);

        $this->assertSingleCreatedEvent(['category_id' => 18], $expected);
    }

    public function test_reported_true_and_false_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        $reported = Work::factory()->create(['reports_count' => 3]);
        $notReported = Work::factory()->create(['reports_count' => 0]);

        $this->assertSingleCreatedEvent(['reported' => 1], $reported);
        $this->assertSingleCreatedEvent(['reported' => 0], $notReported);

        $this->getJson($this->endpoint([
            'reported' => 1,
            'event_type' => 'created',
        ]))
            ->assertOk()
            ->assertJsonPath('data.filters.reported', true);
        $this->getJson($this->endpoint([
            'reported' => 0,
            'event_type' => 'created',
        ]))
            ->assertOk()
            ->assertJsonPath('data.filters.reported', false);
    }

    public function test_promoted_true_and_false_filters_work(): void
    {
        $this->actingAsRole('super-admin');
        $featured = Work::factory()->featured()->create();
        $pinned = Work::factory()->pinned()->create();
        $plain = Work::factory()->create();

        $promoted = $this->getJson($this->endpoint([
            'promoted' => 1,
            'event_type' => 'created',
            'per_page' => 50,
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.summary.total_events', 2)
            ->assertJsonPath('data.filters.promoted', true);

        $this->assertSame(
            [$featured->id, $pinned->id],
            collect($promoted->json('data.items'))->pluck('work_id')->sort()->values()->all(),
        );

        $this->assertSingleCreatedEvent(['promoted' => 0], $plain);
        $this->getJson($this->endpoint([
            'promoted' => 0,
            'event_type' => 'created',
        ]))
            ->assertOk()
            ->assertJsonPath('data.filters.promoted', false);
    }

    public function test_from_and_to_filter_event_at(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['created_at' => '2026-06-30 23:59:59']);
        $expected = Work::factory()->create(['created_at' => '2026-07-15 10:00:00']);
        Work::factory()->create(['created_at' => '2026-08-01 00:00:00']);

        $this->assertSingleCreatedEvent([
            'from' => '2026-07-01',
            'to' => '2026-07-31',
        ], $expected);
    }

    public function test_default_sort_is_event_at_desc(): void
    {
        $this->actingAsRole('super-admin');
        $older = Work::factory()->create(['created_at' => '2026-07-01 10:00:00']);
        $middle = Work::factory()->create(['created_at' => '2026-07-10 10:00:00']);
        $newer = Work::factory()->create(['created_at' => '2026-07-15 10:00:00']);

        $response = $this->getJson($this->endpoint([
            'event_type' => 'created',
            'per_page' => 50,
        ]))
            ->assertOk()
            ->assertJsonPath('data.filters.sort', 'event_at')
            ->assertJsonPath('data.filters.direction', 'desc');

        $this->assertSame(
            [$newer->id, $middle->id, $older->id],
            collect($response->json('data.items'))->pluck('work_id')->all(),
        );
    }

    public function test_event_at_asc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $older = Work::factory()->create(['created_at' => '2026-07-01 10:00:00']);
        $middle = Work::factory()->create(['created_at' => '2026-07-10 10:00:00']);
        $newer = Work::factory()->create(['created_at' => '2026-07-15 10:00:00']);

        $this->assertSortedWorkIds(
            'event_at',
            'asc',
            [$older->id, $middle->id, $newer->id],
        );
    }

    public function test_work_id_asc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $first = Work::factory()->create();
        $second = Work::factory()->create();
        $third = Work::factory()->create();

        $this->assertSortedWorkIds(
            'work_id',
            'asc',
            [$first->id, $second->id, $third->id],
        );
    }

    public function test_title_asc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->create(['title' => 'Bravo Activity']);
        Work::factory()->create(['title' => 'Alpha Activity']);
        Work::factory()->create(['title' => 'Charlie Activity']);

        $response = $this->getJson($this->endpoint([
            'event_type' => 'created',
            'sort' => 'title',
            'direction' => 'asc',
            'per_page' => 50,
        ]))->assertOk();

        $this->assertSame(
            ['Alpha Activity', 'Bravo Activity', 'Charlie Activity'],
            collect($response->json('data.items'))->pluck('title')->all(),
        );
    }

    public function test_status_asc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $approved = Work::factory()->create(['status' => Work::STATUS_APPROVED]);
        $draft = Work::factory()->create(['status' => Work::STATUS_DRAFT]);
        $published = Work::factory()->create(['status' => Work::STATUS_PUBLISHED]);

        $this->assertSortedWorkIds(
            'status',
            'asc',
            [$approved->id, $draft->id, $published->id],
        );
    }

    public function test_reports_count_desc_sorting_works(): void
    {
        $this->actingAsRole('super-admin');
        $low = Work::factory()->create(['reports_count' => 1]);
        $high = Work::factory()->create(['reports_count' => 8]);
        $middle = Work::factory()->create(['reports_count' => 3]);

        $this->assertSortedWorkIds(
            'reports_count',
            'desc',
            [$high->id, $middle->id, $low->id],
        );
    }

    public function test_pagination_accepts_fifty_and_rejects_values_above_the_limit(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(55)->create(['designer_id' => null]);

        $this->getJson($this->endpoint([
            'event_type' => 'created',
            'per_page' => 50,
        ]))
            ->assertOk()
            ->assertJsonCount(50, 'data.items')
            ->assertJsonPath('data.pagination.per_page', 50)
            ->assertJsonPath('data.pagination.total', 55)
            ->assertJsonPath('data.pagination.last_page', 2);

        $this->getJson('/api/admin/works/activity?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_page_parameter_returns_the_requested_page(): void
    {
        $this->actingAsRole('super-admin');
        Work::factory()->count(5)->create(['designer_id' => null]);

        $this->getJson($this->endpoint([
            'event_type' => 'created',
            'per_page' => 2,
            'page' => 2,
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 5)
            ->assertJsonPath('data.pagination.last_page', 3);
    }

    public function test_invalid_event_type_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/activity?event_type=deleted')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_type');
    }

    public function test_invalid_status_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/activity?status=deleted')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_invalid_visibility_status_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/activity?visibility_status=private')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('visibility_status');
    }

    public function test_invalid_sort_and_direction_return_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/activity?sort=email&direction=sideways')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'direction']);
    }

    public function test_invalid_filter_types_and_missing_users_return_422(): void
    {
        $this->actingAsRole('super-admin');
        $parameters = [
            'media_type' => str_repeat('x', 41),
            'designer_id' => 999999991,
            'reviewer_id' => 999999992,
            'reported' => 'maybe',
            'promoted' => 'maybe',
            'page' => 0,
        ];

        $this->getJson($this->endpoint($parameters))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys($parameters));
    }

    public function test_to_before_from_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson($this->endpoint([
            'from' => '2026-07-15',
            'to' => '2026-07-14',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_range_over_ten_years_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson($this->endpoint([
            'from' => '2016-07-15',
            'to' => '2026-07-16',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_unknown_and_sensitive_query_parameters_return_422(): void
    {
        $this->actingAsRole('super-admin');
        $parameters = [
            'unexpected' => 'value',
            'email' => 'private@example.test',
            'password' => 'secret',
            'token' => 'secret-token',
            'cookie' => 'secret-cookie',
            'internal_notes' => 'private',
            'rejection_reason' => 'private',
            'change_request_notes' => 'private',
            'payload' => 'private',
            'metadata' => 'private',
            'description' => 'private',
            'summary' => 'private',
            'title' => 'private',
            'slug' => 'private',
        ];

        $this->getJson($this->endpoint($parameters))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys($parameters));
    }

    public function test_summary_counts_all_required_activity_fields(): void
    {
        $this->actingAsRole('super-admin');

        Work::factory()->featured()->create([
            'status' => Work::STATUS_PUBLISHED,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'reports_count' => 3,
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-02 10:00:00',
            'submitted_at' => '2026-01-03 10:00:00',
            'reviewed_at' => '2026-01-04 10:00:00',
            'approved_at' => '2026-01-05 10:00:00',
            'published_at' => '2026-01-06 10:00:00',
        ]);
        Work::factory()->create([
            'status' => Work::STATUS_REJECTED,
            'reports_count' => 0,
            'created_at' => '2026-02-01 10:00:00',
            'updated_at' => '2026-02-02 10:00:00',
            'submitted_at' => '2026-02-03 10:00:00',
            'reviewed_at' => '2026-02-04 10:00:00',
            'rejected_at' => '2026-02-05 10:00:00',
        ]);
        Work::factory()->pinned()->create([
            'status' => Work::STATUS_ARCHIVED,
            'reports_count' => 2,
            'created_at' => '2026-03-01 10:00:00',
            'updated_at' => '2026-03-02 10:00:00',
            'hidden_at' => '2026-03-03 10:00:00',
            'archived_at' => '2026-03-04 10:00:00',
        ]);

        $this->getJson('/api/admin/works/activity?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 15)
            ->assertJsonPath('data.summary', [
                'total_events' => 15,
                'unique_works' => 3,
                'created_events' => 3,
                'updated_events' => 3,
                'submitted_events' => 2,
                'reviewed_events' => 2,
                'approved_events' => 1,
                'published_events' => 1,
                'rejected_events' => 1,
                'hidden_events' => 1,
                'archived_events' => 1,
                'review_events' => 6,
                'visibility_events' => 3,
                'reported_events' => 10,
                'promoted_events' => 10,
            ]);
    }

    public function test_summary_honors_the_same_active_filters_as_items(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create();
        $reviewer = User::factory()->create();
        $expected = Work::factory()->published()->featured()->create([
            'title' => 'Summary Filter Target',
            'slug' => 'summary-filter-target',
            'media_type' => 'video',
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
            'category_id' => 44,
            'reports_count' => 5,
            'published_at' => '2026-07-15 10:00:00',
        ]);
        Work::factory()->published()->featured()->create([
            'title' => 'Other Unreported Work',
            'media_type' => 'video',
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
            'category_id' => 44,
            'reports_count' => 0,
            'published_at' => '2026-07-15 10:00:00',
        ]);
        Work::factory()->published()->featured()->create([
            'title' => 'Other Image Work',
            'media_type' => 'image',
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
            'category_id' => 44,
            'reports_count' => 4,
            'published_at' => '2026-07-15 10:00:00',
        ]);

        $response = $this->getJson($this->endpoint([
            'q' => 'Summary Filter Target',
            'event_type' => 'published',
            'status' => Work::STATUS_PUBLISHED,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'media_type' => 'video',
            'designer_id' => $designer->id,
            'reviewer_id' => $reviewer->id,
            'category_id' => 44,
            'reported' => 1,
            'promoted' => 1,
            'from' => '2026-07-01',
            'to' => '2026-07-31',
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.work_id', $expected->id)
            ->assertJsonPath('data.summary', [
                'total_events' => 1,
                'unique_works' => 1,
                'created_events' => 0,
                'updated_events' => 0,
                'submitted_events' => 0,
                'reviewed_events' => 0,
                'approved_events' => 0,
                'published_events' => 1,
                'rejected_events' => 0,
                'hidden_events' => 0,
                'archived_events' => 0,
                'review_events' => 0,
                'visibility_events' => 1,
                'reported_events' => 1,
                'promoted_events' => 1,
            ]);

        $this->assertSame(
            [
                'q' => 'Summary Filter Target',
                'event_type' => 'published',
                'status' => Work::STATUS_PUBLISHED,
                'visibility_status' => Work::VISIBILITY_PUBLIC,
                'media_type' => 'video',
                'designer_id' => $designer->id,
                'reviewer_id' => $reviewer->id,
                'category_id' => 44,
                'reported' => true,
                'promoted' => true,
                'from' => '2026-07-01',
                'to' => '2026-07-31',
                'sort' => 'event_at',
                'direction' => 'desc',
            ],
            $response->json('data.filters'),
        );
    }

    public function test_activity_flags_are_calculated_correctly(): void
    {
        $this->actingAsRole('super-admin');
        $reviewWork = Work::factory()->featured()->create([
            'reports_count' => 2,
            'submitted_at' => '2026-07-01 10:00:00',
            'reviewed_at' => '2026-07-02 10:00:00',
            'approved_at' => '2026-07-03 10:00:00',
            'rejected_at' => '2026-07-04 10:00:00',
        ]);
        $visibilityWork = Work::factory()->create([
            'reports_count' => 0,
            'is_featured' => false,
            'is_pinned' => false,
            'published_at' => '2026-07-05 10:00:00',
            'hidden_at' => '2026-07-06 10:00:00',
            'archived_at' => '2026-07-07 10:00:00',
        ]);

        $response = $this->getJson('/api/admin/works/activity?per_page=50')
            ->assertOk();
        $items = collect($response->json('data.items'))->keyBy('id');

        foreach (['submitted', 'reviewed', 'approved', 'rejected'] as $eventType) {
            $this->assertSame([
                'is_review_event' => true,
                'is_visibility_event' => false,
                'is_reported' => true,
                'is_promoted' => true,
                'needs_attention' => true,
            ], $items->get('work-'.$reviewWork->id.'-'.$eventType)['activity_flags']);
        }

        $this->assertSame([
            'is_review_event' => false,
            'is_visibility_event' => true,
            'is_reported' => false,
            'is_promoted' => false,
            'needs_attention' => false,
        ], $items->get('work-'.$visibilityWork->id.'-published')['activity_flags']);

        foreach (['hidden', 'archived'] as $eventType) {
            $this->assertSame([
                'is_review_event' => false,
                'is_visibility_event' => true,
                'is_reported' => false,
                'is_promoted' => false,
                'needs_attention' => true,
            ], $items->get('work-'.$visibilityWork->id.'-'.$eventType)['activity_flags']);
        }

        $this->assertSame([
            'is_review_event' => false,
            'is_visibility_event' => false,
            'is_reported' => false,
            'is_promoted' => false,
            'needs_attention' => false,
        ], $items->get('work-'.$visibilityWork->id.'-created')['activity_flags']);
    }

    public function test_static_activity_route_resolves_to_activity_controller(): void
    {
        $this->actingAsRole('super-admin');

        $route = Route::getRoutes()->match(Request::create('/api/admin/works/activity', 'GET'));

        $this->assertSame(
            WorksActivityController::class.'@index',
            $route->getActionName(),
        );
        $this->getJson('/api/admin/works/activity')
            ->assertOk()
            ->assertJsonPath('message', 'تم جلب سجل الأعمال بنجاح');
        $this->getJson('/api/admin/works/not-a-number')
            ->assertNotFound();
    }

    /**
     * @param array<string, bool|int|string> $filters
     */
    private function assertSingleCreatedEvent(array $filters, Work $expected): void
    {
        $response = $this->getJson($this->endpoint([
            ...$filters,
            'event_type' => 'created',
        ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.summary.total_events', 1)
            ->assertJsonPath('data.summary.unique_works', 1)
            ->assertJsonCount(1, 'data.items');

        $this->assertSame($expected->id, $response->json('data.items.0.work_id'));
    }

    /**
     * @param list<int> $expectedIds
     */
    private function assertSortedWorkIds(string $sort, string $direction, array $expectedIds): void
    {
        $response = $this->getJson($this->endpoint([
            'event_type' => 'created',
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => 50,
        ]))
            ->assertOk()
            ->assertJsonPath('data.filters.sort', $sort)
            ->assertJsonPath('data.filters.direction', $direction);

        $this->assertSame(
            $expectedIds,
            collect($response->json('data.items'))->pluck('work_id')->all(),
        );
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
