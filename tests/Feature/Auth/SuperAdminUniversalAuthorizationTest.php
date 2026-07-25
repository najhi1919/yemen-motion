<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Work;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SuperAdminUniversalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_super_admin_identity_uses_the_stable_role_identifier_not_display_names(): void
    {
        $superAdmin = User::factory()->create(['name' => 'اسم عادي']);
        $superAdmin->assignRole(User::superAdminRoleName());

        Role::findOrCreate('super-admin-copy', 'web');
        $similarRoleUser = User::factory()->create(['name' => 'Super Admin']);
        $similarRoleUser->assignRole('super-admin-copy');

        $translatedDisplayNameUser = User::factory()->create(['name' => 'مسؤول النظام الأعلى']);
        $translatedDisplayNameUser->assignRole('admin');

        $this->assertSame('super-admin', User::superAdminRoleName());
        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertFalse($similarRoleUser->isSuperAdmin());
        $this->assertFalse($translatedDisplayNameUser->isSuperAdmin());
    }

    public function test_super_admin_can_every_registered_permission_without_role_or_user_grants(): void
    {
        $superAdminRole = Role::findByName(User::superAdminRoleName(), 'web');
        $superAdminRole->syncPermissions([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole($superAdminRole);
        $superAdmin->unsetRelation('roles')->unsetRelation('permissions');

        $this->assertSame(0, $superAdminRole->permissions()->count());
        $this->assertSame(0, $superAdmin->permissions()->count());

        $registeredPermissions = collect(config('yemen-motion-permissions.permissions', []))
            ->pluck('name')
            ->filter(fn (mixed $permission): bool => is_string($permission) && $permission !== '')
            ->values();

        $this->assertNotEmpty($registeredPermissions);

        foreach ($registeredPermissions as $permission) {
            $this->assertTrue(
                Gate::forUser($superAdmin)->allows($permission),
                "Super Admin should pass the registered ability: {$permission}",
            );
            $this->assertTrue(
                $superAdmin->can($permission),
                "Super Admin should authorize without a grant pivot: {$permission}",
            );
        }
    }

    public function test_permission_middleware_uses_the_central_bypass_and_preserves_normal_permissions(): void
    {
        $permission = 'admin.works.taxonomy.categories.create';
        Route::get('/api/testing/super-admin-invariant', fn () => response()->json(['allowed' => true]))
            ->middleware(['auth:sanctum', PermissionMiddleware::using($permission)]);

        $superAdmin = $this->superAdminWithoutGrants();
        Sanctum::actingAs($superAdmin, ['*']);
        $this->getJson('/api/testing/super-admin-invariant')
            ->assertOk()
            ->assertJsonPath('allowed', true);

        $ordinaryUser = User::factory()->create();
        $ordinaryUser->assignRole('admin');
        Sanctum::actingAs($ordinaryUser, ['*']);
        $this->getJson('/api/testing/super-admin-invariant')->assertForbidden();

        $ordinaryUser->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($ordinaryUser->fresh(), ['*']);
        $this->getJson('/api/testing/super-admin-invariant')
            ->assertOk()
            ->assertJsonPath('allowed', true);
    }

    public function test_form_request_authorization_uses_the_central_gate_bypass(): void
    {
        Sanctum::actingAs($this->superAdminWithoutGrants(), ['*']);

        $this->postJson('/api/admin/permissions', [
            'name' => 'custom.super-admin-invariant',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'custom.super-admin-invariant');
    }

    public function test_super_admin_without_grants_can_manage_existing_taxonomy_actions(): void
    {
        Sanctum::actingAs($this->superAdminWithoutGrants(), ['*']);

        $this->getJson('/api/admin/works/taxonomy/categories')->assertOk();
        $this->getJson('/api/admin/works/taxonomy/tags')->assertOk();

        $categoryId = $this->postJson('/api/admin/works/taxonomy/categories', [
            'name_ar' => 'تصنيف تجاوز مركزي',
            'name_en' => 'Central Bypass Category',
            'slug' => 'central-bypass-category',
            'sort_order' => 0,
        ])->assertCreated()->json('data.category.id');

        $this->patchJson("/api/admin/works/taxonomy/categories/{$categoryId}", [
            'name_en' => 'Updated Central Category',
        ])->assertOk();
        $this->patchJson("/api/admin/works/taxonomy/categories/{$categoryId}/disable")
            ->assertOk()
            ->assertJsonPath('data.category.is_active', false);

        $tagId = $this->postJson('/api/admin/works/taxonomy/tags', [
            'name_ar' => 'وسم تجاوز مركزي',
            'name_en' => 'Central Bypass Tag',
            'slug' => 'central-bypass-tag',
            'sort_order' => 0,
        ])->assertCreated()->json('data.tag.id');

        $this->patchJson("/api/admin/works/taxonomy/tags/{$tagId}", [
            'name_en' => 'Updated Central Tag',
        ])->assertOk();
        $this->patchJson("/api/admin/works/taxonomy/tags/{$tagId}/disable")
            ->assertOk()
            ->assertJsonPath('data.tag.is_active', false);
    }

    public function test_ordinary_client_and_designer_users_do_not_receive_the_bypass(): void
    {
        foreach (['admin', 'client', 'designer'] as $roleName) {
            $user = User::factory()->create(['name' => 'Super Admin']);
            $user->assignRole($roleName);

            $this->assertFalse($user->isSuperAdmin());
            $this->assertFalse($user->can('admin.works.taxonomy.categories.create'));
        }

        $ordinaryAdmin = User::factory()->create();
        $ordinaryAdmin->assignRole('admin');
        Sanctum::actingAs($ordinaryAdmin, ['*']);
        $this->getJson('/api/admin/works/taxonomy/categories')->assertForbidden();
        $this->postJson('/api/admin/works/taxonomy/tags', [
            'name_ar' => 'وسم غير مسموح',
            'name_en' => 'Forbidden Tag',
            'slug' => 'forbidden-tag',
            'sort_order' => 0,
        ])->assertForbidden();
    }

    public function test_super_admin_bypass_does_not_override_review_readiness_business_rules(): void
    {
        $superAdmin = $this->superAdminWithoutGrants();
        Sanctum::actingAs($superAdmin, ['*']);

        $work = Work::factory()->create([
            'title' => 'مسودة غير جاهزة',
            'summary' => null,
            'description' => null,
            'media_type' => null,
            'category_id' => null,
            'status' => Work::STATUS_DRAFT,
        ])->refresh();

        $this->patchJson("/api/admin/works/{$work->id}/review/submit", [
            'expected_updated_at' => $work->updated_at?->toJSON(),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.readiness.ready', false);

        $this->assertSame(Work::STATUS_DRAFT, $work->refresh()->status);
    }

    public function test_current_user_payload_exposes_server_derived_super_admin_boolean(): void
    {
        $superAdmin = $this->superAdminWithoutGrants();
        Sanctum::actingAs($superAdmin, ['*']);
        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.is_super_admin', true)
            ->assertJsonPath('data.role', User::superAdminRoleName())
            ->assertJsonPath('data.permissions', []);

        $ordinaryUser = User::factory()->create(['name' => 'Super Admin']);
        $ordinaryUser->assignRole('admin');
        Sanctum::actingAs($ordinaryUser, ['*']);
        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.is_super_admin', false);
    }

    private function superAdminWithoutGrants(): User
    {
        $role = Role::findByName(User::superAdminRoleName(), 'web');
        $role->syncPermissions([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->unsetRelation('roles')->unsetRelation('permissions');
    }
}
