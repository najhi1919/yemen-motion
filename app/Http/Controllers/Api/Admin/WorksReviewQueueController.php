<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksReviewQueueRequest;
use App\Models\User;
use App\Models\Work;
use App\Services\Works\WorksSettingsStore;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class WorksReviewQueueController extends Controller
{
    /**
     * @var list<string>
     */
    private const REVIEW_STATUSES = [
        Work::STATUS_SUBMITTED,
        Work::STATUS_IN_REVIEW,
        Work::STATUS_CHANGES_REQUESTED,
    ];

    public function __construct(
        private readonly WorksSettingsStore $settingsStore,
    ) {}

    public function index(WorksReviewQueueRequest $request): JsonResponse
    {
        $storedSettings = $this->settingsStore->getGlobalSettings();
        $storedReviewSlaHours = $storedSettings['values']['review_sla_hours'];
        $reviewSlaHours = is_int($storedReviewSlaHours)
            && $storedReviewSlaHours >= 1
            && $storedReviewSlaHours <= 720
                ? $storedReviewSlaHours
                : null;
        $settingsVersion = (int) $storedSettings['version'];
        $directPublishTrustEnabled = $storedSettings['values']['direct_publish_trust_enabled'];
        $now = now();
        $overdueCutoff = $reviewSlaHours === null
            ? null
            : $now->copy()->subHours($reviewSlaHours);
        $validated = $request->validated();
        $queryText = trim((string) ($validated['q'] ?? ''));
        $sort = (string) ($validated['sort'] ?? 'submitted_at');
        $direction = (string) ($validated['direction'] ?? 'asc');
        $perPage = (int) ($validated['per_page'] ?? 15);
        $assigned = $this->booleanFilter($request, $validated, 'assigned');
        $overdue = $this->booleanFilter($request, $validated, 'overdue');
        $from = filled($validated['from'] ?? null)
            ? Carbon::parse((string) $validated['from'])->startOfDay()
            : null;
        $to = filled($validated['to'] ?? null)
            ? Carbon::parse((string) $validated['to'])->endOfDay()
            : null;
        // نستخدم الاستعلام المفلتر نفسه للملخص والقائمة حتى تبقى الأرقام متطابقة.
        $reviewQuery = $this->reviewQuery(
            $validated,
            $queryText,
            $assigned,
            $overdue,
            $from,
            $to,
            $overdueCutoff,
        );
        $summary = $this->summary((clone $reviewQuery), $overdueCutoff);

        $works = (clone $reviewQuery)
            ->select([
                'id',
                'title',
                'slug',
                'summary',
                'status',
                'visibility_status',
                'media_type',
                'designer_id',
                'reviewer_id',
                'category_id',
                'reports_count',
                'views_count',
                'likes_count',
                'submitted_at',
                'reviewed_at',
                'published_at',
                'rejected_at',
                'archived_at',
                'updated_at',
                'created_at',
            ])
            ->with([
                'designer:id,name',
                'reviewer:id,name',
            ])
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->through(fn (Work $work): array => $this->workPayload($work, $overdueCutoff));

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $works->items(),
                'pagination' => [
                    'current_page' => $works->currentPage(),
                    'per_page' => $works->perPage(),
                    'total' => $works->total(),
                    'last_page' => $works->lastPage(),
                ],
                'summary' => $summary,
                'review_policy' => [
                    'source' => 'work_settings',
                    'enabled' => $reviewSlaHours !== null,
                    'review_sla_hours' => $reviewSlaHours,
                    'overdue_cutoff' => $overdueCutoff?->toIso8601String(),
                    'settings_version' => $settingsVersion,
                ],
                'publication_policy' => [
                    'source' => 'work_settings',
                    'direct_publish_trust_enabled' => $directPublishTrustEnabled,
                    'approval_behavior' => $directPublishTrustEnabled
                        ? 'approve_and_publish'
                        : 'approve_only',
                    'settings_version' => $settingsVersion,
                ],
                'filters' => [
                    'q' => $queryText !== '' ? $queryText : null,
                    'status' => $validated['status'] ?? null,
                    'media_type' => $validated['media_type'] ?? null,
                    'designer_id' => isset($validated['designer_id']) ? (int) $validated['designer_id'] : null,
                    'reviewer_id' => isset($validated['reviewer_id']) ? (int) $validated['reviewer_id'] : null,
                    'assigned' => $assigned,
                    'overdue' => $overdue,
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                    'sort' => $sort,
                    'direction' => $direction,
                ],
            ],
            'message' => 'تم جلب طلبات مراجعة الأعمال بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function reviewQuery(
        array $validated,
        string $queryText,
        ?bool $assigned,
        ?bool $overdue,
        ?Carbon $from,
        ?Carbon $to,
        ?Carbon $overdueCutoff,
    ): Builder {
        $query = Work::query()
            ->whereIn('status', self::REVIEW_STATUSES)
            ->when($queryText !== '', function (Builder $query) use ($queryText): void {
                $query->where(function (Builder $searchQuery) use ($queryText): void {
                    $searchQuery
                        ->where('title', 'like', "%{$queryText}%")
                        ->orWhere('slug', 'like', "%{$queryText}%")
                        ->orWhere('summary', 'like', "%{$queryText}%");
                });
            })
            ->when(
                filled($validated['status'] ?? null),
                fn (Builder $query) => $query->where('status', $validated['status']),
            )
            ->when(
                filled($validated['media_type'] ?? null),
                fn (Builder $query) => $query->where('media_type', $validated['media_type']),
            )
            ->when(
                isset($validated['designer_id']),
                fn (Builder $query) => $query->where('designer_id', $validated['designer_id']),
            )
            ->when(
                isset($validated['reviewer_id']),
                fn (Builder $query) => $query->where('reviewer_id', $validated['reviewer_id']),
            )
            ->when($assigned !== null, function (Builder $query) use ($assigned): void {
                if ($assigned) {
                    $query->whereNotNull('reviewer_id');

                    return;
                }

                $query->whereNull('reviewer_id');
            })
            ->when($from !== null, fn (Builder $query) => $query->where('submitted_at', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->where('submitted_at', '<=', $to));

        if ($overdue !== null) {
            $this->applyOverdueFilter($query, $overdue, $overdueCutoff);
        }

        return $query;
    }

    private function applyOverdueFilter(Builder $query, bool $overdue, ?Carbon $cutoff): void
    {
        if ($cutoff === null) {
            if ($overdue) {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        if ($overdue) {
            $query
                ->whereIn('status', [Work::STATUS_SUBMITTED, Work::STATUS_IN_REVIEW])
                ->whereNotNull('submitted_at')
                ->where('submitted_at', '<', $cutoff)
                ->whereNull('reviewed_at')
                ->whereNull('published_at')
                ->whereNull('rejected_at')
                ->whereNull('archived_at');

            return;
        }

        // هذه المجموعة هي النفي الصريح لكل شروط التأخر داخل نطاق المراجعة.
        $query->where(function (Builder $query) use ($cutoff): void {
            $query
                ->whereNotIn('status', [Work::STATUS_SUBMITTED, Work::STATUS_IN_REVIEW])
                ->orWhereNull('submitted_at')
                ->orWhere('submitted_at', '>=', $cutoff)
                ->orWhereNotNull('reviewed_at')
                ->orWhereNotNull('published_at')
                ->orWhereNotNull('rejected_at')
                ->orWhereNotNull('archived_at');
        });
    }

    /**
     * @return array<string, int>
     */
    private function summary(Builder $query, ?Carbon $overdueCutoff): array
    {
        $summaryQuery = $query
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS submitted', [Work::STATUS_SUBMITTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS in_review', [Work::STATUS_IN_REVIEW])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS changes_requested', [Work::STATUS_CHANGES_REQUESTED])
            ->selectRaw('SUM(CASE WHEN reviewer_id IS NOT NULL THEN 1 ELSE 0 END) AS assigned')
            ->selectRaw('SUM(CASE WHEN reviewer_id IS NULL THEN 1 ELSE 0 END) AS unassigned');

        if ($overdueCutoff === null) {
            $summaryQuery->selectRaw('0 AS overdue');
        } else {
            $summaryQuery->selectRaw(
                'SUM(CASE WHEN status IN (?, ?)
                    AND submitted_at IS NOT NULL
                    AND submitted_at < ?
                    AND reviewed_at IS NULL
                    AND published_at IS NULL
                    AND rejected_at IS NULL
                    AND archived_at IS NULL
                    THEN 1 ELSE 0 END) AS overdue',
                [
                    Work::STATUS_SUBMITTED,
                    Work::STATUS_IN_REVIEW,
                    $overdueCutoff->toDateTimeString(),
                ],
            );
        }

        $counts = $summaryQuery
            ->selectRaw('SUM(CASE WHEN reports_count > 0 THEN 1 ELSE 0 END) AS reported')
            ->first();

        return [
            'total' => (int) ($counts?->total ?? 0),
            'submitted' => (int) ($counts?->submitted ?? 0),
            'in_review' => (int) ($counts?->in_review ?? 0),
            'changes_requested' => (int) ($counts?->changes_requested ?? 0),
            'assigned' => (int) ($counts?->assigned ?? 0),
            'unassigned' => (int) ($counts?->unassigned ?? 0),
            'overdue' => (int) ($counts?->overdue ?? 0),
            'reported' => (int) ($counts?->reported ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function booleanFilter(
        WorksReviewQueueRequest $request,
        array $validated,
        string $key,
    ): ?bool {
        if (! array_key_exists($key, $validated) || $validated[$key] === null) {
            return null;
        }

        return $request->boolean($key);
    }

    /**
     * @return array<string, mixed>
     */
    private function workPayload(Work $work, ?Carbon $overdueCutoff): array
    {
        $isOverdue = $this->isOverdue($work, $overdueCutoff);

        return [
            'id' => $work->id,
            'title' => $work->title,
            'slug' => $work->slug,
            'summary' => $work->summary,
            'status' => $work->status,
            'visibility_status' => $work->visibility_status,
            'media_type' => $work->media_type,
            'designer' => $this->userReference($work->designer),
            'reviewer' => $this->userReference($work->reviewer),
            'category_id' => $work->category_id,
            'reports_count' => $work->reports_count,
            'views_count' => $work->views_count,
            'likes_count' => $work->likes_count,
            'submitted_at' => $work->submitted_at?->toJSON(),
            'reviewed_at' => $work->reviewed_at?->toJSON(),
            'updated_at' => $work->updated_at?->toJSON(),
            'created_at' => $work->created_at?->toJSON(),
            'review_flags' => [
                'assigned' => $work->reviewer_id !== null,
                'overdue' => $isOverdue,
                'needs_attention' => $isOverdue
                    || $work->reports_count > 0
                    || $work->status === Work::STATUS_CHANGES_REQUESTED,
            ],
        ];
    }

    private function isOverdue(Work $work, ?Carbon $cutoff): bool
    {
        return $cutoff !== null
            && in_array($work->status, [Work::STATUS_SUBMITTED, Work::STATUS_IN_REVIEW], true)
            && $work->submitted_at !== null
            && $work->submitted_at->lt($cutoff)
            && $work->reviewed_at === null
            && $work->published_at === null
            && $work->rejected_at === null
            && $work->archived_at === null;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userReference(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
