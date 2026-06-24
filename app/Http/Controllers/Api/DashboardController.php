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
}
