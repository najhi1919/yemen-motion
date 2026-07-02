<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardLegacyAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_cannot_access_legacy_dashboard_endpoints(): void
    {
        foreach ($this->legacyDashboardEndpoints() as $endpoint) {
            $this->getJson($endpoint)
                ->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'غير مصادق عليه.',
                    'data' => null,
                    'errors' => null,
                ]);
        }
    }

    public function test_super_admin_can_access_legacy_dashboard_endpoints(): void
    {
        $superAdmin = User::factory()->create(['email' => 'legacy-dashboard-super-admin@example.com']);
        $superAdmin->assignRole('super-admin');

        Sanctum::actingAs($superAdmin, ['*']);

        foreach ($this->legacyDashboardEndpoints() as $endpoint) {
            $this->getJson($endpoint)
                ->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'errors' => null,
                ]);
        }
    }

    public function test_admin_can_access_legacy_dashboard_endpoints_with_permissions(): void
    {
        $admin = User::factory()->create(['email' => 'legacy-dashboard-admin@example.com']);
        $admin->assignRole('admin');

        Sanctum::actingAs($admin, ['*']);

        foreach ($this->legacyDashboardEndpoints() as $endpoint) {
            $this->getJson($endpoint)
                ->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'errors' => null,
                ]);
        }
    }

    public function test_staff_cannot_access_legacy_dashboard_endpoints(): void
    {
        $staff = User::factory()->create(['email' => 'legacy-dashboard-staff@example.com']);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff, ['*']);

        foreach ($this->legacyDashboardEndpoints() as $endpoint) {
            $this->getJson($endpoint)
                ->assertStatus(403);
        }
    }

    public function test_client_cannot_access_legacy_dashboard_endpoints(): void
    {
        $client = User::factory()->create(['email' => 'legacy-dashboard-client@example.com']);
        $client->assignRole('client');

        Sanctum::actingAs($client, ['*']);

        foreach ($this->legacyDashboardEndpoints() as $endpoint) {
            $this->getJson($endpoint)
                ->assertStatus(403);
        }
    }

    public function test_designer_cannot_access_legacy_dashboard_endpoints(): void
    {
        $designer = User::factory()->create(['email' => 'legacy-dashboard-designer@example.com']);
        $designer->assignRole('designer');

        Sanctum::actingAs($designer, ['*']);

        foreach ($this->legacyDashboardEndpoints() as $endpoint) {
            $this->getJson($endpoint)
                ->assertStatus(403);
        }
    }

    public function test_authenticated_non_admin_can_still_access_dashboard_overview_with_role_scoping(): void
    {
        $staff = User::factory()->create(['email' => 'overview-staff-access@example.com']);
        $staff->assignRole('staff');

        Sanctum::actingAs($staff, ['*']);

        $this->getJson('/api/dashboard/overview')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'staff');
    }

    private function legacyDashboardEndpoints(): array
    {
        return [
            '/api/dashboard/stats',
            '/api/dashboard/activity',
            '/api/dashboard/chart',
        ];
    }
}
