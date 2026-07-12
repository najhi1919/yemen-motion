<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserReportsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_cannot_access_user_report(): void
    {
        $this->getJson('/api/admin/reports/users')
            ->assertUnauthorized();
    }

    public function test_super_admin_can_access_aggregated_user_report(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/reports/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.filters.period', 'day')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_users',
                        'users_in_range',
                        'verified_users',
                        'unverified_users',
                    ],
                    'role_breakdown' => [
                        '*' => ['role', 'count'],
                    ],
                    'registrations_series' => [
                        '*' => ['period', 'count'],
                    ],
                    'filters' => ['from', 'to', 'role', 'period'],
                    'generated_at',
                ],
                'message',
                'errors',
            ]);
    }

    public function test_admin_cannot_access_report_even_with_accidental_permission(): void
    {
        $this->actingAsRoleWithAccidentalPermission('admin');

        $this->getJson('/api/admin/reports/users')
            ->assertForbidden();
    }

    public function test_staff_client_and_designer_cannot_access_report_even_with_accidental_permission(): void
    {
        foreach (['staff', 'client', 'designer'] as $role) {
            $this->actingAsRoleWithAccidentalPermission($role);

            $this->getJson('/api/admin/reports/users')
                ->assertForbidden();
        }
    }

    public function test_response_contains_only_aggregates_without_personal_user_fields(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $user = $this->userWithRole('client', [
            'name' => 'Private Report User',
            'email' => 'private.report.user@example.com',
            'password' => 'private-password-value',
            'remember_token' => 'private-remember-token',
            'created_at' => '2026-07-02 10:00:00',
        ]);

        $response = $this->getJson('/api/admin/reports/users?from=2026-07-01&to=2026-07-03')
            ->assertOk();

        $payload = $response->json('data');
        $keys = $this->recursiveKeys($payload);

        foreach (['email', 'name', 'password', 'remember_token', 'token', 'cookie', 'users'] as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $keys);
        }

        $json = $response->getContent();
        $this->assertStringNotContainsString($user->name, $json);
        $this->assertStringNotContainsString($user->email, $json);
        $this->assertStringNotContainsString('private-remember-token', $json);
    }

    public function test_from_and_to_filters_apply_to_user_registration_date(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-06-30 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-02 10:00:00']);
        $this->userWithRole('designer', [
            'created_at' => '2026-07-03 10:00:00',
            'email_verified_at' => null,
        ]);
        $this->userWithRole('staff', ['created_at' => '2026-07-10 10:00:00']);

        $this->getJson('/api/admin/reports/users?from=2026-07-01&to=2026-07-05')
            ->assertOk()
            ->assertJsonPath('data.summary.total_users', 5)
            ->assertJsonPath('data.summary.users_in_range', 2)
            ->assertJsonPath('data.summary.verified_users', 1)
            ->assertJsonPath('data.summary.unverified_users', 1)
            ->assertJsonPath('data.filters.from', '2026-07-01')
            ->assertJsonPath('data.filters.to', '2026-07-05');
    }

    public function test_role_filter_restricts_all_report_aggregates(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-01 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-02 10:00:00']);
        $this->userWithRole('designer', ['created_at' => '2026-07-03 10:00:00']);

        $this->getJson('/api/admin/reports/users?role=client')
            ->assertOk()
            ->assertJsonPath('data.summary.total_users', 2)
            ->assertJsonPath('data.summary.users_in_range', 2)
            ->assertJsonCount(1, 'data.role_breakdown')
            ->assertJsonPath('data.role_breakdown.0.role', 'client')
            ->assertJsonPath('data.role_breakdown.0.count', 2)
            ->assertJsonPath('data.filters.role', 'client');
    }

    public function test_unknown_role_returns_validation_error(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/reports/users?role=missing-role')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_day_period_groups_registrations_by_date(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-01 10:00:00']);
        $this->userWithRole('designer', ['created_at' => '2026-07-01 18:00:00']);
        $this->userWithRole('staff', ['created_at' => '2026-07-02 10:00:00']);

        $response = $this->getJson('/api/admin/reports/users?from=2026-07-01&to=2026-07-02&period=day')
            ->assertOk()
            ->assertJsonPath('data.summary.total_users', 4)
            ->assertJsonPath('data.summary.users_in_range', 3)
            ->assertJsonPath('data.summary.verified_users', 3)
            ->assertJsonPath('data.summary.unverified_users', 0)
            ->assertJsonPath('data.filters.period', 'day');

        $this->assertSame([
            ['role' => 'admin', 'count' => 0],
            ['role' => 'client', 'count' => 1],
            ['role' => 'designer', 'count' => 1],
            ['role' => 'staff', 'count' => 1],
            ['role' => 'super-admin', 'count' => 0],
        ], $response->json('data.role_breakdown'));
        $this->assertSame([
            ['period' => '2026-07-01', 'count' => 2],
            ['period' => '2026-07-02', 'count' => 1],
        ], $response->json('data.registrations_series'));
    }

    public function test_month_period_groups_registrations_by_month(): void
    {
        $this->actingAsRole('super-admin', ['created_at' => '2026-01-01 10:00:00']);
        $this->userWithRole('client', ['created_at' => '2026-07-01 10:00:00']);
        $this->userWithRole('designer', ['created_at' => '2026-07-20 10:00:00']);
        $this->userWithRole('staff', ['created_at' => '2026-08-01 10:00:00']);

        $response = $this->getJson('/api/admin/reports/users?from=2026-07-01&to=2026-08-31&period=month')
            ->assertOk();

        $this->assertSame([
            ['period' => '2026-07', 'count' => 2],
            ['period' => '2026-08', 'count' => 1],
        ], $response->json('data.registrations_series'));
    }

    public function test_invalid_period_and_invalid_date_range_return_validation_errors(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/reports/users?period=quarter')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);

        $this->getJson('/api/admin/reports/users?from=2026-07-10&to=2026-07-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }

    public function test_unknown_query_parameter_returns_validation_error(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/reports/users?sort=asc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_sensitive_and_unsupported_query_parameters_are_rejected(): void
    {
        $this->actingAsRole('super-admin');

        $this->getJson('/api/admin/reports/users?email=x&name=x&password=x&token=x&cookie=x&payload=x&request=x&metadata=x&export=x&format=x')
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

    private function actingAsRole(string $role, array $attributes = []): User
    {
        $user = $this->userWithRole($role, $attributes);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function actingAsRoleWithAccidentalPermission(string $role): User
    {
        $permission = Permission::firstOrCreate([
            'name' => 'admin.reports.users.view',
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
