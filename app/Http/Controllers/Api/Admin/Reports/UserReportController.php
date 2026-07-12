<?php

namespace App\Http\Controllers\Api\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\UserReportRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class UserReportController extends Controller
{
    public function __invoke(UserReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $role = $validated['role'] ?? null;
        $period = $validated['period'] ?? 'day';

        $populationQuery = User::query()
            ->when($role, fn (Builder $query, string $roleName) => $this->filterByRole($query, $roleName));

        $rangeQuery = (clone $populationQuery)
            ->when(
                $validated['from'] ?? null,
                fn (Builder $query, string $from) => $query->whereDate('users.created_at', '>=', $from),
            )
            ->when(
                $validated['to'] ?? null,
                fn (Builder $query, string $to) => $query->whereDate('users.created_at', '<=', $to),
            );

        $data = [
            'summary' => [
                'total_users' => (clone $populationQuery)->count(),
                'users_in_range' => (clone $rangeQuery)->count(),
                'verified_users' => (clone $rangeQuery)->whereNotNull('email_verified_at')->count(),
                'unverified_users' => (clone $rangeQuery)->whereNull('email_verified_at')->count(),
            ],
            'role_breakdown' => $this->roleBreakdown($rangeQuery, $role),
            'registrations_series' => $this->registrationsSeries($rangeQuery, $period),
            'filters' => [
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'role' => $role,
                'period' => $period,
            ],
            'generated_at' => now()->toJSON(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'تم إنشاء تقرير المستخدمين بنجاح',
            'errors' => null,
        ]);
    }

    private function filterByRole(Builder $query, string $role): Builder
    {
        return $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery
            ->where('roles.name', $role)
            ->where('roles.guard_name', 'web'));
    }

    /**
     * يبني توزيع الأدوار من العدادات فقط دون تحميل المستخدمين أو بيانات pivot.
     *
     * @return list<array{role: string, count: int}>
     */
    private function roleBreakdown(Builder $rangeQuery, ?string $selectedRole): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->when($selectedRole, fn (Builder $query, string $role) => $query->where('name', $role))
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (string $role): array => [
                'role' => $role,
                'count' => (clone $rangeQuery)
                    ->whereHas('roles', fn (Builder $roleQuery) => $roleQuery
                        ->where('roles.name', $role)
                        ->where('roles.guard_name', 'web'))
                    ->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * نجمع الحقل الزمني وحده في PHP لتجنب دوال SQL الخاصة بمحرك قاعدة بيانات بعينه.
     *
     * @return list<array{period: string, count: int}>
     */
    private function registrationsSeries(Builder $rangeQuery, string $period): array
    {
        $counts = [];

        foreach ((clone $rangeQuery)->select('users.created_at')->orderBy('users.created_at')->cursor() as $user) {
            if (! $user->created_at) {
                continue;
            }

            $bucket = $this->periodBucket($user->created_at, $period);
            $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
        }

        ksort($counts);

        return collect($counts)
            ->map(fn (int $count, string $bucket): array => [
                'period' => $bucket,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    private function periodBucket(CarbonInterface $date, string $period): string
    {
        return match ($period) {
            'week' => $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'month' => $date->format('Y-m'),
            'year' => $date->format('Y'),
            default => $date->toDateString(),
        };
    }
}
