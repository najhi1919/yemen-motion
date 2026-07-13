<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorksAccessGateTest extends TestCase
{
    use RefreshDatabase;

    private const SECTIONS = [
        [
            'key' => 'overview',
            'label_ar' => 'النظرة العامة',
            'route' => '/admin/works',
            'permission' => 'admin.works.overview.view',
            'allowed' => true,
        ],
        [
            'key' => 'all',
            'label_ar' => 'كل الأعمال',
            'route' => '/admin/works/all',
            'permission' => 'admin.works.all.view',
            'allowed' => true,
        ],
        [
            'key' => 'review',
            'label_ar' => 'طلبات المراجعة',
            'route' => '/admin/works/review',
            'permission' => 'admin.works.review.view',
            'allowed' => true,
        ],
        [
            'key' => 'visibility',
            'label_ar' => 'الظهور والتمييز',
            'route' => '/admin/works/visibility',
            'permission' => 'admin.works.visibility.view',
            'allowed' => true,
        ],
        [
            'key' => 'reports',
            'label_ar' => 'البلاغات والمخالفات',
            'route' => '/admin/works/reports',
            'permission' => 'admin.works.reports.view',
            'allowed' => true,
        ],
        [
            'key' => 'taxonomy',
            'label_ar' => 'التصنيفات والوسوم',
            'route' => '/admin/works/taxonomy',
            'permission' => 'admin.works.taxonomy.view',
            'allowed' => true,
        ],
        [
            'key' => 'activity',
            'label_ar' => 'سجل الأعمال',
            'route' => '/admin/works/activity',
            'permission' => 'admin.works.activity.view',
            'allowed' => true,
        ],
        [
            'key' => 'settings',
            'label_ar' => 'إعدادات وصلاحيات الأعمال',
            'route' => '/admin/works/settings',
            'permission' => 'admin.works.settings.view',
            'allowed' => true,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/admin/works/access')
            ->assertUnauthorized();
    }

    public function test_super_admin_receives_all_eight_sections(): void
    {
        $this->actingAsRole('super-admin');

        $response = $this->getJson('/api/admin/works/access')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.base_route', '/admin/works')
            ->assertJsonPath('data.sidebar_mode', 'contextual')
            ->assertJsonCount(8, 'data.sections');

        $this->assertSame(self::SECTIONS, $response->json('data.sections'));
        $this->assertSame($this->permissionMapFor(self::SECTIONS), $response->json('data.permissions'));
    }

    public function test_admin_without_works_access_gets_403(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/admin/works/access')
            ->assertForbidden();
    }

    public function test_staff_without_works_access_gets_403(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/admin/works/access')
            ->assertForbidden();
    }

    public function test_admin_receives_only_the_single_permitted_section(): void
    {
        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.review.view',
        ]);

        $response = $this->getJson('/api/admin/works/access')
            ->assertOk()
            ->assertJsonCount(1, 'data.sections');

        $expectedSections = [self::SECTIONS[2]];

        $this->assertSame($expectedSections, $response->json('data.sections'));
        $this->assertSame($this->permissionMapFor($expectedSections), $response->json('data.permissions'));
    }

    public function test_staff_receives_only_the_multiple_permitted_sections(): void
    {
        $this->actingAsRole('staff', [
            'admin.works.access',
            'admin.works.overview.view',
            'admin.works.reports.view',
            'admin.works.activity.view',
        ]);

        $response = $this->getJson('/api/admin/works/access')
            ->assertOk()
            ->assertJsonCount(3, 'data.sections');

        $expectedSections = [
            self::SECTIONS[0],
            self::SECTIONS[4],
            self::SECTIONS[6],
        ];

        $this->assertSame($expectedSections, $response->json('data.sections'));
        $this->assertSame($this->permissionMapFor($expectedSections), $response->json('data.permissions'));
    }

    public function test_admin_and_staff_with_access_but_no_section_permissions_receive_empty_sections(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, ['admin.works.access']);

            $response = $this->getJson('/api/admin/works/access')
                ->assertOk()
                ->assertJsonPath('data.sections', []);

            $this->assertSame(
                ['admin.works.access' => true],
                $response->json('data.permissions'),
            );
        }
    }

    public function test_client_with_accidental_works_permissions_gets_403(): void
    {
        $this->actingAsRole('client', [
            'admin.works.access',
            'admin.works.overview.view',
        ]);

        $this->getJson('/api/admin/works/access')
            ->assertForbidden();
    }

    public function test_designer_with_accidental_works_permissions_gets_403(): void
    {
        $this->actingAsRole('designer', [
            'admin.works.access',
            'admin.works.settings.view',
        ]);

        $this->getJson('/api/admin/works/access')
            ->assertForbidden();
    }

    public function test_unknown_and_sensitive_query_parameters_return_422(): void
    {
        $this->actingAsRole('super-admin');

        foreach (['role', 'email', 'token', 'q'] as $parameter) {
            $this->getJson('/api/admin/works/access?'.$parameter.'=blocked')
                ->assertUnprocessable()
                ->assertJsonValidationErrors($parameter);
        }
    }

    public function test_response_exposes_only_the_safe_access_contract(): void
    {
        $user = $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.overview.view',
        ]);

        $response = $this->getJson('/api/admin/works/access')
            ->assertOk();

        $this->assertSame(
            ['base_route', 'permissions', 'sections', 'sidebar_mode'],
            collect(array_keys($response->json('data')))->sort()->values()->all(),
        );

        $encodedResponse = strtolower(json_encode($response->json(), JSON_THROW_ON_ERROR));

        $this->assertStringNotContainsString(strtolower($user->email), $encodedResponse);
        $this->assertStringNotContainsString(strtolower($user->name), $encodedResponse);

        foreach (['user', 'email', 'name', 'payload', 'metadata', 'password', 'token', 'cookie'] as $key) {
            $this->assertArrayNotHasKey($key, $response->json('data'));
        }
    }

    /**
     * @param  list<string>  $permissions
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
     * @param  list<array{key: string, label_ar: string, route: string, permission: string, allowed: bool}>  $sections
     * @return array<string, bool>
     */
    private function permissionMapFor(array $sections): array
    {
        $permissions = [
            'admin.works.access' => true,
        ];

        foreach ($sections as $section) {
            $permissions[$section['permission']] = true;
        }

        return $permissions;
    }
}
