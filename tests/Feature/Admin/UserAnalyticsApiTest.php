<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserAnalyticsApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_user_analytics(): void
    {
        $this->getJson('/api/admin/analytics/users')
            ->assertUnauthorized();
    }

    public function test_super_admin_can_access_aggregated_user_analytics(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);

        $this->getJson('/api/admin/analytics/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.filters.period', 'day')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'current_period_users',
                        'previous_period_users',
                        'absolute_change',
                        'percentage_change',
                        'verified_rate',
                        'unverified_rate',
                    ],
                    'trend' => [
                        '*' => ['period', 'count', 'cumulative_count'],
                    ],
                    'role_mix' => [
                        '*' => ['role', 'count', 'percentage'],
                    ],
                    'comparison' => [
                        'current_from',
                        'current_to',
                        'previous_from',
                        'previous_to',
                        'period',
                        'role',
                    ],
                    'filters' => ['from', 'to', 'role', 'period'],
                    'generated_at',
                ],
                'message',
                'errors',
            ]);
    }

    public function test_admin_cannot_access_analytics_even_with_accidental_permission(): void
    {
        $this->actingAsRoleWithAccidentalPermission('admin');

        $this->getJson('/api/admin/analytics/users')
            ->assertForbidden();
    }

    public function test_staff_client_and_designer_cannot_access_analytics_even_with_accidental_permission(): void
    {
        foreach (['staff', 'client', 'designer'] as $role) {
            $this->actingAsRoleWithAccidentalPermission($role);

            $this->getJson('/api/admin/analytics/users')
                ->assertForbidden();
        }
    }

    public function test_response_contains_only_aggregates_without_personal_user_fields(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $user = $this->userWithRole('client', [
            'name' => 'Private Analytics User',
            'email' => 'private.analytics.user@example.com',
            'password' => 'private-password-value',
            'remember_token' => 'private-remember-token',
            'created_at' => '2026-07-02 10:00:00',
        ]);

        $response = $this->getJson('/api/admin/analytics/users?from=2026-07-01&to=2026-07-03')
            ->assertOk();

        $keys = $this->recursiveKeys($response->json('data'));

        foreach (['email', 'name', 'password', 'remember_token', 'token', 'cookie', 'users'] as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $keys);
        }

        $json = $response->getContent();
        $this->assertStringNotContainsString($user->name, $json);
        $this->assertStringNotContainsString($user->email, $json);
        $this->assertStringNotContainsString('private-remember-token', $json);
    }

    public function test_default_range_uses_last_thirty_days_and_the_thirty_days_before_them(): void
    {
        Carbon::setTestNow('2026-07-30 12:00:00');
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-10 10:00:00']);
        $this->userWithRole('designer', ['created_at' => '2026-06-15 10:00:00']);
        $this->userWithRole('staff', ['created_at' => '2026-05-31 10:00:00']);

        $this->getJson('/api/admin/analytics/users')
            ->assertOk()
            ->assertJsonPath('data.summary.current_period_users', 1)
            ->assertJsonPath('data.summary.previous_period_users', 1)
            ->assertJsonPath('data.comparison.current_from', '2026-07-01')
            ->assertJsonPath('data.comparison.current_to', '2026-07-30')
            ->assertJsonPath('data.comparison.previous_from', '2026-06-01')
            ->assertJsonPath('data.comparison.previous_to', '2026-06-30')
            ->assertJsonPath('data.filters.from', null)
            ->assertJsonPath('data.filters.to', null);
    }

    public function test_from_and_to_filters_apply_to_user_registration_date(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-06 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-10 10:00:00']);
        $this->userWithRole('designer', ['created_at' => '2026-07-12 10:00:00']);
        $this->userWithRole('staff', ['created_at' => '2026-07-20 10:00:00']);

        $this->getJson('/api/admin/analytics/users?from=2026-07-10&to=2026-07-14')
            ->assertOk()
            ->assertJsonPath('data.summary.current_period_users', 2)
            ->assertJsonPath('data.summary.previous_period_users', 1)
            ->assertJsonPath('data.filters.from', '2026-07-10')
            ->assertJsonPath('data.filters.to', '2026-07-14');
    }

    public function test_previous_range_has_the_same_inclusive_length_as_current_range(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);

        $this->getJson('/api/admin/analytics/users?from=2026-07-10&to=2026-07-14')
            ->assertOk()
            ->assertJsonPath('data.comparison.current_from', '2026-07-10')
            ->assertJsonPath('data.comparison.current_to', '2026-07-14')
            ->assertJsonPath('data.comparison.previous_from', '2026-07-05')
            ->assertJsonPath('data.comparison.previous_to', '2026-07-09');
    }

    public function test_role_filter_restricts_summary_trend_and_role_mix(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-06 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-10 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-11 10:00:00']);
        $this->userWithRole('designer', ['created_at' => '2026-07-12 10:00:00']);

        $response = $this->getJson('/api/admin/analytics/users?from=2026-07-10&to=2026-07-13&role=client')
            ->assertOk()
            ->assertJsonPath('data.summary.current_period_users', 2)
            ->assertJsonPath('data.summary.previous_period_users', 1)
            ->assertJsonPath('data.filters.role', 'client')
            ->assertJsonCount(1, 'data.role_mix')
            ->assertJsonPath('data.role_mix.0.role', 'client')
            ->assertJsonPath('data.role_mix.0.count', 2)
            ->assertJsonPath('data.role_mix.0.percentage', 100);

        $this->assertSame(2, collect($response->json('data.trend'))->sum('count'));
    }

    public function test_unknown_role_returns_validation_error(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/analytics/users?role=missing-role')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_day_period_groups_trend_with_cumulative_counts(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-10 10:00:00']);
        $this->userWithRole('designer', ['created_at' => '2026-07-10 18:00:00']);
        $this->userWithRole('staff', ['created_at' => '2026-07-11 10:00:00']);

        $response = $this->getJson('/api/admin/analytics/users?from=2026-07-10&to=2026-07-11&period=day')
            ->assertOk();

        $this->assertSame([
            ['period' => '2026-07-10', 'count' => 2, 'cumulative_count' => 2],
            ['period' => '2026-07-11', 'count' => 1, 'cumulative_count' => 3],
        ], $response->json('data.trend'));
    }

    public function test_month_period_groups_trend_by_month(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-01 10:00:00']);
        $this->userWithRole('designer', ['created_at' => '2026-07-20 10:00:00']);
        $this->userWithRole('staff', ['created_at' => '2026-08-01 10:00:00']);

        $response = $this->getJson('/api/admin/analytics/users?from=2026-07-01&to=2026-08-31&period=month')
            ->assertOk();

        $this->assertSame([
            ['period' => '2026-07', 'count' => 2, 'cumulative_count' => 2],
            ['period' => '2026-08', 'count' => 1, 'cumulative_count' => 3],
        ], $response->json('data.trend'));
    }

    public function test_invalid_period_and_invalid_date_range_return_validation_errors(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/analytics/users?period=quarter')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);

        $this->getJson('/api/admin/analytics/users?from=2026-07-10&to=2026-07-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }

    public function test_unknown_query_parameter_returns_validation_error(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/analytics/users?sort=asc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_sensitive_and_unsupported_query_parameters_are_rejected(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/analytics/users?email=x&name=x&password=x&token=x&cookie=x&payload=x&request=x&metadata=x&export=x&format=x')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'name',
                'password',
                'token',
                'cookie',
                'payload',
                'request',
                'metadata',
                'export',
                'format',
            ]);
    }

    public function test_percentage_change_is_null_when_previous_is_zero_and_current_is_positive(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-10 10:00:00']);

        $this->getJson('/api/admin/analytics/users?from=2026-07-10&to=2026-07-10')
            ->assertOk()
            ->assertJsonPath('data.summary.current_period_users', 1)
            ->assertJsonPath('data.summary.previous_period_users', 0)
            ->assertJsonPath('data.summary.percentage_change', null);
    }

    public function test_percentage_change_is_zero_when_current_and_previous_are_zero(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);

        $this->getJson('/api/admin/analytics/users?from=2026-07-10&to=2026-07-10')
            ->assertOk()
            ->assertJsonPath('data.summary.current_period_users', 0)
            ->assertJsonPath('data.summary.previous_period_users', 0)
            ->assertJsonPath('data.summary.percentage_change', 0);
    }

    private function actingAsRole(string $role, array $attributes = []): User
    {
        $user = $this->userWithRole($role, $attributes);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function actingAsRoleWithAccidentalPermission(string $role): User
    {
        $permission = Permission::firstOrCreate([
            'name' => 'admin.analytics.users.view',
            'guard_name' => 'web',
        ]);
        $user = $this->userWithRole($role);
        $user->givePermissionTo($permission);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

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
