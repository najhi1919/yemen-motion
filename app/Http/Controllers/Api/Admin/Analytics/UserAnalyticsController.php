<?php

namespace App\Http\Controllers\Api\Admin\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Analytics\UserAnalyticsRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class UserAnalyticsController extends Controller
{
    public function __invoke(UserAnalyticsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $role = $validated['role'] ?? null;
        $period = $validated['period'] ?? 'day';
        $ranges = $this->comparisonRanges($validated);

        $baseQuery = User::query()
            ->when($role, fn (Builder $query, string $roleName) => $this->filterByRole($query, $roleName));

        $currentQuery = $this->withinRange(
            clone $baseQuery,
            $ranges['current_from'],
            $ranges['current_to'],
        );
        $previousQuery = $this->withinRange(
            clone $baseQuery,
            $ranges['previous_from'],
            $ranges['previous_to'],
        );

        $currentUsers = (clone $currentQuery)->count();
        $previousUsers = (clone $previousQuery)->count();
        $verifiedUsers = (clone $currentQuery)->whereNotNull('email_verified_at')->count();
        $unverifiedUsers = $currentUsers - $verifiedUsers;

        $data = [
            'summary' => [
                'current_period_users' => $currentUsers,
                'previous_period_users' => $previousUsers,
                'absolute_change' => $currentUsers - $previousUsers,
                'percentage_change' => $this->percentageChange($currentUsers, $previousUsers),
                'verified_rate' => $this->percentage($verifiedUsers, $currentUsers),
                'unverified_rate' => $this->percentage($unverifiedUsers, $currentUsers),
            ],
            'trend' => $this->trend($currentQuery, $period),
            'role_mix' => $this->roleMix($currentQuery, $role, $currentUsers),
            'comparison' => [
                'current_from' => $ranges['current_from']->toDateString(),
                'current_to' => $ranges['current_to']->toDateString(),
                'previous_from' => $ranges['previous_from']->toDateString(),
                'previous_to' => $ranges['previous_to']->toDateString(),
                'period' => $period,
                'role' => $role,
            ],
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
            'message' => 'تم إنشاء تحليلات المستخدمين بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * ينشئ مدى حاليًا مغلقًا ومدى سابقًا مساويًا له في عدد الأيام.
     * عند غياب الحدين يكون كل مدى ثلاثين يومًا، وعند غياب حد واحد يستكمل ثلاثين يومًا منه.
     *
     * @param array<string, mixed> $validated
     * @return array{current_from: Carbon, current_to: Carbon, previous_from: Carbon, previous_to: Carbon}
     */
    private function comparisonRanges(array $validated): array
    {
        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : null;
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->startOfDay()
            : null;

        if (! $from && ! $to) {
            $to = now()->startOfDay();
            $from = $to->copy()->subDays(29);
        } elseif ($from && ! $to) {
            $to = $from->copy()->addDays(29);
        } elseif (! $from && $to) {
            $from = $to->copy()->subDays(29);
        }

        /** @var Carbon $from */
        /** @var Carbon $to */
        $durationDays = (int) $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($durationDays - 1);

        return [
            'current_from' => $from,
            'current_to' => $to,
            'previous_from' => $previousFrom,
            'previous_to' => $previousTo,
        ];
    }

    private function filterByRole(Builder $query, string $role): Builder
    {
        return $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery
            ->where('roles.name', $role)
            ->where('roles.guard_name', 'web'));
    }

    private function withinRange(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->whereBetween('users.created_at', [
            $from->copy()->startOfDay(),
            $to->copy()->endOfDay(),
        ]);
    }

    private function percentageChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function percentage(int $count, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($count / $total) * 100, 2);
    }

    /**
     * يجمع تاريخ التسجيل فقط في PHP لتجنب دوال SQL المرتبطة بمحرك محدد.
     *
     * @return list<array{period: string, count: int, cumulative_count: int}>
     */
    private function trend(Builder $currentQuery, string $period): array
    {
        $counts = [];

        foreach ((clone $currentQuery)->select('users.created_at')->orderBy('users.created_at')->cursor() as $user) {
            if (! $user->created_at) {
                continue;
            }

            $bucket = $this->periodBucket($user->created_at, $period);
            $counts[$bucket] = ($counts[$bucket] ?? 0) + 1;
        }

        ksort($counts);
        $cumulative = 0;
        $trend = [];

        foreach ($counts as $bucket => $count) {
            $cumulative += $count;
            $trend[] = [
                'period' => $bucket,
                'count' => $count,
                'cumulative_count' => $cumulative,
            ];
        }

        return $trend;
    }

    /**
     * @return list<array{role: string, count: int, percentage: float}>
     */
    private function roleMix(Builder $currentQuery, ?string $selectedRole, int $currentUsers): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->when($selectedRole, fn (Builder $query, string $role) => $query->where('name', $role))
            ->orderBy('name')
            ->pluck('name')
            ->map(function (string $role) use ($currentQuery, $currentUsers): array {
                $count = (clone $currentQuery)
                    ->whereHas('roles', fn (Builder $roleQuery) => $roleQuery
                        ->where('roles.name', $role)
                        ->where('roles.guard_name', 'web'))
                    ->count();

                return [
                    'role' => $role,
                    'count' => $count,
                    'percentage' => $this->percentage($count, $currentUsers),
                ];
            })
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
