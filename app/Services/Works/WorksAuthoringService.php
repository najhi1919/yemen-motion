<?php

declare(strict_types=1);

namespace App\Services\Works;

use App\Exceptions\WorksAuthoringStateConflictException;
use App\Models\User;
use App\Models\Work;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WorksAuthoringService
{
    /** @var list<string> */
    private const AUTHORING_FIELDS = [
        'title',
        'summary',
        'description',
        'media_type',
        'price_amount',
        'delivery_days',
        'designer_id',
        'internal_notes',
    ];

    /** @var list<string> */
    private const EDITABLE_STATUSES = [
        Work::STATUS_DRAFT,
        Work::STATUS_CHANGES_REQUESTED,
    ];

    public function __construct(private readonly AuditEventLogger $auditEventLogger) {}

    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $settings
     * @param array<string, string|null> $requestContext
     * @return array{work: Work, changed: true, changed_keys: list<string>}
     */
    public function createDraft(
        array $validated,
        array $settings,
        User $actor,
        array $requestContext,
    ): array {
        return DB::transaction(function () use (
            $validated,
            $settings,
            $actor,
            $requestContext,
        ): array {
            $attributes = $this->normalizedAuthoringAttributes($validated);
            $work = new Work();

            $work->title = $attributes['title'];
            $work->slug = $this->uniqueSlug($attributes['title']);
            $work->summary = $attributes['summary'] ?? null;
            $work->description = $attributes['description'] ?? null;
            $work->status = Work::STATUS_DRAFT;
            $work->visibility_status = Work::VISIBILITY_HIDDEN;
            $work->media_type = $attributes['media_type'] ?? null;
            $work->price_amount = $attributes['price_amount'] ?? null;
            $work->delivery_days = $attributes['delivery_days'] ?? null;
            $work->designer_id = $attributes['designer_id'] ?? null;
            $work->reviewer_id = null;
            $work->category_id = null;
            $work->cover_media_id = null;
            $work->is_featured = false;
            $work->is_pinned = false;
            $work->is_trusted_direct_publish = false;
            $work->views_count = 0;
            $work->likes_count = 0;
            $work->reports_count = 0;
            $work->submitted_at = null;
            $work->reviewed_at = null;
            $work->approved_at = null;
            $work->published_at = null;
            $work->rejected_at = null;
            $work->hidden_at = null;
            $work->archived_at = null;
            $work->rejection_reason = null;
            $work->change_request_notes = null;
            $work->internal_notes = $attributes['internal_notes'] ?? null;
            $work->save();
            $work->refresh();

            $changedKeys = array_values(array_intersect(
                self::AUTHORING_FIELDS,
                array_keys($validated),
            ));

            $this->recordAuditEvent(
                $work,
                $actor,
                $requestContext,
                'works.authoring.created',
                'create',
                [
                    'status' => $work->status,
                    'changed_keys' => $changedKeys,
                    'settings_version' => (int) ($settings['version'] ?? 1),
                    'initial_status' => Work::STATUS_DRAFT,
                ],
            );

            return [
                'work' => $work,
                'changed' => true,
                'changed_keys' => $changedKeys,
            ];
        });
    }

    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $settings
     * @param array<string, string|null> $requestContext
     * @return array{work: Work, changed: bool, changed_keys: list<string>}
     *
     * @throws WorksAuthoringStateConflictException
     */
    public function updateDraft(
        int $workId,
        array $validated,
        array $settings,
        User $actor,
        array $requestContext,
    ): array {
        return DB::transaction(function () use (
            $workId,
            $validated,
            $settings,
            $actor,
            $requestContext,
        ): array {
            $work = Work::query()
                ->whereKey($workId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($work->status, self::EDITABLE_STATUSES, true)) {
                throw new WorksAuthoringStateConflictException($work->status);
            }

            $normalized = $this->normalizedAuthoringAttributes($validated);
            $changes = [];

            foreach (self::AUTHORING_FIELDS as $field) {
                if (! array_key_exists($field, $normalized)) {
                    continue;
                }

                if ($work->getAttribute($field) !== $normalized[$field]) {
                    $changes[$field] = $normalized[$field];
                }
            }

            if ($changes === []) {
                return [
                    'work' => $work,
                    'changed' => false,
                    'changed_keys' => [],
                ];
            }

            foreach ($changes as $field => $value) {
                $work->setAttribute($field, $value);
            }

            $work->save();
            $work->refresh();
            $changedKeys = array_keys($changes);

            $this->recordAuditEvent(
                $work,
                $actor,
                $requestContext,
                'works.authoring.updated',
                'update',
                [
                    'status' => $work->status,
                    'changed_keys' => $changedKeys,
                    'settings_version' => (int) ($settings['version'] ?? 1),
                ],
            );

            return [
                'work' => $work,
                'changed' => true,
                'changed_keys' => $changedKeys,
            ];
        });
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function normalizedAuthoringAttributes(array $validated): array
    {
        $attributes = [];

        foreach (self::AUTHORING_FIELDS as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $value = $validated[$field];

            $attributes[$field] = match ($field) {
                'price_amount' => $value === null
                    ? null
                    : number_format((float) $value, 2, '.', ''),
                'delivery_days', 'designer_id' => $value === null ? null : (int) $value,
                default => $value,
            };
        }

        return $attributes;
    }

    private function uniqueSlug(string $title): string
    {
        $title = trim($title);
        $base = preg_match('/[\pL\pN]/u', $title) === 1
            ? Str::slug($title)
            : 'work';
        $base = $base !== '' ? Str::limit($base, 140, '') : 'work';

        if (! Work::query()->where('slug', $base)->exists()) {
            return $base;
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $base.'-'.Str::lower(Str::random(12));

            if (! Work::query()->where('slug', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('تعذر إنشاء معرف رابط فريد للعمل.');
    }

    /**
     * @param array<string, string|null> $requestContext
     * @param array<string, mixed> $metadata
     */
    private function recordAuditEvent(
        Work $work,
        User $actor,
        array $requestContext,
        string $eventType,
        string $action,
        array $metadata,
    ): void {
        $this->auditEventLogger->record([
            'event_type' => $eventType,
            'category' => 'works',
            'severity' => 'notice',
            'actor_type' => 'user',
            'actor_id' => $actor->getKey(),
            'actor_role' => $actor->roles->first()?->name,
            'target_type' => 'work',
            'target_id' => $work->getKey(),
            'action' => $action,
            'outcome' => 'success',
            'ip_address' => $requestContext['ip_address'] ?? null,
            'user_agent' => $requestContext['user_agent'] ?? null,
            'metadata' => $metadata,
        ]);
    }
}
