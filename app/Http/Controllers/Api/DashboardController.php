<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $this->authorizeDashboardPermission($request, 'dashboard.stats.view');

        $user = $request->user();
        $role = $user->roles->first()?->name;

        $totalUsers = User::count();
        $totalClients = User::whereHas('roles', fn($q) => $q->where('name', 'client'))->count();
        $totalDesigners = User::whereHas('roles', fn($q) => $q->where('name', 'designer'))->count();
        $totalStaff = User::whereHas('roles', fn($q) => $q->where('name', 'staff'))->count();
        $totalAdmins = User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->count();

        $newUsersToday = User::whereDate('created_at', now()->toDateString())->count();
        $newUsersThisWeek = User::where('created_at', '>=', now()->subWeek())->count();
        $newUsersThisMonth = User::where('created_at', '>=', now()->subMonth())->count();

        $stats = [
            'total_users' => $totalUsers,
            'total_clients' => $totalClients,
            'total_designers' => $totalDesigners,
            'total_staff' => $totalStaff,
            'total_admins' => $totalAdmins,
            'new_users_today' => $newUsersToday,
            'new_users_this_week' => $newUsersThisWeek,
            'new_users_this_month' => $newUsersThisMonth,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'تم جلب الإحصائيات بنجاح',
            'errors' => null,
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        $this->authorizeDashboardPermission($request, 'dashboard.activity.view');

        $recentUsers = User::latest('created_at')
            ->take(10)
            ->get()
            ->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
                'created_at' => $user->created_at->toISOString(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'activities' => $recentUsers,
            ],
            'message' => 'تم جلب النشاط الأخير بنجاح',
            'errors' => null,
        ]);
    }

    public function chart(Request $request): JsonResponse
    {
        $this->authorizeDashboardPermission($request, 'dashboard.chart.view');

        $days = 30;
        $labels = [];
        $newUsers = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('Y-m-d');
            $newUsers[] = User::whereDate('created_at', $date)->count();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'new_users' => $newUsers,
            ],
            'message' => 'تم جلب بيانات الرسم البياني بنجاح',
            'errors' => null,
        ]);
    }

    private function authorizeDashboardPermission(Request $request, string $permission): void
    {
        $viewer = $request->user();

        if (! $viewer || ! $viewer->can($permission)) {
            abort(403, 'غير مصرح لك بعرض بيانات لوحة التحكم.');
        }
    }

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $userRoles = $user->roles->pluck('name')->toArray();

        $validPeriods = ['day', 'week', 'month', 'year'];
        $period = $request->input('period', 'month');

        if (!in_array($period, $validPeriods)) {
            return response()->json(['message' => 'Invalid period provided'], 422);
        }

        $role = 'other';
        if (in_array('admin', $userRoles) || in_array('super-admin', $userRoles)) {
            $role = 'admin';
        } elseif (in_array('staff', $userRoles)) {
            $role = 'staff';
        }

        $sections = [];
        $cards = [];
        $charts = [];
        $activities = [];

        $protectedRoleNames = config('yemen-motion-permissions.protected_roles', []);
        $systemPermissionNames = collect(config('yemen-motion-permissions.permissions', []))
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        $totalUsers = User::count();
        $totalAdmins = User::whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'super-admin']))->count();
        $totalStaff = User::whereHas('roles', fn($query) => $query->where('name', 'staff'))->count();
        $totalClients = User::whereHas('roles', fn($query) => $query->where('name', 'client'))->count();
        $totalDesigners = User::whereHas('roles', fn($query) => $query->where('name', 'designer'))->count();

        $totalRoles = Role::query()->count();
        $protectedRoles = Role::query()
            ->whereIn('name', $protectedRoleNames)
            ->count();
        $customRoles = max(0, $totalRoles - $protectedRoles);

        $totalPermissions = Permission::query()->count();
        $systemPermissions = Permission::query()
            ->when($systemPermissionNames !== [], fn($query) => $query->whereIn('name', $systemPermissionNames))
            ->when($systemPermissionNames === [], fn($query) => $query->whereRaw('1 = 0'))
            ->count();
        $customPermissions = max(0, $totalPermissions - $systemPermissions);
        $dashboardPermissions = Permission::query()
            ->where('name', 'like', 'dashboard.%')
            ->count();
        $adminPermissions = Permission::query()
            ->where('name', 'like', 'admin.%')
            ->count();

        $cardValues = [
            'users' => $totalUsers,
            'orders' => 0,
            'works' => 0,
            'contests' => 0,
            'wallet' => 0,
            'works_review' => 0,
            'reports' => 0,
            'activities_feed' => 0,
            'overview' => $totalUsers,
            'staff' => $totalStaff,
            'roles' => $totalRoles,
            'permissions' => $totalPermissions,
            'access' => $totalRoles + $totalPermissions,
        ];

        $chartPoints = [
            'users' => [
                ['label' => 'المستخدمون', 'value' => $totalUsers],
                ['label' => 'الإداريون', 'value' => $totalAdmins],
                ['label' => 'الموظفون', 'value' => $totalStaff],
                ['label' => 'العملاء', 'value' => $totalClients],
                ['label' => 'المصممون', 'value' => $totalDesigners],
            ],
            'staff' => [
                ['label' => 'الموظفون', 'value' => $totalStaff],
                ['label' => 'الإداريون', 'value' => $totalAdmins],
            ],
            'roles' => [
                ['label' => 'الأدوار', 'value' => $totalRoles],
                ['label' => 'المحمية', 'value' => $protectedRoles],
                ['label' => 'المخصصة', 'value' => $customRoles],
            ],
            'permissions' => [
                ['label' => 'الصلاحيات', 'value' => $totalPermissions],
                ['label' => 'النظامية', 'value' => $systemPermissions],
                ['label' => 'المخصصة', 'value' => $customPermissions],
            ],
            'access' => [
                ['label' => 'الأدوار', 'value' => $totalRoles],
                ['label' => 'الصلاحيات', 'value' => $totalPermissions],
                ['label' => 'صلاحيات لوحة التحكم', 'value' => $dashboardPermissions],
                ['label' => 'صلاحيات الإدارة', 'value' => $adminPermissions],
            ],
            'overview' => [
                ['label' => 'المستخدمون', 'value' => $totalUsers],
                ['label' => 'الأدوار', 'value' => $totalRoles],
                ['label' => 'الصلاحيات', 'value' => $totalPermissions],
            ],
        ];

        $allSections = [
            'users' => [
                'key' => 'users',
                'label' => ['ar' => 'المستخدمون', 'en' => 'Users'],
                'icon' => 'user-group',
                'color' => '#10b981',
                'is_admin_only' => true,
            ],
            'orders' => [
                'key' => 'orders',
                'label' => ['ar' => 'الطلبات', 'en' => 'Orders'],
                'icon' => 'shopping-cart',
                'color' => '#6366f1',
                'is_admin_only' => true,
            ],
            'works' => [
                'key' => 'works',
                'label' => ['ar' => 'الأعمال', 'en' => 'Works'],
                'icon' => 'briefcase',
                'color' => '#3b82f6',
                'is_admin_only' => true,
            ],
            'contests' => [
                'key' => 'contests',
                'label' => ['ar' => 'المسابقات', 'en' => 'Contests'],
                'icon' => 'trophy',
                'color' => '#8b5cf6',
                'is_admin_only' => true,
            ],
            'wallet' => [
                'key' => 'wallet',
                'label' => ['ar' => 'المحفظة', 'en' => 'Wallet'],
                'icon' => 'wallet',
                'color' => '#22c55e',
                'is_admin_only' => true,
            ],
            'staff' => [
                'key' => 'staff',
                'label' => ['ar' => 'الفريق', 'en' => 'Staff'],
                'icon' => 'users',
                'color' => '#14b8a6',
                'is_admin_only' => true,
            ],
            'roles' => [
                'key' => 'roles',
                'label' => ['ar' => 'الأدوار', 'en' => 'Roles'],
                'icon' => 'shield-check',
                'color' => '#8b5cf6',
                'is_admin_only' => true,
            ],
            'permissions' => [
                'key' => 'permissions',
                'label' => ['ar' => 'الصلاحيات', 'en' => 'Permissions'],
                'icon' => 'key',
                'color' => '#0ea5e9',
                'is_admin_only' => true,
            ],
            'access' => [
                'key' => 'access',
                'label' => ['ar' => 'إدارة الوصول', 'en' => 'Access Management'],
                'icon' => 'rectangle-group',
                'color' => '#f97316',
                'is_admin_only' => true,
            ],
            'works_review' => [
                'key' => 'works_review',
                'label' => ['ar' => 'مراجعة الأعمال', 'en' => 'Works Review'],
                'icon' => 'clipboard-check',
                'color' => '#f59e0b',
                'is_admin_only' => false,
            ],
            'reports' => [
                'key' => 'reports',
                'label' => ['ar' => 'البلاغات', 'en' => 'Reports'],
                'icon' => 'flag',
                'color' => '#ef4444',
                'is_admin_only' => false,
            ],
            'activities_feed' => [
                'key' => 'activities_feed',
                'label' => ['ar' => 'النشاطات', 'en' => 'Activities'],
                'icon' => 'rectangle-group',
                'color' => '#0ea5e9',
                'is_admin_only' => false,
            ],
            // Minimal section for 'other' roles
            'overview' => [
                'key' => 'overview',
                'label' => ['ar' => 'نظرة عامة', 'en' => 'Overview'],
                'icon' => 'chart-bar',
                'color' => '#94a3b8',
                'is_admin_only' => false, // Accessible by all
            ],
        ];

        foreach ($allSections as $key => $sectionConfig) {
            $canView = false;
            if ($role === 'admin') {
                $canView = true; // Admin sees all sections
            } elseif ($role === 'staff' && !$sectionConfig['is_admin_only']) {
                $canView = true; // Staff sees staff-safe sections
            } elseif ($role === 'other' && $key === 'overview') {
                $canView = true; // 'Other' roles only see a minimal overview
            }

            if ($canView) {
                $sections[] = array_merge($sectionConfig, ['is_active' => true, 'permission' => null]); // Simplified permission for now

                $cards[] = [
                    'key' => $key,
                    'label' => $sectionConfig['label'],
                    'value' => $cardValues[$key] ?? 0,
                    'change' => 0,
                    'trend' => 'neutral',
                    'section' => $key,
                ];

                $charts[] = [
                    'key' => $key,
                    'type' => 'bar',
                    'section' => $key,
                    'points' => $chartPoints[$key] ?? [
                        ['label' => $sectionConfig['label']['ar'], 'value' => $cardValues[$key] ?? 0],
                    ],
                ];
            }
        }

        if ($role === 'admin' || $role === 'staff') {
            $activities = [
                [
                    'key' => 'access-data-synced',
                    'label' => [
                        'ar' => 'تم تحديث مؤشرات مركز الوصول من قاعدة البيانات',
                        'en' => 'Access management indicators were loaded from the database',
                    ],
                    'time' => 'الآن',
                    'icon' => 'rectangle-group',
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'period' => $period,
                'sections' => $sections,
                'cards' => $cards,
                'charts' => $charts,
                'activities' => $activities,
            ],
            'message' => 'تم جلب ملخص لوحة التحكم',
            'errors' => null,
            'meta' => [
                'periods' => $validPeriods,
                'selected_period' => $period,
            ],
        ]);
    }
}
