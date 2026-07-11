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
        $this->authorizeDashboardPermission($request, 'dashboard.overview.view');

        $user = $request->user();

        if (! $user->hasAnyRole(['super-admin', 'admin', 'staff'])) {
            abort(403, 'غير مصرح لك بعرض بيانات لوحة التحكم.');
        }

        $userRoles = $user->roles->pluck('name')->toArray();
        $isSuperAdmin = $user->hasRole('super-admin');

        $validPeriods = ['day', 'week', 'month', 'year'];
        $period = $request->input('period', 'month');

        if (!in_array($period, $validPeriods)) {
            return response()->json(['message' => 'Invalid period provided'], 422);
        }

        $periodRange = $this->dashboardPeriodRange($period);

        $role = 'other';
        if (in_array('admin', $userRoles) || in_array('super-admin', $userRoles)) {
            $role = 'admin';
        } elseif (in_array('staff', $userRoles)) {
            $role = 'staff';
        }

        $sections = [];
        $cards = [];
        $charts = [];
        $activities = $this->dashboardActivitiesForUser($user);

        $timeBuckets = $this->dashboardTimeBuckets($period, $periodRange);
        $userCreatedAt = User::query()
            ->whereBetween('created_at', [$periodRange['start'], $periodRange['end']])
            ->get(['created_at'])
            ->map(fn(User $user) => $user->created_at)
            ->all();
        $roleCreatedAt = Role::query()
            ->whereBetween('created_at', [$periodRange['start'], $periodRange['end']])
            ->get(['created_at'])
            ->map(fn(Role $role) => $role->created_at)
            ->all();
        $permissionCreatedAt = Permission::query()
            ->whereBetween('created_at', [$periodRange['start'], $periodRange['end']])
            ->get(['created_at'])
            ->map(fn(Permission $permission) => $permission->created_at)
            ->all();

        $periodUsers = count($userCreatedAt);
        $periodRoles = count($roleCreatedAt);
        $periodPermissions = count($permissionCreatedAt);
        $totalAdmins = User::whereHas('roles', fn($query) => $query->whereIn('name', ['admin', 'super-admin']))->count();
        $totalStaff = User::whereHas('roles', fn($query) => $query->where('name', 'staff'))->count();

        $userTimeSeriesPoints = $this->cumulativeTimeSeriesPoints($userCreatedAt, $timeBuckets);
        $roleTimeSeriesPoints = $this->cumulativeTimeSeriesPoints($roleCreatedAt, $timeBuckets);
        $permissionTimeSeriesPoints = $this->cumulativeTimeSeriesPoints($permissionCreatedAt, $timeBuckets);
        $accessTimeSeriesPoints = array_map(
            fn(array $rolePoint, array $permissionPoint) => [
                'label' => $rolePoint['label'],
                'value' => $rolePoint['value'] + $permissionPoint['value'],
            ],
            $roleTimeSeriesPoints,
            $permissionTimeSeriesPoints,
        );

        $cardValues = [
            'users' => $periodUsers,
            'orders' => 0,
            'works' => 0,
            'contests' => 0,
            'wallet' => 0,
            'works_review' => 0,
            'reports' => 0,
            'activities_feed' => 0,
            // Overview remains the users summary, scoped to the selected period.
            'overview' => $periodUsers,
            'staff' => $totalStaff,
            'roles' => $periodRoles,
            'permissions' => $periodPermissions,
            'access' => $periodRoles + $periodPermissions,
        ];

        $chartPoints = [
            'users' => $userTimeSeriesPoints,
            'staff' => [
                ['label' => 'الموظفون', 'value' => $totalStaff],
                ['label' => 'الإداريون', 'value' => $totalAdmins],
            ],
            'roles' => $roleTimeSeriesPoints,
            'permissions' => $permissionTimeSeriesPoints,
            'access' => $accessTimeSeriesPoints,
            // Overview continues to mirror users, now as the same cumulative series.
            'overview' => $userTimeSeriesPoints,
        ];

        // Placeholder modules remain super-admin only until their precise permissions exist.
        $allSections = [
            'users' => [
                'key' => 'users',
                'label' => ['ar' => 'المستخدمون', 'en' => 'Users'],
                'icon' => 'user-group',
                'color' => '#10b981',
                'is_admin_only' => false,
                'permission' => 'admin.users.view',
                'is_live' => true,
                'is_placeholder' => false,
            ],
            'orders' => [
                'key' => 'orders',
                'label' => ['ar' => 'الطلبات', 'en' => 'Orders'],
                'icon' => 'shopping-cart',
                'color' => '#6366f1',
                'is_admin_only' => true,
                'permission' => 'dashboard.overview.view',
                'is_live' => false,
                'is_placeholder' => true,
            ],
            'works' => [
                'key' => 'works',
                'label' => ['ar' => 'الأعمال', 'en' => 'Works'],
                'icon' => 'briefcase',
                'color' => '#3b82f6',
                'is_admin_only' => true,
                'permission' => 'dashboard.overview.view',
                'is_live' => false,
                'is_placeholder' => true,
            ],
            'contests' => [
                'key' => 'contests',
                'label' => ['ar' => 'المسابقات', 'en' => 'Contests'],
                'icon' => 'trophy',
                'color' => '#8b5cf6',
                'is_admin_only' => true,
                'permission' => 'dashboard.overview.view',
                'is_live' => false,
                'is_placeholder' => true,
            ],
            'wallet' => [
                'key' => 'wallet',
                'label' => ['ar' => 'المحفظة', 'en' => 'Wallet'],
                'icon' => 'wallet',
                'color' => '#22c55e',
                'is_admin_only' => true,
                'permission' => 'dashboard.overview.view',
                'is_live' => false,
                'is_placeholder' => true,
            ],
            'staff' => [
                'key' => 'staff',
                'label' => ['ar' => 'الفريق', 'en' => 'Staff'],
                'icon' => 'users',
                'color' => '#14b8a6',
                'is_admin_only' => true,
                'permission' => 'super-admin',
                'is_live' => true,
                'is_placeholder' => false,
            ],
            'roles' => [
                'key' => 'roles',
                'label' => ['ar' => 'الأدوار', 'en' => 'Roles'],
                'icon' => 'shield-check',
                'color' => '#8b5cf6',
                'is_admin_only' => false,
                'permission' => 'admin.roles.view',
                'is_live' => true,
                'is_placeholder' => false,
            ],
            'permissions' => [
                'key' => 'permissions',
                'label' => ['ar' => 'الصلاحيات', 'en' => 'Permissions'],
                'icon' => 'key',
                'color' => '#0ea5e9',
                'is_admin_only' => false,
                'permission' => 'admin.permissions.view',
                'is_live' => true,
                'is_placeholder' => false,
            ],
            'access' => [
                'key' => 'access',
                'label' => ['ar' => 'إدارة الوصول', 'en' => 'Access Management'],
                'icon' => 'rectangle-group',
                'color' => '#f97316',
                'is_admin_only' => false,
                'permission' => 'admin.access.view',
                'is_live' => true,
                'is_placeholder' => false,
            ],
            'works_review' => [
                'key' => 'works_review',
                'label' => ['ar' => 'مراجعة الأعمال', 'en' => 'Works Review'],
                'icon' => 'clipboard-check',
                'color' => '#f59e0b',
                'is_admin_only' => false,
                'permission' => 'dashboard.overview.view',
                'is_live' => false,
                'is_placeholder' => true,
            ],
            'reports' => [
                'key' => 'reports',
                'label' => ['ar' => 'البلاغات', 'en' => 'Reports'],
                'icon' => 'flag',
                'color' => '#ef4444',
                'is_admin_only' => false,
                'permission' => 'dashboard.overview.view',
                'is_live' => false,
                'is_placeholder' => true,
            ],
            'activities_feed' => [
                'key' => 'activities_feed',
                'label' => ['ar' => 'النشاطات', 'en' => 'Activities'],
                'icon' => 'rectangle-group',
                'color' => '#0ea5e9',
                'is_admin_only' => false,
                'permission' => 'dashboard.overview.view',
                'is_live' => false,
                'is_placeholder' => true,
            ],
            // Base section for internal roles with dashboard overview access.
            'overview' => [
                'key' => 'overview',
                'label' => ['ar' => 'نظرة عامة', 'en' => 'Overview'],
                'icon' => 'chart-bar',
                'color' => '#94a3b8',
                'is_admin_only' => false,
                'permission' => 'dashboard.overview.view',
                'is_live' => true,
                'is_placeholder' => false,
            ],
        ];

        foreach ($allSections as $key => $sectionConfig) {
            $canView = $isSuperAdmin || (
                ! $sectionConfig['is_placeholder']
                && $sectionConfig['permission'] !== 'super-admin'
                && $user->can($sectionConfig['permission'])
            );

            if ($canView) {
                $sections[] = array_merge($sectionConfig, [
                    'can_view' => true,
                    'is_active' => true,
                ]);

                $cards[] = [
                    'key' => $key,
                    'label' => $sectionConfig['label'],
                    'value' => $cardValues[$key] ?? 0,
                    'change' => 0,
                    'trend' => 'neutral',
                    'section' => $key,
                    'permission' => $sectionConfig['permission'],
                    'can_view' => true,
                    'is_admin_only' => $sectionConfig['is_admin_only'],
                    'is_live' => $sectionConfig['is_live'],
                    'is_placeholder' => $sectionConfig['is_placeholder'],
                ];

                $charts[] = [
                    'key' => $key,
                    'type' => 'bar',
                    'section' => $key,
                    'permission' => $sectionConfig['permission'],
                    'can_view' => true,
                    'is_admin_only' => $sectionConfig['is_admin_only'],
                    'is_live' => $sectionConfig['is_live'],
                    'is_placeholder' => $sectionConfig['is_placeholder'],
                    'points' => $chartPoints[$key] ?? [
                        ['label' => $sectionConfig['label']['ar'], 'value' => $cardValues[$key] ?? 0],
                    ],
                ];
            }
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

    private function dashboardPeriodRange(string $period): array
    {
        $current = now();

        return match ($period) {
            'day' => [
                'start' => $current->copy()->startOfDay(),
                'end' => $current->copy()->endOfDay(),
            ],
            'week' => [
                'start' => $current->copy()->startOfWeek(),
                'end' => $current->copy()->endOfWeek(),
            ],
            'month' => [
                'start' => $current->copy()->startOfMonth(),
                'end' => $current->copy()->endOfMonth(),
            ],
            'year' => [
                'start' => $current->copy()->startOfYear(),
                'end' => $current->copy()->endOfYear(),
            ],
        };
    }

    private function dashboardTimeBuckets(string $period, array $periodRange): array
    {
        $buckets = [];
        $cursor = $periodRange['start']->copy();

        while ($cursor->lte($periodRange['end'])) {
            $start = $cursor->copy();
            $end = match ($period) {
                'day' => $start->copy()->endOfHour(),
                'week', 'month' => $start->copy()->endOfDay(),
                'year' => $start->copy()->endOfMonth(),
            };

            if ($end->gt($periodRange['end'])) {
                $end = $periodRange['end']->copy();
            }

            $buckets[] = [
                'label' => match ($period) {
                    'day' => $start->format('H:00'),
                    'week', 'month' => $start->format('Y-m-d'),
                    'year' => $start->format('Y-m'),
                },
                'start' => $start,
                'end' => $end,
            ];

            $cursor = match ($period) {
                'day' => $start->copy()->addHour()->startOfHour(),
                'week', 'month' => $start->copy()->addDay()->startOfDay(),
                'year' => $start->copy()->addMonth()->startOfMonth(),
            };
        }

        return $buckets;
    }

    private function cumulativeTimeSeriesPoints(array $createdAtValues, array $buckets): array
    {
        $cumulative = 0;
        $points = [];

        foreach ($buckets as $bucket) {
            foreach ($createdAtValues as $createdAt) {
                if ($createdAt->gte($bucket['start']) && $createdAt->lte($bucket['end'])) {
                    $cumulative++;
                }
            }

            $points[] = [
                'label' => $bucket['label'],
                'value' => $cumulative,
            ];
        }

        return $points;
    }

    private function dashboardActivitiesForUser(User $user): array
    {
        $isSuperAdmin = $user->hasRole('super-admin');

        $userActivities = collect();
        if ($isSuperAdmin || $user->can('admin.users.view')) {
            $userActivities = User::query()
                ->latest('created_at')
                ->take(4)
                ->get()
                ->map(fn(User $user) => [
                    'key' => "user-created-{$user->id}",
                    'label' => [
                        'ar' => "تم إنشاء مستخدم جديد: {$user->name}",
                        'en' => "New user created: {$user->name}",
                    ],
                    'time' => optional($user->created_at)->format('Y-m-d H:i'),
                    'icon' => 'user-group',
                    'sort_at' => optional($user->created_at)->getTimestamp() ?? 0,
                ]);
        }

        $roleActivities = collect();
        if ($isSuperAdmin || $user->can('admin.roles.view')) {
            $roleActivities = Role::query()
                ->latest('created_at')
                ->take(3)
                ->get()
                ->map(fn(Role $role) => [
                    'key' => "role-created-{$role->id}",
                    'label' => [
                        'ar' => "تم إنشاء دور: {$role->name}",
                        'en' => "Role created: {$role->name}",
                    ],
                    'time' => optional($role->created_at)->format('Y-m-d H:i'),
                    'icon' => 'shield-check',
                    'sort_at' => optional($role->created_at)->getTimestamp() ?? 0,
                ]);
        }

        $permissionActivities = collect();
        if ($isSuperAdmin || $user->can('admin.permissions.view')) {
            $permissionActivities = Permission::query()
                ->latest('created_at')
                ->take(3)
                ->get()
                ->map(fn(Permission $permission) => [
                    'key' => "permission-created-{$permission->id}",
                    'label' => [
                        'ar' => "تم إنشاء صلاحية: {$permission->name}",
                        'en' => "Permission created: {$permission->name}",
                    ],
                    'time' => optional($permission->created_at)->format('Y-m-d H:i'),
                    'icon' => 'key',
                    'sort_at' => optional($permission->created_at)->getTimestamp() ?? 0,
                ]);
        }

        return $userActivities
            ->concat($roleActivities)
            ->concat($permissionActivities)
            ->sortByDesc('sort_at')
            ->take(8)
            ->map(function (array $activity) {
                unset($activity['sort_at']);

                return $activity;
            })
            ->values()
            ->all();
    }
}
