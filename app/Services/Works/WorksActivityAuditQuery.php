<?php

declare(strict_types=1);

namespace App\Services\Works;

use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorksActivityAuditQuery
{
    private const CATEGORY = 'works';

    /**
     * @var list<string>
     */
    private const SORTS = [
        'event_at',
        'audit_event_id',
        'event_type',
        'actor_name',
        'work_id',
        'work_title',
    ];

    /**
     * @var array<string, array{
     *     event_type: string,
     *     event_group: string,
     *     group_label_ar?: string,
     *     group_label_en?: string,
     *     event_key: string,
     *     label_ar: string,
     *     label_en: string,
     *     target_scope: string,
     *     requires_work: bool,
     *     needs_attention: bool,
     *     severity_fallback: string
     * }>
     */
    private const EVENT_DEFINITIONS = [
        'works.review.started' => [
            'event_type' => 'works.review.started',
            'event_group' => 'review',
            'group_label_ar' => 'المراجعة',
            'group_label_en' => 'Review',
            'event_key' => 'started',
            'label_ar' => 'بدء مراجعة العمل',
            'label_en' => 'Work review started',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.review.reviewer_assigned' => [
            'event_type' => 'works.review.reviewer_assigned',
            'event_group' => 'review',
            'event_key' => 'reviewer_assigned',
            'label_ar' => 'تعيين مراجع للعمل',
            'label_en' => 'Work reviewer assigned',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.review.approved' => [
            'event_type' => 'works.review.approved',
            'event_group' => 'review',
            'event_key' => 'approved',
            'label_ar' => 'اعتماد العمل',
            'label_en' => 'Work approved',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.review.changes_requested' => [
            'event_type' => 'works.review.changes_requested',
            'event_group' => 'review',
            'event_key' => 'changes_requested',
            'label_ar' => 'طلب تعديلات على العمل',
            'label_en' => 'Work changes requested',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => true,
            'severity_fallback' => 'notice',
        ],
        'works.review.rejected' => [
            'event_type' => 'works.review.rejected',
            'event_group' => 'review',
            'event_key' => 'rejected',
            'label_ar' => 'رفض العمل',
            'label_en' => 'Work rejected',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => true,
            'severity_fallback' => 'notice',
        ],
        'works.review.published' => [
            'event_type' => 'works.review.published',
            'event_group' => 'review',
            'event_key' => 'published',
            'label_ar' => 'نشر العمل بعد الاعتماد',
            'label_en' => 'Work published after approval',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.review.reopened' => [
            'event_type' => 'works.review.reopened',
            'event_group' => 'review',
            'event_key' => 'reopened',
            'label_ar' => 'إعادة فتح مراجعة العمل',
            'label_en' => 'Work review reopened',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.visibility.published' => [
            'event_type' => 'works.visibility.published',
            'event_group' => 'visibility',
            'group_label_ar' => 'الظهور',
            'group_label_en' => 'Visibility',
            'event_key' => 'published',
            'label_ar' => 'نشر العمل',
            'label_en' => 'Work published',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.visibility.unpublished' => [
            'event_type' => 'works.visibility.unpublished',
            'event_group' => 'visibility',
            'event_key' => 'unpublished',
            'label_ar' => 'إلغاء نشر العمل',
            'label_en' => 'Work unpublished',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.visibility.hidden' => [
            'event_type' => 'works.visibility.hidden',
            'event_group' => 'visibility',
            'event_key' => 'hidden',
            'label_ar' => 'إخفاء العمل',
            'label_en' => 'Work hidden',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => true,
            'severity_fallback' => 'notice',
        ],
        'works.visibility.restored' => [
            'event_type' => 'works.visibility.restored',
            'event_group' => 'visibility',
            'event_key' => 'restored',
            'label_ar' => 'استعادة ظهور العمل',
            'label_en' => 'Work visibility restored',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.visibility.featured' => [
            'event_type' => 'works.visibility.featured',
            'event_group' => 'visibility',
            'event_key' => 'featured',
            'label_ar' => 'تمييز العمل',
            'label_en' => 'Work featured',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.visibility.unfeatured' => [
            'event_type' => 'works.visibility.unfeatured',
            'event_group' => 'visibility',
            'event_key' => 'unfeatured',
            'label_ar' => 'إلغاء تمييز العمل',
            'label_en' => 'Work unfeatured',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.visibility.pinned' => [
            'event_type' => 'works.visibility.pinned',
            'event_group' => 'visibility',
            'event_key' => 'pinned',
            'label_ar' => 'تثبيت العمل',
            'label_en' => 'Work pinned',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.visibility.unpinned' => [
            'event_type' => 'works.visibility.unpinned',
            'event_group' => 'visibility',
            'event_key' => 'unpinned',
            'label_ar' => 'إلغاء تثبيت العمل',
            'label_en' => 'Work unpinned',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.reports.review_started' => [
            'event_type' => 'works.reports.review_started',
            'event_group' => 'reports',
            'group_label_ar' => 'البلاغات',
            'group_label_en' => 'Reports',
            'event_key' => 'review_started',
            'label_ar' => 'بدء مراجعة بلاغ العمل',
            'label_en' => 'Work report review started',
            'target_scope' => 'work_report',
            'requires_work' => true,
            'needs_attention' => true,
            'severity_fallback' => 'notice',
        ],
        'works.reports.dismissed' => [
            'event_type' => 'works.reports.dismissed',
            'event_group' => 'reports',
            'event_key' => 'dismissed',
            'label_ar' => 'إغلاق بلاغ العمل',
            'label_en' => 'Work report dismissed',
            'target_scope' => 'work_report',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.reports.archived' => [
            'event_type' => 'works.reports.archived',
            'event_group' => 'reports',
            'event_key' => 'archived',
            'label_ar' => 'أرشفة بلاغ العمل',
            'label_en' => 'Work report archived',
            'target_scope' => 'work_report',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.taxonomy.category.created' => [
            'event_type' => 'works.taxonomy.category.created',
            'event_group' => 'taxonomy',
            'group_label_ar' => 'التصنيف',
            'group_label_en' => 'Taxonomy',
            'event_key' => 'category_created',
            'label_ar' => 'إنشاء تصنيف أعمال',
            'label_en' => 'Work category created',
            'target_scope' => 'work_category',
            'requires_work' => false,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.taxonomy.category.updated' => [
            'event_type' => 'works.taxonomy.category.updated',
            'event_group' => 'taxonomy',
            'event_key' => 'category_updated',
            'label_ar' => 'تحديث تصنيف أعمال',
            'label_en' => 'Work category updated',
            'target_scope' => 'work_category',
            'requires_work' => false,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.taxonomy.category.disabled' => [
            'event_type' => 'works.taxonomy.category.disabled',
            'event_group' => 'taxonomy',
            'event_key' => 'category_disabled',
            'label_ar' => 'تعطيل تصنيف أعمال',
            'label_en' => 'Work category disabled',
            'target_scope' => 'work_category',
            'requires_work' => false,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.taxonomy.tag.created' => [
            'event_type' => 'works.taxonomy.tag.created',
            'event_group' => 'taxonomy',
            'event_key' => 'tag_created',
            'label_ar' => 'إنشاء وسم أعمال',
            'label_en' => 'Work tag created',
            'target_scope' => 'work_tag',
            'requires_work' => false,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.taxonomy.tag.updated' => [
            'event_type' => 'works.taxonomy.tag.updated',
            'event_group' => 'taxonomy',
            'event_key' => 'tag_updated',
            'label_ar' => 'تحديث وسم أعمال',
            'label_en' => 'Work tag updated',
            'target_scope' => 'work_tag',
            'requires_work' => false,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.taxonomy.tag.disabled' => [
            'event_type' => 'works.taxonomy.tag.disabled',
            'event_group' => 'taxonomy',
            'event_key' => 'tag_disabled',
            'label_ar' => 'تعطيل وسم أعمال',
            'label_en' => 'Work tag disabled',
            'target_scope' => 'work_tag',
            'requires_work' => false,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'works.taxonomy.tags.merged' => [
            'event_type' => 'works.taxonomy.tags.merged',
            'event_group' => 'taxonomy',
            'event_key' => 'tags_merged',
            'label_ar' => 'دمج وسوم الأعمال',
            'label_en' => 'Work tags merged',
            'target_scope' => 'work_tag',
            'requires_work' => false,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'work.category.changed' => [
            'event_type' => 'work.category.changed',
            'event_group' => 'taxonomy_assignment',
            'group_label_ar' => 'إسناد التصنيف',
            'group_label_en' => 'Taxonomy assignment',
            'event_key' => 'category_changed',
            'label_ar' => 'تغيير تصنيف العمل',
            'label_en' => 'Work category changed',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
        'work.tags.updated' => [
            'event_type' => 'work.tags.updated',
            'event_group' => 'taxonomy_assignment',
            'event_key' => 'tags_updated',
            'label_ar' => 'تحديث وسوم العمل',
            'label_en' => 'Work tags updated',
            'target_scope' => 'work',
            'requires_work' => true,
            'needs_attention' => false,
            'severity_fallback' => 'notice',
        ],
    ];

    /**
     * @return array<string, array{
     *     event_type: string,
     *     event_group: string,
     *     group_label_ar?: string,
     *     group_label_en?: string,
     *     event_key: string,
     *     label_ar: string,
     *     label_en: string,
     *     target_scope: string,
     *     requires_work: bool,
     *     needs_attention: bool,
     *     severity_fallback: string
     * }>
     */
    public function definitions(): array
    {
        return self::EVENT_DEFINITIONS;
    }

    /**
     * @return list<string>
     */
    public function supportedEventTypes(): array
    {
        return array_keys(self::EVENT_DEFINITIONS);
    }

    /**
     * @return list<string>
     */
    public function supportedEventGroups(): array
    {
        return array_values(array_unique(array_column(
            self::EVENT_DEFINITIONS,
            'event_group',
        )));
    }

    /**
     * @return list<string>
     */
    public function supportedTargetTypes(): array
    {
        return array_values(array_unique(array_column(
            self::EVENT_DEFINITIONS,
            'target_scope',
        )));
    }

    /**
     * @return list<string>
     */
    public function supportedSorts(): array
    {
        return self::SORTS;
    }

    /**
     * @return array{
     *     groups: list<array{key: string, label_ar: string, label_en: string}>,
     *     events: list<array{
     *         event_type: string,
     *         event_key: string,
     *         event_group: string,
     *         label_ar: string,
     *         label_en: string,
     *         target_scope: string,
     *         requires_work: bool,
     *         needs_attention: bool
     *     }>
     * }
     */
    public function eventCatalog(): array
    {
        $groups = [];
        $events = [];

        foreach (self::EVENT_DEFINITIONS as $definition) {
            if (isset($definition['group_label_ar'], $definition['group_label_en'])) {
                $groups[] = [
                    'key' => $definition['event_group'],
                    'label_ar' => $definition['group_label_ar'],
                    'label_en' => $definition['group_label_en'],
                ];
            }

            $events[] = [
                'event_type' => $definition['event_type'],
                'event_key' => $definition['event_key'],
                'event_group' => $definition['event_group'],
                'label_ar' => $definition['label_ar'],
                'label_en' => $definition['label_en'],
                'target_scope' => $definition['target_scope'],
                'requires_work' => $definition['requires_work'],
                'needs_attention' => $definition['needs_attention'],
            ];
        }

        return [
            'groups' => $groups,
            'events' => $events,
        ];
    }

    /**
     * @param array{
     *     event_type?: mixed,
     *     event_group?: mixed,
     *     actor_id?: mixed,
     *     target_type?: mixed,
     *     target_id?: mixed,
     *     work_id?: mixed,
     *     q?: mixed,
     *     from?: mixed,
     *     to?: mixed,
     *     outcome?: mixed
     * } $filters
     */
    public function query(
        array $filters = [],
        string $direction = 'desc',
        string $sort = 'event_at',
    ): Builder
    {
        $query = $this->baseQuery();
        $this->applyFilters($query, $filters);
        $this->applyOrder($query, $sort, $direction);

        return $query;
    }

    private function baseQuery(): Builder
    {
        $query = DB::table('audit_events')
            ->leftJoin('users as activity_actors', function (JoinClause $join): void {
                $join->on('activity_actors.id', '=', 'audit_events.actor_id')
                    ->where('audit_events.actor_type', '=', 'user');
            })
            ->leftJoin('works as direct_works', function (JoinClause $join): void {
                $join->on('direct_works.id', '=', 'audit_events.target_id')
                    ->where('audit_events.target_type', '=', 'work');
            })
            ->leftJoin('work_reports as activity_reports', function (JoinClause $join): void {
                $join->on('activity_reports.id', '=', 'audit_events.target_id')
                    ->where('audit_events.target_type', '=', 'work_report');
            })
            ->leftJoin('works as report_works', 'report_works.id', '=', 'activity_reports.work_id')
            ->where('audit_events.category', self::CATEGORY)
            ->whereIn('audit_events.event_type', $this->supportedEventTypes())
            ->select([
                'audit_events.id as audit_event_id',
                'audit_events.event_type',
                'audit_events.action',
                'audit_events.outcome',
                'audit_events.occurred_at',
                'audit_events.actor_id',
                'activity_actors.name as actor_name',
                'audit_events.actor_role',
                'audit_events.target_type',
                'audit_events.target_id',
            ])
            ->selectRaw('? as source', ['audit_events'])
            ->selectRaw('COALESCE(direct_works.id, report_works.id) as work_id')
            ->selectRaw('COALESCE(direct_works.title, report_works.title) as work_title')
            ->selectRaw('COALESCE(direct_works.slug, report_works.slug) as work_slug')
            ->selectRaw('COALESCE(direct_works.status, report_works.status) as work_status')
            ->selectRaw(
                'COALESCE(direct_works.visibility_status, report_works.visibility_status) as work_visibility_status',
            )
            ->selectRaw('COALESCE(direct_works.media_type, report_works.media_type) as work_media_type');

        $this->applySupportedEventTargets($query);
        $this->addDefinitionColumn($query, 'event_key', 'event_key');
        $this->addDefinitionColumn($query, 'event_group', 'event_group');
        $this->addDefinitionColumn($query, 'event_label_ar', 'label_ar');
        $this->addDefinitionColumn($query, 'event_label_en', 'label_en');
        $this->addDefinitionColumn($query, 'target_scope', 'target_scope');
        $this->addDefinitionColumn($query, 'requires_work', 'requires_work');
        $this->addDefinitionColumn($query, 'needs_attention', 'needs_attention');

        [$severityCase, $severityBindings] = $this->definitionCase('severity_fallback');
        $query->selectRaw(
            "COALESCE(audit_events.severity, {$severityCase}) as severity",
            $severityBindings,
        );

        return $query;
    }

    private function applySupportedEventTargets(Builder $query): void
    {
        /** @var array<string, list<string>> $eventTypesByTarget */
        $eventTypesByTarget = [];

        foreach (self::EVENT_DEFINITIONS as $eventType => $definition) {
            $eventTypesByTarget[$definition['target_scope']][] = $eventType;
        }

        $query->where(function (Builder $targetsQuery) use ($eventTypesByTarget): void {
            $firstTarget = true;

            foreach ($eventTypesByTarget as $targetType => $eventTypes) {
                $targetConstraint = function (Builder $targetQuery) use ($eventTypes, $targetType): void {
                    $targetQuery
                        ->where('audit_events.target_type', $targetType)
                        ->whereIn('audit_events.event_type', $eventTypes);
                };

                if ($firstTarget) {
                    $targetsQuery->where($targetConstraint);
                } else {
                    $targetsQuery->orWhere($targetConstraint);
                }

                $firstTarget = false;
            }
        });
    }

    /**
     * @param array{
     *     event_type?: mixed,
     *     event_group?: mixed,
     *     actor_id?: mixed,
     *     target_type?: mixed,
     *     target_id?: mixed,
     *     work_id?: mixed,
     *     q?: mixed,
     *     from?: mixed,
     *     to?: mixed,
     *     outcome?: mixed
     * } $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($this->filledScalar($filters['event_type'] ?? null)) {
            $query->where('audit_events.event_type', (string) $filters['event_type']);
        }

        if ($this->filledScalar($filters['event_group'] ?? null)) {
            $query->whereIn(
                'audit_events.event_type',
                $this->eventTypesForGroup((string) $filters['event_group']),
            );
        }

        foreach (['actor_id', 'target_id'] as $filter) {
            $id = $this->positiveInteger($filters[$filter] ?? null);

            if ($id !== null) {
                $query->where("audit_events.{$filter}", $id);
            }
        }

        if ($this->filledScalar($filters['target_type'] ?? null)) {
            $query->where('audit_events.target_type', (string) $filters['target_type']);
        }

        $workId = $this->positiveInteger($filters['work_id'] ?? null);

        if ($workId !== null) {
            $query->whereRaw('COALESCE(direct_works.id, report_works.id) = ?', [$workId]);
        }

        if ($this->filledScalar($filters['q'] ?? null)) {
            $needle = '%'.Str::lower(trim((string) $filters['q'])).'%';
            $query->where(function (Builder $searchQuery) use ($needle): void {
                foreach ([
                    'audit_events.event_type',
                    'audit_events.action',
                    'activity_actors.name',
                    'direct_works.title',
                    'report_works.title',
                    'direct_works.slug',
                    'report_works.slug',
                ] as $index => $column) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $searchQuery->{$method}("LOWER({$column}) LIKE ?", [$needle]);
                }
            });
        }

        foreach (['from' => '>=', 'to' => '<='] as $filter => $operator) {
            $value = $this->dateFilter($filters[$filter] ?? null);

            if ($value !== null) {
                $query->where('audit_events.occurred_at', $operator, $value);
            }
        }

        if ($this->filledScalar($filters['outcome'] ?? null)) {
            $query->where('audit_events.outcome', (string) $filters['outcome']);
        }
    }

    private function applyOrder(Builder $query, string $sort, string $direction): void
    {
        $safeDirection = in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction)
            : 'desc';
        $safeSort = in_array($sort, self::SORTS, true) ? $sort : 'event_at';

        match ($safeSort) {
            'audit_event_id' => $query->orderBy('audit_events.id', $safeDirection),
            'event_type' => $query->orderBy('audit_events.event_type', $safeDirection),
            'actor_name' => $query->orderBy('activity_actors.name', $safeDirection),
            'work_id' => $query->orderByRaw(
                "COALESCE(direct_works.id, report_works.id) {$safeDirection}",
            ),
            'work_title' => $query->orderByRaw(
                "COALESCE(direct_works.title, report_works.title) {$safeDirection}",
            ),
            default => $query->orderBy('audit_events.occurred_at', $safeDirection),
        };

        if ($safeSort !== 'audit_event_id') {
            $query->orderBy('audit_events.id', $safeDirection);
        }
    }

    private function addDefinitionColumn(Builder $query, string $alias, string $definitionKey): void
    {
        [$case, $bindings] = $this->definitionCase($definitionKey);
        $expression = in_array($definitionKey, ['requires_work', 'needs_attention'], true)
            ? "CAST({$case} AS INTEGER)"
            : $case;
        $query->selectRaw("{$expression} as {$alias}", $bindings);
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function definitionCase(string $definitionKey): array
    {
        $parts = ['CASE audit_events.event_type'];
        $bindings = [];

        foreach (self::EVENT_DEFINITIONS as $eventType => $definition) {
            $parts[] = 'WHEN ? THEN ?';
            $bindings[] = $eventType;
            $bindings[] = is_bool($definition[$definitionKey])
                ? (int) $definition[$definitionKey]
                : $definition[$definitionKey];
        }

        $parts[] = 'END';

        return [implode(' ', $parts), $bindings];
    }

    /**
     * @return list<string>
     */
    private function eventTypesForGroup(string $group): array
    {
        $types = [];

        foreach (self::EVENT_DEFINITIONS as $eventType => $definition) {
            if ($definition['event_group'] === $group) {
                $types[] = $eventType;
            }
        }

        return $types;
    }

    private function filledScalar(mixed $value): bool
    {
        return is_scalar($value) && trim((string) $value) !== '';
    }

    private function positiveInteger(mixed $value): ?int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $integer === false ? null : $integer;
    }

    private function dateFilter(mixed $value): DateTimeInterface|string|null
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        return $this->filledScalar($value) ? trim((string) $value) : null;
    }
}
