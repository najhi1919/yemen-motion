<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
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

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        // Get user roles as an array of role names
        $userRoles = $user->roles->pluck('name')->toArray();

        $validPeriods = ['day', 'week', 'month', 'year'];
        $period = $request->input('period', 'month');

        // Validate period input
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

        // Define all possible sections with their properties and role access
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

            // In a real scenario, this would include a full permission check (e.g., $user->can($sectionConfig['permission']))
            // For this task, direct role check is sufficient as per spec.

            if ($canView) {
                $sections[] = array_merge($sectionConfig, ['is_active' => true, 'permission' => null]); // Simplified permission for now

                // Add a basic card for each visible section
                $cards[] = [
                    'key' => $key,
                    'label' => $sectionConfig['label'],
                    'value' => $key === 'users' ? User::count() : 0, // Real users count for 'users', 0 for others
                    'change' => 0,
                    'trend' => 'neutral',
                    'section' => $key,
                ];

                // Add a basic chart placeholder for each visible section
                $charts[] = [
                    'key' => $key,
                    'type' => 'bar',
                    'section' => $key,
                    'points' => [
                        ['label' => 'اليوم', 'value' => 0],
                        ['label' => 'الأسبوع', 'value' => 0],
                        ['label' => 'الشهر', 'value' => 0],
                    ],
                ];
            }
        }

        // Basic activities placeholder for admin/staff
        if ($role === 'admin' || $role === 'staff') {
            $activities = [
                ['key' => 'activity-1', 'label' => ['ar' => 'نشاط تجريبي', 'en' => 'Demo Activity'], 'time' => 'منذ ساعة']
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
