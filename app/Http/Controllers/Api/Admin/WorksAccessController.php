<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksAccessRequest;
use Illuminate\Http\JsonResponse;

class WorksAccessController extends Controller
{
    /**
     * @var list<array{key: string, label_ar: string, route: string, permission: string}>
     */
    private const SECTIONS = [
        [
            'key' => 'overview',
            'label_ar' => 'النظرة العامة',
            'route' => '/admin/works',
            'permission' => 'admin.works.overview.view',
        ],
        [
            'key' => 'all',
            'label_ar' => 'كل الأعمال',
            'route' => '/admin/works/all',
            'permission' => 'admin.works.all.view',
        ],
        [
            'key' => 'review',
            'label_ar' => 'طلبات المراجعة',
            'route' => '/admin/works/review',
            'permission' => 'admin.works.review.view',
        ],
        [
            'key' => 'visibility',
            'label_ar' => 'الظهور والتمييز',
            'route' => '/admin/works/visibility',
            'permission' => 'admin.works.visibility.view',
        ],
        [
            'key' => 'reports',
            'label_ar' => 'البلاغات والمخالفات',
            'route' => '/admin/works/reports',
            'permission' => 'admin.works.reports.view',
        ],
        [
            'key' => 'taxonomy',
            'label_ar' => 'التصنيفات والوسوم',
            'route' => '/admin/works/taxonomy',
            'permission' => 'admin.works.taxonomy.view',
        ],
        [
            'key' => 'activity',
            'label_ar' => 'سجل الأعمال',
            'route' => '/admin/works/activity',
            'permission' => 'admin.works.activity.view',
        ],
        [
            'key' => 'settings',
            'label_ar' => 'إعدادات وصلاحيات الأعمال',
            'route' => '/admin/works/settings',
            'permission' => 'admin.works.settings.view',
        ],
    ];

    public function index(WorksAccessRequest $request): JsonResponse
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('super-admin');
        $sections = [];
        $permissions = [
            'admin.works.access' => true,
        ];

        // نعيد الأقسام المسموحة فقط، ونبقي خريطة الصلاحيات محدودة باحتياجات Sidebar.
        foreach (self::SECTIONS as $section) {
            if (! $isSuperAdmin && ! $user->can($section['permission'])) {
                continue;
            }

            $sections[] = [
                ...$section,
                'allowed' => true,
            ];
            $permissions[$section['permission']] = true;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'base_route' => '/admin/works',
                'sidebar_mode' => 'contextual',
                'sections' => $sections,
                'permissions' => $permissions,
            ],
            'message' => 'تم جلب صلاحيات الوصول إلى إدارة الأعمال بنجاح',
            'errors' => null,
        ]);
    }
}
