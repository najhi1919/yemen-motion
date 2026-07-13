<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksOverviewRequest;
use App\Models\Work;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class WorksOverviewController extends Controller
{
    public function index(WorksOverviewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $period = $validated['period'] ?? 'month';
        [$from, $to] = $this->effectiveRange($validated, $period);

        // يبقى الملخص عامًا، بينما يحدد المدى نقاط السلسلة الزمنية فقط.
        $summary = $this->summary();

        $data = [
            'summary' => $summary,
            'visibility' => $this->visibility(),
            'review_queue' => [
                'pending' => $summary['submitted'],
                'in_review' => $summary['in_review'],
                'changes_requested' => $summary['changes_requested'],
                'overdue' => $this->overdueReviewCount(),
            ],
            'series' => $this->series($from, $to, $period),
            'top_counters' => $this->topCounters(),
            'filters' => [
                'period' => $period,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'generated_at' => now()->toJSON(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'تم جلب نظرة عامة على الأعمال بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * @return array{total: int, submitted: int, in_review: int, changes_requested: int, approved: int, published: int, rejected: int, hidden: int, archived: int, featured: int, pinned: int, reported: int}
     */
    private function summary(): array
    {
        $statusCounts = Work::query()
            ->select('status')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => Work::query()->count(),
            'submitted' => (int) $statusCounts->get(Work::STATUS_SUBMITTED, 0),
            'in_review' => (int) $statusCounts->get(Work::STATUS_IN_REVIEW, 0),
            'changes_requested' => (int) $statusCounts->get(Work::STATUS_CHANGES_REQUESTED, 0),
            'approved' => (int) $statusCounts->get(Work::STATUS_APPROVED, 0),
            'published' => (int) $statusCounts->get(Work::STATUS_PUBLISHED, 0),
            'rejected' => (int) $statusCounts->get(Work::STATUS_REJECTED, 0),
            'hidden' => (int) $statusCounts->get(Work::STATUS_HIDDEN, 0),
            'archived' => (int) $statusCounts->get(Work::STATUS_ARCHIVED, 0),
            'featured' => Work::query()->featured()->count(),
            'pinned' => Work::query()->pinned()->count(),
            'reported' => Work::query()->reported()->count(),
        ];
    }

    /**
     * @return array{public: int, hidden: int}
     */
    private function visibility(): array
    {
        $visibilityCounts = Work::query()
            ->select('visibility_status')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('visibility_status')
            ->pluck('aggregate', 'visibility_status');

        return [
            'public' => (int) $visibilityCounts->get(Work::VISIBILITY_PUBLIC, 0),
            'hidden' => (int) $visibilityCounts->get(Work::VISIBILITY_HIDDEN, 0),
        ];
    }

    private function overdueReviewCount(): int
    {
        return Work::query()
            ->whereIn('status', [Work::STATUS_SUBMITTED, Work::STATUS_IN_REVIEW])
            ->where('submitted_at', '<', now()->subHours(48))
            ->whereNull('reviewed_at')
            ->whereNull('published_at')
            ->whereNull('rejected_at')
            ->whereNull('archived_at')
            ->count();
    }

    /**
     * @return array{views: int, likes: int, reports: int}
     */
    private function topCounters(): array
    {
        $totals = Work::query()
            ->toBase()
            ->selectRaw('COALESCE(SUM(views_count), 0) AS views')
            ->selectRaw('COALESCE(SUM(likes_count), 0) AS likes')
            ->selectRaw('COALESCE(SUM(reports_count), 0) AS reports')
            ->first();

        return [
            'views' => (int) ($totals->views ?? 0),
            'likes' => (int) ($totals->likes ?? 0),
            'reports' => (int) ($totals->reports ?? 0),
        ];
    }

    /**
     * نجمع أعمدة التوقيت اللازمة فقط في PHP لتجنب دوال SQL المرتبطة بمحرك محدد.
     *
     * @return list<array{label: string, submitted: int, published: int, rejected: int}>
     */
    private function series(CarbonInterface $from, CarbonInterface $to, string $period): array
    {
        $counts = [];
        $range = [$from, $to];

        $works = Work::query()
            ->select(['id', 'submitted_at', 'published_at', 'rejected_at'])
            ->where(function (Builder $query) use ($range): void {
                $query->whereBetween('submitted_at', $range)
                    ->orWhereBetween('published_at', $range)
                    ->orWhereBetween('rejected_at', $range);
            })
            ->orderBy('id')
            ->cursor();

        foreach ($works as $work) {
            foreach ([
                'submitted' => $work->submitted_at,
                'published' => $work->published_at,
                'rejected' => $work->rejected_at,
            ] as $event => $occurredAt) {
                if (! $occurredAt || $occurredAt->lt($from) || $occurredAt->gt($to)) {
                    continue;
                }

                $label = $this->periodBucket($occurredAt, $period);
                $counts[$label] ??= [
                    'submitted' => 0,
                    'published' => 0,
                    'rejected' => 0,
                ];
                $counts[$label][$event]++;
            }
        }

        ksort($counts);

        return collect($counts)
            ->map(fn (array $values, string $label): array => [
                'label' => $label,
                'submitted' => $values['submitted'],
                'published' => $values['published'],
                'rejected' => $values['rejected'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{0: Carbon, 1: Carbon}
     */
    private function effectiveRange(array $validated, string $period): array
    {
        if (isset($validated['from'], $validated['to'])) {
            return [
                Carbon::parse($validated['from'])->startOfDay(),
                Carbon::parse($validated['to'])->endOfDay(),
            ];
        }

        $now = now();

        return match ($period) {
            'day' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [
                $now->copy()->startOfWeek(Carbon::MONDAY),
                $now->copy()->endOfWeek(Carbon::SUNDAY),
            ],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
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
