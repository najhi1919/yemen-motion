<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Work;
use Carbon\Carbon;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorksOverviewApiTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/admin/works/overview')
            ->assertUnauthorized();
    }

    public function test_super_admin_can_access_aggregate_only_overview(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/overview')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.filters.period', 'month')
            ->assertJsonPath('data.filters.from', '2026-07-01')
            ->assertJsonPath('data.filters.to', '2026-07-31')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total',
                        'submitted',
                        'in_review',
                        'changes_requested',
                        'approved',
                        'published',
                        'rejected',
                        'hidden',
                        'archived',
                        'featured',
                        'pinned',
                        'reported',
                    ],
                    'visibility' => ['public', 'hidden'],
                    'review_queue' => ['pending', 'in_review', 'changes_requested', 'overdue'],
                    'series' => [
                        '*' => ['label', 'submitted', 'published', 'rejected'],
                    ],
                    'top_counters' => ['views', 'likes', 'reports'],
                    'filters' => ['period', 'from', 'to'],
                    'generated_at',
                ],
                'message',
                'errors',
            ]);
    }

    public function test_admin_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/admin/works/overview')
            ->assertForbidden();
    }

    public function test_staff_without_works_permissions_gets_403(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/admin/works/overview')
            ->assertForbidden();
    }

    public function test_admin_with_access_only_gets_403(): void
    {
        $this->actingAsRole('admin', ['admin.works.access']);

        $this->getJson('/api/admin/works/overview')
            ->assertForbidden();
    }

    public function test_admin_and_staff_with_access_and_overview_permissions_can_access(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, [
                'admin.works.access',
                'admin.works.overview.view',
            ]);

            $this->getJson('/api/admin/works/overview')
                ->assertOk()
                ->assertJsonPath('success', true);
        }
    }

    public function test_client_and_designer_with_accidental_permissions_get_403(): void
    {
        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, [
                'admin.works.access',
                'admin.works.overview.view',
            ]);

            $this->getJson('/api/admin/works/overview')
                ->assertForbidden();
        }
    }

    public function test_response_does_not_expose_work_or_user_sensitive_fields(): void
    {
        $this->actingAsRole('super-admin');
        $designer = User::factory()->create([
            'name' => 'Private Works Designer',
            'email' => 'private.works.designer@example.com',
        ]);
        Work::factory()->create([
            'title' => 'Private Work Title',
            'designer_id' => $designer->id,
            'internal_notes' => 'Private internal work notes',
            'rejection_reason' => 'Private rejection reason',
            'change_request_notes' => 'Private requested changes',
        ]);

        $response = $this->getJson('/api/admin/works/overview')
            ->assertOk();

        $keys = $this->recursiveKeys($response->json('data'));

        foreach ([
            'user',
            'users',
            'designer',
            'email',
            'name',
            'internal_notes',
            'rejection_reason',
            'change_request_notes',
            'metadata',
            'rows',
        ] as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $keys);
        }

        $json = $response->getContent();
        $this->assertStringNotContainsString($designer->name, $json);
        $this->assertStringNotContainsString($designer->email, $json);
        $this->assertStringNotContainsString('Private Work Title', $json);
        $this->assertStringNotContainsString('Private internal work notes', $json);
        $this->assertStringNotContainsString('Private rejection reason', $json);
        $this->assertStringNotContainsString('Private requested changes', $json);
    }

    public function test_summary_counts_statuses_and_flags_correctly(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');
        $this->actingAsRole('super-admin');

        Work::factory()->submitted()->featured()->create();
        Work::factory()->submitted()->create();
        Work::factory()->inReview()->pinned()->create();
        Work::factory()->create([
            'status' => Work::STATUS_CHANGES_REQUESTED,
            'submitted_at' => now()->subDay(),
            'reports_count' => 4,
        ]);
        Work::factory()->approved()->create();
        Work::factory()->published()->count(2)->create();
        Work::factory()->rejected()->create();
        Work::factory()->hidden()->create();
        Work::factory()->archived()->create();

        $this->getJson('/api/admin/works/overview')
            ->assertOk()
            ->assertJsonPath('data.summary', [
                'total' => 10,
                'submitted' => 2,
                'in_review' => 1,
                'changes_requested' => 1,
                'approved' => 1,
                'published' => 2,
                'rejected' => 1,
                'hidden' => 1,
                'archived' => 1,
                'featured' => 1,
                'pinned' => 1,
                'reported' => 1,
            ]);
    }

    public function test_visibility_counts_public_and_hidden_works_correctly(): void
    {
        $this->actingAsRole('super-admin');

        Work::factory()->published()->count(2)->create();
        Work::factory()->hidden()->create();
        Work::factory()->submitted()->create();

        $this->getJson('/api/admin/works/overview')
            ->assertOk()
            ->assertJsonPath('data.visibility', [
                'public' => 2,
                'hidden' => 2,
            ]);
    }

    public function test_review_queue_counts_pending_in_review_changes_and_overdue_correctly(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');
        $this->actingAsRole('super-admin');

        Work::factory()->submitted()->create(['submitted_at' => now()->subHours(10)]);
        Work::factory()->submitted()->create(['submitted_at' => now()->subHours(49)]);
        Work::factory()->submitted()->create([
            'submitted_at' => now()->subHours(72),
            'reviewed_at' => now()->subHour(),
        ]);
        Work::factory()->inReview()->create(['submitted_at' => now()->subHours(20)]);
        Work::factory()->inReview()->create(['submitted_at' => now()->subHours(72)]);
        Work::factory()->inReview()->create([
            'submitted_at' => now()->subHours(72),
            'archived_at' => now()->subHour(),
        ]);
        Work::factory()->count(2)->create([
            'status' => Work::STATUS_CHANGES_REQUESTED,
            'submitted_at' => now()->subDays(3),
        ]);

        $this->getJson('/api/admin/works/overview')
            ->assertOk()
            ->assertJsonPath('data.review_queue', [
                'pending' => 3,
                'in_review' => 3,
                'changes_requested' => 2,
                'overdue' => 2,
            ]);
    }

    public function test_top_counters_sum_all_work_counters(): void
    {
        $this->actingAsRole('super-admin');

        Work::factory()->create(['views_count' => 10, 'likes_count' => 4, 'reports_count' => 1]);
        Work::factory()->create(['views_count' => 25, 'likes_count' => 6, 'reports_count' => 3]);
        Work::factory()->create(['views_count' => 5, 'likes_count' => 0, 'reports_count' => 0]);

        $this->getJson('/api/admin/works/overview')
            ->assertOk()
            ->assertJsonPath('data.top_counters', [
                'views' => 40,
                'likes' => 10,
                'reports' => 4,
            ]);
    }

    public function test_day_week_month_and_year_periods_are_accepted(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');
        $this->actingAsRole('super-admin');

        foreach (['day', 'week', 'month', 'year'] as $period) {
            $this->getJson('/api/admin/works/overview?period='.$period)
                ->assertOk()
                ->assertJsonPath('data.filters.period', $period);
        }
    }

    public function test_custom_range_is_accepted_and_series_uses_lifecycle_timestamps(): void
    {
        $this->actingAsRole('super-admin');

        Work::factory()->create([
            'status' => Work::STATUS_SUBMITTED,
            'submitted_at' => '2026-07-02 10:00:00',
        ]);
        Work::factory()->create([
            'status' => Work::STATUS_PUBLISHED,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'published_at' => '2026-07-15 10:00:00',
        ]);
        Work::factory()->create([
            'status' => Work::STATUS_REJECTED,
            'rejected_at' => '2026-07-20 10:00:00',
        ]);
        Work::factory()->create([
            'status' => Work::STATUS_SUBMITTED,
            'submitted_at' => '2026-08-01 10:00:00',
        ]);

        $response = $this->getJson('/api/admin/works/overview?period=month&from=2026-07-01&to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 4)
            ->assertJsonPath('data.filters', [
                'period' => 'month',
                'from' => '2026-07-01',
                'to' => '2026-07-31',
            ]);

        $this->assertSame([
            [
                'label' => '2026-07',
                'submitted' => 1,
                'published' => 1,
                'rejected' => 1,
            ],
        ], $response->json('data.series'));
    }

    public function test_invalid_period_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/overview?period=quarter')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }

    public function test_to_before_from_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/overview?from=2026-07-10&to=2026-07-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_range_over_ten_years_returns_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/overview?from=2016-07-12&to=2026-07-13')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_unknown_and_sensitive_query_parameters_return_422(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/works/overview?role=x&email=x&token=x&q=x&search=x&password=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
                'email',
                'token',
                'q',
                'search',
                'password',
            ]);
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
