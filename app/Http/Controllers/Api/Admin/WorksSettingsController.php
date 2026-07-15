<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksSettingsRequest;
use App\Models\Work;
use Illuminate\Http\JsonResponse;

class WorksSettingsController extends Controller
{
    /**
     * @var list<string>
     */
    private const INTERNAL_ROLES = [
        'super-admin',
        'admin',
        'staff',
    ];

    /**
     * @var list<string>
     */
    private const FORBIDDEN_ROLES = [
        'client',
        'designer',
    ];

    /**
     * @var list<string>
     */
    private const LIFECYCLE_EVENTS = [
        'created',
        'updated',
        'submitted',
        'reviewed',
        'approved',
        'published',
        'rejected',
        'hidden',
        'archived',
    ];

    /**
     * @var array<string, string>
     */
    private const SECTION_LABELS = [
        'navigation' => 'الوصول والتنقل',
        'read_detail' => 'القائمة وتفاصيل القراءة',
        'content_management' => 'إنشاء وتحديث المحتوى',
        'review' => 'المراجعة',
        'visibility' => 'الظهور والتمييز',
        'reports' => 'البلاغات',
        'taxonomy' => 'التصنيفات والوسوم',
        'bulk' => 'الإجراءات الجماعية',
        'activity_audit' => 'النشاط والتدقيق',
        'settings' => 'الإعدادات',
        'search' => 'البحث',
    ];

    /**
     * @var list<string>
     */
    private const READ_DETAIL_PERMISSIONS = [
        'admin.works.list',
        'admin.works.detail.view',
        'admin.works.media.view',
        'admin.works.metadata.view',
        'admin.works.designer.view',
        'admin.works.private_notes.view',
    ];

    /**
     * @var list<string>
     */
    private const VISIBILITY_ACTION_PERMISSIONS = [
        'admin.works.publish',
        'admin.works.unpublish',
        'admin.works.hide',
        'admin.works.restore_visibility',
        'admin.works.feature',
        'admin.works.unfeature',
        'admin.works.pin',
        'admin.works.unpin',
    ];

    /**
     * @var array<string, string>
     */
    private const CAPABILITY_PERMISSIONS = [
        'can_view_settings' => 'admin.works.settings.view',
        'can_manage_settings' => 'admin.works.settings.manage',
        'can_manage_workflow' => 'admin.works.settings.workflow.manage',
        'can_manage_review_sla' => 'admin.works.settings.review_sla.manage',
        'can_manage_direct_publish_trust' => 'admin.works.settings.direct_publish_trust.manage',
        'can_manage_media_limits' => 'admin.works.settings.media_limits.manage',
    ];

    public function index(WorksSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'settings_support' => [
                    'persistent_settings_available' => false,
                    'source' => 'static_defaults_and_registered_permissions',
                    'reason' => 'لا يوجد جدول إعدادات أعمال مستقل حاليًا؛ هذه القراءة تعرض الوضع الحالي والصلاحيات المسجلة فقط.',
                ],
                'access_model' => [
                    'internal_roles' => self::INTERNAL_ROLES,
                    'forbidden_roles' => self::FORBIDDEN_ROLES,
                    'super_admin_has_all_permissions' => true,
                    'client_designer_forbidden_even_if_granted' => true,
                ],
                'workflow' => $this->workflow(),
                'permission_registry' => $this->permissionRegistry(),
                'current_user_capabilities' => $this->currentUserCapabilities($request),
                'management_support' => [
                    'settings_mutation_available' => false,
                    'workflow_mutation_available' => false,
                    'review_sla_mutation_available' => false,
                    'direct_publish_trust_mutation_available' => false,
                    'media_limits_mutation_available' => false,
                    'reason' => 'واجهات الحفظ والتعديل غير مبنية في هذه المرحلة.',
                ],
            ],
            'message' => 'تم جلب إعدادات وصلاحيات الأعمال بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * @return array<string, list<string>|string>
     */
    private function workflow(): array
    {
        return [
            'statuses' => [
                Work::STATUS_DRAFT,
                Work::STATUS_SUBMITTED,
                Work::STATUS_IN_REVIEW,
                Work::STATUS_CHANGES_REQUESTED,
                Work::STATUS_APPROVED,
                Work::STATUS_PUBLISHED,
                Work::STATUS_REJECTED,
                Work::STATUS_HIDDEN,
                Work::STATUS_ARCHIVED,
            ],
            'visibility_statuses' => [
                Work::VISIBILITY_HIDDEN,
                Work::VISIBILITY_PUBLIC,
            ],
            'lifecycle_events' => self::LIFECYCLE_EVENTS,
            'review_queue_statuses' => [
                Work::STATUS_SUBMITTED,
                Work::STATUS_IN_REVIEW,
                Work::STATUS_CHANGES_REQUESTED,
            ],
            'derived_from' => 'App\\Models\\Work constants and current works API contracts',
        ];
    }

