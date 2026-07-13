<?php

namespace Tests\Feature\Permissions;

use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksPermissionRegistryTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_WORKS_PERMISSIONS = [
        'admin.works.access',
        'admin.works.overview.view',
        'admin.works.all.view',
        'admin.works.review.view',
        'admin.works.visibility.view',
        'admin.works.reports.view',
        'admin.works.taxonomy.view',
        'admin.works.activity.view',
        'admin.works.settings.view',
        'admin.works.list',
        'admin.works.detail.view',
        'admin.works.media.view',
        'admin.works.metadata.view',
        'admin.works.designer.view',
        'admin.works.private_notes.view',
        'admin.works.create',
        'admin.works.update.basic',
        'admin.works.update.media',
        'admin.works.update.pricing',
        'admin.works.update.delivery',
        'admin.works.update.category',
        'admin.works.update.tags',
        'admin.works.update.designer',
        'admin.works.update.private_notes',
        'admin.works.review.start',
        'admin.works.review.approve',
        'admin.works.review.request_changes',
        'admin.works.review.reject',
        'admin.works.review.publish_after_approval',
        'admin.works.review.assign_reviewer',
        'admin.works.review.reopen',
        'admin.works.publish',
        'admin.works.unpublish',
        'admin.works.hide',
        'admin.works.restore_visibility',
        'admin.works.feature',
        'admin.works.unfeature',
        'admin.works.pin',
        'admin.works.unpin',
        'admin.works.visibility.order',
        'admin.works.reports.list',
        'admin.works.reports.detail.view',
        'admin.works.reports.review',
        'admin.works.reports.dismiss',
        'admin.works.reports.request_changes',
        'admin.works.reports.hide_work',
        'admin.works.reports.restore_work',
        'admin.works.reports.archive',
        'admin.works.taxonomy.categories.view',
        'admin.works.taxonomy.categories.create',
        'admin.works.taxonomy.categories.update',
        'admin.works.taxonomy.categories.disable',
        'admin.works.taxonomy.tags.view',
        'admin.works.taxonomy.tags.create',
        'admin.works.taxonomy.tags.update',
        'admin.works.taxonomy.tags.disable',
        'admin.works.taxonomy.bulk_assign',
        'admin.works.taxonomy.merge_tags',
        'admin.works.bulk.publish',
        'admin.works.bulk.hide',
        'admin.works.bulk.archive',
        'admin.works.bulk.restore',
        'admin.works.bulk.category_update',
        'admin.works.bulk.tags_update',
        'admin.works.bulk.assign_reviewer',
        'admin.works.activity.list',
        'admin.works.activity.detail.view',
        'admin.works.audit.metadata.view',
        'admin.works.audit.export_denied',
        'admin.works.settings.manage',
        'admin.works.settings.workflow.manage',
        'admin.works.settings.review_sla.manage',
        'admin.works.settings.direct_publish_trust.manage',
        'admin.works.settings.media_limits.manage',
        'admin.works.search',
        'admin.works.search.private_metadata',
        'admin.works.search.designer',
        'admin.works.search.reports',
    ];

    public function test_registry_contains_the_exact_works_permission_contract_without_duplicates(): void
    {
        $registry = collect(config('yemen-motion-permissions.permissions'));
        $registeredNames = $registry->pluck('name');
        $worksPermissions = $registry
            ->filter(fn (array $permission): bool => str_starts_with($permission['name'] ?? '', 'admin.works.'));

        $this->assertCount(78, $worksPermissions);
        $this->assertSame(
            $registeredNames->count(),
            $registeredNames->unique()->count(),
            'Permission registry contains duplicate names.',
        );
        $this->assertSame(
            $this->sortedExpectedPermissions(),
            $worksPermissions->pluck('name')->sort()->values()->all(),
        );
        $this->assertSame(['admin.works'], $worksPermissions->pluck('group')->unique()->sort()->values()->all());
        $this->assertTrue(
            $worksPermissions->every(
                fn (array $permission): bool => filled($permission['label_ar'] ?? null),
            ),
        );
        $this->assertNotContains('admin.works.delete', $registeredNames);
        $this->assertNotContains('admin.works.force_delete', $registeredNames);
    }

    public function test_seeder_creates_every_works_permission_and_grants_it_to_super_admin(): void
    {
        $this->seed(AuthRolesSeeder::class);

        $databasePermissions = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', 'admin.works.%')
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $superAdminPermissions = Role::query()
            ->where('name', 'super-admin')
            ->where('guard_name', 'web')
            ->firstOrFail()
            ->permissions()
            ->where('name', 'like', 'admin.works.%')
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($this->sortedExpectedPermissions(), $databasePermissions);
        $this->assertSame($this->sortedExpectedPermissions(), $superAdminPermissions);
    }

    public function test_seeder_does_not_grant_works_permissions_to_other_baseline_roles(): void
    {
        $this->seed(AuthRolesSeeder::class);

        foreach (['admin', 'staff', 'client', 'designer'] as $roleName) {
            $worksPermissions = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->firstOrFail()
                ->permissions()
                ->where('name', 'like', 'admin.works.%')
                ->pluck('name')
                ->sort()
                ->values()
                ->all();

            $this->assertSame(
                [],
                $worksPermissions,
                "{$roleName} must not receive baseline works permissions.",
            );
        }
    }

    /**
     * @return list<string>
     */
    private function sortedExpectedPermissions(): array
    {
        return collect(self::EXPECTED_WORKS_PERMISSIONS)
            ->sort()
            ->values()
            ->all();
    }
}
