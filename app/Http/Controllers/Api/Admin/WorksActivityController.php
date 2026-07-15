<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksActivityRequest;
use App\Models\User;
use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class WorksActivityController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const EVENT_TIMESTAMPS = [
        'created' => 'created_at',
        'updated' => 'updated_at',
        'submitted' => 'submitted_at',
        'reviewed' => 'reviewed_at',
        'approved' => 'approved_at',
        'published' => 'published_at',
        'rejected' => 'rejected_at',
        'hidden' => 'hidden_at',
        'archived' => 'archived_at',
    ];

    /**
     * @var array<string, string>
     */
    private const EVENT_LABELS = [
        'created' => 'إنشاء العمل',
        'updated' => 'تحديث العمل',
        'submitted' => 'إرسال للمراجعة',
        'reviewed' => 'مراجعة العمل',
        'approved' => 'اعتماد العمل',
        'published' => 'نشر العمل',
        'rejected' => 'رفض العمل',
        'hidden' => 'إخفاء العمل',
        'archived' => 'أرشفة العمل',
    ];

    /**
     * @var list<string>
     */
    private const REVIEW_EVENTS = [
        'submitted',
        'reviewed',
        'approved',
        'rejected',
    ];

    /**
     * @var list<string>
     */
    private const VISIBILITY_EVENTS = [
        'published',
        'hidden',
        'archived',
    ];

    /**
     * @var list<string>
     */
    private const ATTENTION_EVENTS = [
        'rejected',
        'hidden',
        'archived',
    ];

    public function index(WorksActivityRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $queryText = trim((string) ($validated['q'] ?? ''));
        $eventType = filled($validated['event_type'] ?? null)
            ? (string) $validated['event_type']
            : null;
        $sort = (string) ($validated['sort'] ?? 'event_at');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $perPage = (int) ($validated['per_page'] ?? 15);
        $reported = $this->booleanFilter($request, $validated, 'reported');
        $promoted = $this->booleanFilter($request, $validated, 'promoted');
        $from = filled($validated['from'] ?? null)
            ? Carbon::parse((string) $validated['from'])->startOfDay()
            : null;
        $to = filled($validated['to'] ?? null)
            ? Carbon::parse((string) $validated['to'])->endOfDay()
            : null;

        $worksQuery = $this->worksQuery(
            $validated,
            $queryText,
            $reported,
            $promoted,
        );
        $eventsQuery = $this->eventsQuery(
            $worksQuery,
            $eventType,
            $from,
            $to,
        );
        $summary = $this->summary(clone $eventsQuery);

        $events = $this->orderedEventsQuery(
            clone $eventsQuery,
            $sort,
            $direction,
        )->paginate($perPage);
        $relatedWorks = $this->relatedWorks($events->items());
        $items = collect($events->items())
            ->map(fn (stdClass $event): array => $this->activityPayload($event, $relatedWorks))
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'pagination' => [
                    'current_page' => $events->currentPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                    'last_page' => $events->lastPage(),
                ],
                'summary' => $summary,
                'filters' => [
                    'q' => $queryText !== '' ? $queryText : null,
                    'event_type' => $eventType,
                    'status' => $validated['status'] ?? null,
                    'visibility_status' => $validated['visibility_status'] ?? null,
                    'media_type' => $validated['media_type'] ?? null,
                    'designer_id' => isset($validated['designer_id']) ? (int) $validated['designer_id'] : null,
                    'reviewer_id' => isset($validated['reviewer_id']) ? (int) $validated['reviewer_id'] : null,
                    'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
                    'reported' => $reported,
                    'promoted' => $promoted,
                    'from' => $from?->toDateString(),
                    'to' => $to?->toDateString(),
                    'sort' => $sort,
                    'direction' => $direction,
                ],
                'activity_source' => [
                    'dedicated_log_available' => false,
                    'source' => 'work_lifecycle_timestamps',
                    'reason' => 'لا يوجد جدول سجل أعمال مستقل حاليًا؛ هذه القائمة مشتقة من تواريخ دورة حياة الأعمال.',
                ],
            ],
            'message' => 'تم جلب سجل الأعمال بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function worksQuery(
        array $validated,
        string $queryText,
        ?bool $reported,
        ?bool $promoted,
    ): Builder {
        return DB::table('works')
            ->when($queryText !== '', function (Builder $query) use ($queryText): void {
                $query->where(function (Builder $searchQuery) use ($queryText): void {
                    $searchQuery
                        ->where('title', 'like', "%{$queryText}%")
                        ->orWhere('slug', 'like', "%{$queryText}%");
                });
            })
            ->when(
                filled($validated['status'] ?? null),
                fn (Builder $query) => $query->where('status', $validated['status']),
            )
            ->when(
                filled($validated['visibility_status'] ?? null),
                fn (Builder $query) => $query->where('visibility_status', $validated['visibility_status']),
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
            ->when(
                isset($validated['category_id']),
                fn (Builder $query) => $query->where('category_id', $validated['category_id']),
            )
            ->when($reported !== null, function (Builder $query) use ($reported): void {
                $query->where('reports_count', $reported ? '>' : '=', 0);
            })
            ->when($promoted === true, function (Builder $query): void {
                $query->where(function (Builder $promotionQuery): void {
                    $promotionQuery
                        ->where('is_featured', true)
                        ->orWhere('is_pinned', true);
                });
            })
            ->when($promoted === false, function (Builder $query): void {
                $query
                    ->where('is_featured', false)
                    ->where('is_pinned', false);
            });
    }

    private function eventsQuery(
        Builder $worksQuery,
        ?string $eventType,
        ?Carbon $from,
        ?Carbon $to,
    ): Builder {
        $eventTimestamps = $eventType !== null
            ? [$eventType => self::EVENT_TIMESTAMPS[$eventType]]
            : self::EVENT_TIMESTAMPS;
        $firstEventType = (string) array_key_first($eventTimestamps);
        $events = $this->eventBranch(
            $worksQuery,
            $firstEventType,
            $eventTimestamps[$firstEventType],
        );

        foreach ($eventTimestamps as $currentEventType => $timestampColumn) {
            if ($currentEventType === $firstEventType) {
                continue;
            }

            $events->unionAll(
                $this->eventBranch($worksQuery, $currentEventType, $timestampColumn),
            );
        }

        // يطبق النطاق الزمني على وقت الحدث المشتق لا على صف العمل.
        return DB::query()
            ->fromSub($events, 'work_activity_events')
            ->when($from !== null, fn (Builder $query) => $query->where('event_at', '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->where('event_at', '<=', $to));
    }

    private function eventBranch(
        Builder $worksQuery,
        string $eventType,
        string $timestampColumn,
    ): Builder {
        $qualifiedTimestamp = 'works.'.$timestampColumn;

        return (clone $worksQuery)
            ->whereNotNull($qualifiedTimestamp)
            ->select([
                'works.id as work_id',
                'works.title',
                'works.slug',
                'works.status',
                'works.visibility_status',
                'works.media_type',
                'works.category_id',
                'works.reports_count',
                'works.views_count',
                'works.likes_count',
            ])
            ->selectRaw(
                'CASE WHEN works.is_featured OR works.is_pinned THEN 1 ELSE 0 END AS is_promoted',
            )
            ->selectRaw('CAST(? AS VARCHAR) AS event_type', [$eventType])
            ->selectRaw($qualifiedTimestamp.' AS event_at');
    }

    /**
     * @return array<string, int>
     */
    private function summary(Builder $eventsQuery): array
    {
        $counts = $eventsQuery
            ->selectRaw('COUNT(*) AS total_events')
            ->selectRaw('COUNT(DISTINCT work_id) AS unique_works')
            ->selectRaw("COALESCE(SUM(CASE WHEN event_type = 'created' THEN 1 ELSE 0 END), 0) AS created_events")
            ->selectRaw("COALESCE(SUM(CASE WHEN event_type = 'updated' THEN 1 ELSE 0 END), 0) AS updated_events")
            ->selectRaw("COALESCE(SUM(CASE WHEN event_type = 'submitted' THEN 1 ELSE 0 END), 0) AS submitted_events")
            ->selectRaw("COALESCE(SUM(CASE WHEN event_type = 'reviewed' THEN 1 ELSE 0 END), 0) AS reviewed_events")
            ->selectRaw("COALESCE(SUM(CASE WHEN event_type = 'approved' THEN 1 ELSE 0 END), 0) AS approved_events")
            ->selectRaw("COALESCE(SUM(CASE WHEN event_type = 'published' THEN 1 ELSE 0 END), 0) AS published_events")
            ->selectRaw("COALESCE(SUM(CASE WHEN event_type = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected_events")
            ->selectRaw("COALESCE(SUM(CASE WHEN event_type = 'hidden' THEN 1 ELSE 0 END), 0) AS hidden_events")
            ->selectRaw("COALESCE(SUM(CASE WHEN event_type = 'archived' THEN 1 ELSE 0 END), 0) AS archived_events")
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN event_type IN ('submitted', 'reviewed', 'approved', 'rejected') THEN 1 ELSE 0 END), 0) AS review_events",
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN event_type IN ('published', 'hidden', 'archived') THEN 1 ELSE 0 END), 0) AS visibility_events",
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN reports_count > 0 THEN 1 ELSE 0 END), 0) AS reported_events')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_promoted = 1 THEN 1 ELSE 0 END), 0) AS promoted_events')
            ->first();

        return [
            'total_events' => (int) ($counts?->total_events ?? 0),
            'unique_works' => (int) ($counts?->unique_works ?? 0),
            'created_events' => (int) ($counts?->created_events ?? 0),
            'updated_events' => (int) ($counts?->updated_events ?? 0),
            'submitted_events' => (int) ($counts?->submitted_events ?? 0),
            'reviewed_events' => (int) ($counts?->reviewed_events ?? 0),
            'approved_events' => (int) ($counts?->approved_events ?? 0),
            'published_events' => (int) ($counts?->published_events ?? 0),
            'rejected_events' => (int) ($counts?->rejected_events ?? 0),
            'hidden_events' => (int) ($counts?->hidden_events ?? 0),
            'archived_events' => (int) ($counts?->archived_events ?? 0),
            'review_events' => (int) ($counts?->review_events ?? 0),
            'visibility_events' => (int) ($counts?->visibility_events ?? 0),
            'reported_events' => (int) ($counts?->reported_events ?? 0),
            'promoted_events' => (int) ($counts?->promoted_events ?? 0),
        ];
    }

    private function orderedEventsQuery(
        Builder $eventsQuery,
        string $sort,
        string $direction,
    ): Builder {
        $eventsQuery->orderBy($sort, $direction);

        if ($sort !== 'event_at') {
            $eventsQuery->orderBy('event_at', 'desc');
        }

        if ($sort !== 'work_id') {
            $eventsQuery->orderBy('work_id');
        }

        return $eventsQuery->orderBy('event_type');
    }

    /**
     * @param array<int, stdClass> $events
     * @return Collection<int, Work>
     */
    private function relatedWorks(array $events): Collection
    {
        $workIds = collect($events)
            ->map(fn (stdClass $event): int => (int) $event->work_id)
            ->unique()
            ->values()
            ->all();

        if ($workIds === []) {
            return collect();
        }

        return Work::query()
            ->select([
                'id',
                'designer_id',
                'reviewer_id',
            ])
            ->with([
                'designer:id,name',
                'reviewer:id,name',
            ])
            ->whereIn('id', $workIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param Collection<int, Work> $relatedWorks
     * @return array<string, mixed>
     */
    private function activityPayload(stdClass $event, Collection $relatedWorks): array
    {
        $workId = (int) $event->work_id;
        $eventType = (string) $event->event_type;
        $reportsCount = (int) $event->reports_count;
        $isReported = $reportsCount > 0;
        $isPromoted = (int) $event->is_promoted === 1;
        $isReviewEvent = in_array($eventType, self::REVIEW_EVENTS, true);
        $isVisibilityEvent = in_array($eventType, self::VISIBILITY_EVENTS, true);
        $needsAttention = $isReported
            || in_array($eventType, self::ATTENTION_EVENTS, true);
        $work = $relatedWorks->get($workId);

        return [
            'id' => 'work-'.$workId.'-'.$eventType,
            'work_id' => $workId,
            'event_type' => $eventType,
            'event_label' => self::EVENT_LABELS[$eventType],
            'event_at' => Carbon::parse((string) $event->event_at)->toJSON(),
            'title' => (string) $event->title,
            'slug' => (string) $event->slug,
            'status' => (string) $event->status,
            'visibility_status' => (string) $event->visibility_status,
            'media_type' => $event->media_type !== null ? (string) $event->media_type : null,
            'designer' => $work instanceof Work
                ? $this->userReference($work->designer)
                : null,
            'reviewer' => $work instanceof Work
                ? $this->userReference($work->reviewer)
                : null,
            'category_id' => $event->category_id !== null
                ? (int) $event->category_id
                : null,
            'reports_count' => $reportsCount,
            'views_count' => (int) $event->views_count,
            'likes_count' => (int) $event->likes_count,
            'activity_flags' => [
                'is_review_event' => $isReviewEvent,
                'is_visibility_event' => $isVisibilityEvent,
                'is_reported' => $isReported,
                'is_promoted' => $isPromoted,
                'needs_attention' => $needsAttention,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function booleanFilter(
        WorksActivityRequest $request,
        array $validated,
        string $key,
    ): ?bool {
        if (! array_key_exists($key, $validated) || $validated[$key] === null) {
            return null;
        }

        return $request->boolean($key);
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
