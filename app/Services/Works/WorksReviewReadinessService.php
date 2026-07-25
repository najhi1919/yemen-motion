<?php

declare(strict_types=1);

namespace App\Services\Works;

use App\Models\Work;
use App\Models\WorkMedia;

class WorksReviewReadinessService
{
    public function __construct(private readonly WorksMediaService $mediaService) {}

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function evaluate(Work $work, array $settings, bool $canViewPrivateNotes = false): array
    {
        $work->loadMissing(['category', 'tags']);
        $media = $work->media()->get();
        $policy = $this->mediaService->readinessPolicy($work, $settings);
        $sections = [
            'status' => [],
            'content' => [],
            'organization' => [],
            'media' => [],
        ];

        if (! in_array($work->status, [Work::STATUS_DRAFT, Work::STATUS_CHANGES_REQUESTED], true)) {
            $this->add($sections, 'status', 'invalid_status', 'blocker', false, 'authoring-review');
        }

        if (mb_strlen(trim((string) $work->title)) < 2) {
            $this->add($sections, 'content', 'title_missing', 'blocker', false, 'authoring-basic');
        }
        $this->requiredText($sections, 'content', 'summary_missing', $work->summary, 'authoring-basic');
        $this->requiredText($sections, 'content', 'description_missing', $work->description, 'authoring-basic');

        if (blank($work->media_type)) {
            $this->add($sections, 'content', 'media_type_missing', 'blocker', false, 'authoring-basic');
        } elseif (! in_array($work->media_type, $policy['allowed_media_types'], true)) {
            $this->add($sections, 'content', 'media_type_not_allowed', 'blocker', false, 'authoring-basic');
        }

        if ($work->category_id === null) {
            $this->add($sections, 'organization', 'category_missing', 'blocker', false, 'authoring-taxonomy');
        } elseif ($work->category === null) {
            $this->add($sections, 'organization', 'category_invalid', 'blocker', false, 'authoring-taxonomy');
        } elseif (! $work->category->isActive()) {
            $this->add($sections, 'organization', 'category_inactive', 'blocker', false, 'authoring-taxonomy');
        }

        if ($media->isEmpty()) {
            $this->add($sections, 'media', 'media_missing', 'blocker', false, 'authoring-media');
        }

        $maximum = $policy['effective_limits']['max_items'] ?? null;
        if (is_int($maximum) && $media->count() > $maximum) {
            $this->add($sections, 'media', 'media_limit_exceeded', 'blocker', false, 'authoring-media');
        }

        if ($media->contains(fn (WorkMedia $item): bool => ! $this->mediaService->isCompatibleForReview($work, $item))) {
            $this->add($sections, 'media', 'media_type_mismatch', 'blocker', false, 'authoring-media');
        }
        if ($media->contains(fn (WorkMedia $item): bool => $item->processing_status === WorkMedia::PROCESSING_PENDING)) {
            $this->add($sections, 'media', 'media_processing_pending', 'blocker', false, 'authoring-media');
        }
        if ($media->contains(fn (WorkMedia $item): bool => $item->processing_status === WorkMedia::PROCESSING_FAILED)) {
            $this->add($sections, 'media', 'media_processing_failed', 'blocker', false, 'authoring-media');
        }
        if ($media->contains(fn (WorkMedia $item): bool => ! in_array($item->processing_status, WorkMedia::PROCESSING_STATUSES, true))) {
            $this->add($sections, 'media', 'media_invalid', 'blocker', false, 'authoring-media');
        }

        $cover = $work->cover_media_id === null
            ? null
            : $media->firstWhere('id', $work->cover_media_id);
        if ($work->cover_media_id !== null && $cover === null) {
            $this->add($sections, 'media', 'cover_invalid', 'blocker', false, 'authoring-media');
        } elseif ($cover instanceof WorkMedia) {
            if ($cover->kind !== WorkMedia::KIND_IMAGE) {
                $this->add($sections, 'media', 'cover_invalid', 'blocker', false, 'authoring-media');
            } elseif ($cover->processing_status !== WorkMedia::PROCESSING_READY) {
                $this->add($sections, 'media', 'cover_not_ready', 'blocker', false, 'authoring-media');
            }
        }
        if (
            in_array($work->media_type, [Work::MEDIA_TYPE_IMAGE, Work::MEDIA_TYPE_GALLERY], true)
            && $work->cover_media_id === null
        ) {
            $this->add($sections, 'media', 'cover_missing', 'blocker', false, 'authoring-media');
        }

        $this->warning($sections, 'organization', 'designer_missing', $work->designer_id !== null, 'authoring-basic');
        $this->warning($sections, 'organization', 'tags_missing', $work->tags->isNotEmpty(), 'authoring-taxonomy');
        $this->warning($sections, 'content', 'price_missing', $work->price_amount !== null, 'authoring-basic');
        $this->warning($sections, 'content', 'delivery_missing', $work->delivery_days !== null, 'authoring-basic');
        $this->warning($sections, 'content', 'summary_short', mb_strlen(trim((string) $work->summary)) >= 80, 'authoring-basic');
        $this->warning($sections, 'content', 'description_short', mb_strlen(trim((string) $work->description)) >= 200, 'authoring-basic');
        if ($canViewPrivateNotes) {
            $this->warning($sections, 'content', 'internal_notes_missing', filled(trim((string) $work->internal_notes)), 'authoring-basic');
        }

        $payloadSections = collect($sections)->map(function (array $items, string $key): array {
            $blocked = collect($items)->contains(fn (array $item): bool => $item['severity'] === 'blocker');
            $warned = collect($items)->contains(fn (array $item): bool => $item['severity'] === 'warning');

            return [
                'key' => $key,
                'status' => $blocked ? 'blocked' : ($warned ? 'warning' : 'ready'),
                'items' => array_values($items),
            ];
        })->values()->all();
        $blockers = collect($payloadSections)->flatMap(fn (array $section) => $section['items'])
            ->where('severity', 'blocker')->count();
        $warnings = collect($payloadSections)->flatMap(fn (array $section) => $section['items'])
            ->where('severity', 'warning')->count();

        return [
            'ready' => $blockers === 0,
            'blockers_count' => $blockers,
            'warnings_count' => $warnings,
            'evaluated_at' => now()->toJSON(),
            'work_updated_at' => $work->updated_at?->toJSON(),
            'sections' => $payloadSections,
        ];
    }

    /** @param array<string, list<array<string, mixed>>> $sections */
    private function requiredText(array &$sections, string $section, string $code, ?string $value, string $target): void
    {
        if (blank(trim((string) $value))) {
            $this->add($sections, $section, $code, 'blocker', false, $target);
        }
    }

    /** @param array<string, list<array<string, mixed>>> $sections */
    private function warning(array &$sections, string $section, string $code, bool $satisfied, string $target): void
    {
        if (! $satisfied) {
            $this->add($sections, $section, $code, 'warning', false, $target);
        }
    }

    /** @param array<string, list<array<string, mixed>>> $sections */
    private function add(array &$sections, string $section, string $code, string $severity, bool $satisfied, string $target): void
    {
        $sections[$section][] = compact('code', 'severity', 'satisfied', 'target');
    }
}
