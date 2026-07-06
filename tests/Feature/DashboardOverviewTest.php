<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'designer', 'guard_name' => 'web']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(401);
    }

    public function test_authenticated_admin_gets_valid_json_shape(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'role',
                    'period',
                    'sections',
                    'cards',
                    'charts',
                    'activities',
                ],
                'message',
                'errors',
                'meta' => [
                    'periods',
                    'selected_period',
                ],
            ])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('meta.selected_period', 'month')
            ->assertJsonPath('meta.periods', ['day', 'week', 'month', 'year']);

        $sectionKeys = collect($response->json('data.sections'))->pluck('key')->toArray();
        $this->assertContains('users', $sectionKeys);
        $this->assertContains('orders', $sectionKeys);
        $this->assertContains('works', $sectionKeys);
        $this->assertContains('contests', $sectionKeys);
        $this->assertContains('wallet', $sectionKeys);
        $this->assertContains('staff', $sectionKeys);
        $this->assertContains('roles', $sectionKeys);
        $this->assertContains('permissions', $sectionKeys);
        $this->assertContains('access', $sectionKeys);
    }

    public function test_admin_sees_real_access_metrics(): void
    {
        Permission::firstOrCreate(['name' => 'dashboard.overview.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'admin.roles.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'custom.reports.export', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'support-agent', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        User::factory()->count(2)->create()->each(fn(User $user) => $user->assignRole('staff'));
        User::factory()->create()->assignRole('client');
        User::factory()->create()->assignRole('designer');

        Sanctum::actingAs($admin, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $cards = collect($response->json('data.cards'))->keyBy('key');

        $this->assertSame(User::count(), $cards->get('users')['value']);
        $this->assertSame(
            User::whereHas('roles', fn($query) => $query->where('name', 'staff'))->count(),
            $cards->get('staff')['value']
        );
        $this->assertSame(Role::count(), $cards->get('roles')['value']);
        $this->assertSame(Permission::count(), $cards->get('permissions')['value']);
    }

    public function test_authenticated_staff_gets_valid_json_shape(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Sanctum::actingAs($staff, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'role',
                    'period',
                    'sections',
                    'cards',
                    'charts',
                    'activities',
                ],
                'message',
                'errors',
                'meta' => [
                    'periods',
                    'selected_period',
                ],
            ])
            ->assertJson(['success' => true])
            ->assertJsonPath('data.role', 'staff')
            ->assertJsonPath('meta.selected_period', 'month');
    }

    public function test_staff_does_not_see_admin_only_sections(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        Sanctum::actingAs($staff, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview');

        $sectionKeys = collect($response->json('data.sections'))->pluck('key')->toArray();
        $cardKeys = collect($response->json('data.cards'))->pluck('key')->toArray();
        $chartKeys = collect($response->json('data.charts'))->pluck('key')->toArray();

        $adminOnlyKeys = ['users', 'orders', 'works', 'contests', 'wallet'];
        foreach ($adminOnlyKeys as $key) {
            $this->assertNotContains($key, $sectionKeys, "Staff should not see section: $key");
            $this->assertNotContains($key, $cardKeys, "Staff should not see card: $key");
            $this->assertNotContains($key, $chartKeys, "Staff should not see chart: $key");
        }

        $this->assertContains('works_review', $sectionKeys);
        $this->assertContains('reports', $sectionKeys);
        $this->assertContains('activities_feed', $sectionKeys);
    }

    public function test_overview_does_not_return_fake_demo_activity(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['*']);

        $response = $this->json('GET', '/api/dashboard/overview')
            ->assertStatus(200);

        $activityLabels = collect($response->json('data.activities'))
            ->map(fn(array $activity) => $activity['label']['ar'] ?? null)
            ->filter()
            ->values()
            ->all();

        $this->assertNotContains('نشاط تجريبي', $activityLabels);
    }

    public function test_period_is_reflected_in_response(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['*']);

        foreach (['day', 'week', 'month', 'year'] as $period) {
            $this->json('GET', "/api/dashboard/overview?period=$period")
                ->assertStatus(200)
                ->assertJsonPath('data.period', $period)
                ->assertJsonPath('meta.selected_period', $period);
        }
    }

    public function test_invalid_period_returns_422(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['*']);

        $this->json('GET', '/api/dashboard/overview?period=invalid')
            ->assertStatus(422)
            ->assertJson(['message' => 'Invalid period provided']);
    }
}