    /**
     * @return array{group: string, total_permissions: int, sections: list<array{key: string, label: string, permissions: list<array<string, string>>}>}
     */
    private function permissionRegistry(): array
    {
        $registeredPermissions = collect(config('yemen-motion-permissions.permissions', []))
            ->filter(fn (mixed $permission): bool => is_array($permission)
                && ($permission['group'] ?? null) === 'admin.works'
                && is_string($permission['name'] ?? null))
            ->values();

        $sectionPermissions = collect(array_keys(self::SECTION_LABELS))
            ->mapWithKeys(fn (string $key): array => [$key => []]);

        foreach ($registeredPermissions as $permission) {
            $name = $permission['name'];
            $item = ['name' => $name];

            if (is_string($permission['label'] ?? null)) {
                $item['label'] = $permission['label'];
            } elseif (is_string($permission['label_ar'] ?? null)) {
                $item['label'] = $permission['label_ar'];
            }

            if (is_string($permission['description'] ?? null)) {
                $item['description'] = $permission['description'];
            } elseif (is_string($permission['description_ar'] ?? null)) {
                $item['description'] = $permission['description_ar'];
            }

            $sectionKey = $this->permissionSection($name);
            $sectionPermissions[$sectionKey] = [
                ...$sectionPermissions[$sectionKey],
                $item,
            ];
        }

        $sections = $sectionPermissions
            ->map(fn (array $permissions, string $key): array => [
                'key' => $key,
                'label' => self::SECTION_LABELS[$key],
                'permissions' => $permissions,
            ])
            ->values()
            ->all();

        return [
            'group' => 'admin.works',
            'total_permissions' => $registeredPermissions->count(),
            'sections' => $sections,
        ];
    }

    private function permissionSection(string $name): string
    {
        if ($name === 'admin.works.access'
            || preg_match('/^admin\.works\.(overview|all|review|visibility|reports|taxonomy|activity|settings)\.view$/', $name) === 1) {
            return 'navigation';
        }

        if (in_array($name, self::READ_DETAIL_PERMISSIONS, true)) {
            return 'read_detail';
        }

        if ($name === 'admin.works.create' || str_starts_with($name, 'admin.works.update.')) {
            return 'content_management';
        }

        if (str_starts_with($name, 'admin.works.review.')) {
            return 'review';
        }

        if (in_array($name, self::VISIBILITY_ACTION_PERMISSIONS, true)
            || str_starts_with($name, 'admin.works.visibility.')) {
            return 'visibility';
        }

        if (str_starts_with($name, 'admin.works.reports.')) {
            return 'reports';
        }

        if (str_starts_with($name, 'admin.works.taxonomy.')) {
            return 'taxonomy';
        }

        if (str_starts_with($name, 'admin.works.bulk.')) {
            return 'bulk';
        }

        if (str_starts_with($name, 'admin.works.activity.')
            || str_starts_with($name, 'admin.works.audit.')) {
            return 'activity_audit';
        }

        if (str_starts_with($name, 'admin.works.settings.')) {
            return 'settings';
        }

        if ($name === 'admin.works.search' || str_starts_with($name, 'admin.works.search.')) {
            return 'search';
        }

        return 'content_management';
    }

    /**
     * @return array<string, bool>
     */
    private function currentUserCapabilities(WorksSettingsRequest $request): array
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('super-admin');
        $capabilities = [];

        foreach (self::CAPABILITY_PERMISSIONS as $capability => $permission) {
            $capabilities[$capability] = $isSuperAdmin || $user->can($permission);
        }

        return $capabilities;
    }
}
