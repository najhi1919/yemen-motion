<?php

declare(strict_types=1);

namespace App\Services\Works;

use App\Exceptions\WorksMediaConflictException;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkMedia;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WorksMediaService
{
    private const DISK = 'works_private';

    /** @var list<string> */
    private const EDITABLE_STATUSES = [
        Work::STATUS_DRAFT,
        Work::STATUS_CHANGES_REQUESTED,
    ];

    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];

    /** @var list<string> */
    private const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** @var list<string> */
    private const VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/webm',
        'video/quicktime',
    ];

    public function __construct(
        private readonly WorksSettingsStore $settingsStore,
        private readonly AuditEventLogger $auditEventLogger,
    ) {}

    /** @return array<string, mixed> */
    public function index(int $workId): array
    {
        $work = Work::query()->findOrFail($workId);
        $settings = $this->settingsStore->getGlobalSettings();
        $media = WorkMedia::query()
            ->where('work_id', $work->id)
            ->with(['uploader:id,name'])
            ->ordered()
            ->get();

        return [
            'work' => $this->workPayload($work),
            'media' => $media
                ->map(fn (WorkMedia $item): array => $this->mediaPayload($item, $work))
                ->all(),
            'media_policy' => $this->mediaPolicy($work, $settings),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, string|null> $requestContext
     * @return array<string, mixed>
     */
    public function upload(
        int $workId,
        UploadedFile $file,
        array $settings,
        User $actor,
        array $requestContext,
    ): array {
        $work = Work::query()->findOrFail($workId);
        $this->assertEditable($work);
        $this->assertMediaTypeAllowed($work, $settings);

        $activeCount = WorkMedia::query()->where('work_id', $work->id)->count();
        $this->assertItemLimit($work, $settings, $activeCount);

        $fileMetadata = $this->validatedFileMetadata($work, $file, $settings);
        $path = 'works/'.$work->id.'/'.Str::uuid().'.'.$fileMetadata['extension'];
        $storedPath = Storage::disk(self::DISK)->putFileAs(
            'works/'.$work->id,
            $file,
            basename($path),
        );

        if ($storedPath !== $path) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk(self::DISK)->delete($storedPath);
            }

            throw new RuntimeException('تعذر تخزين ملف وسيط العمل.');
        }

        try {
            return DB::transaction(function () use (
                $workId,
                $fileMetadata,
                $path,
                $settings,
                $actor,
                $requestContext,
            ): array {
                $lockedWork = Work::query()->lockForUpdate()->findOrFail($workId);
                $this->assertEditable($lockedWork);
                $this->assertMediaTypeAllowed($lockedWork, $settings);
                $this->assertMimeMatchesMediaType(
                    $lockedWork,
                    $fileMetadata['mime_type'],
                );

                $activeQuery = WorkMedia::query()->where('work_id', $lockedWork->id);
                $activeCount = (clone $activeQuery)->count();
                $this->assertItemLimit($lockedWork, $settings, $activeCount);
                $position = ((int) (clone $activeQuery)->max('position')) + 1;

                $media = WorkMedia::query()->create([
                    'work_id' => $lockedWork->id,
                    'uploaded_by' => $actor->getKey(),
                    'disk' => self::DISK,
                    'path' => $path,
                    'original_name' => $fileMetadata['original_name'],
                    'mime_type' => $fileMetadata['mime_type'],
                    'extension' => $fileMetadata['extension'],
                    'kind' => $fileMetadata['kind'],
                    'size_bytes' => $fileMetadata['size_bytes'],
                    'position' => $position,
                    'width' => $fileMetadata['width'],
                    'height' => $fileMetadata['height'],
                    'duration_ms' => null,
                    'processing_status' => $fileMetadata['kind'] === WorkMedia::KIND_IMAGE
                        ? WorkMedia::PROCESSING_READY
                        : WorkMedia::PROCESSING_PENDING,
                    'processing_error' => null,
                ]);

                $this->recordAuditEvent(
                    $media,
                    $actor,
                    $requestContext,
                    'works.media.uploaded',
                    'upload',
                    [
                        'work_id' => $lockedWork->id,
                        'kind' => $media->kind,
                        'size_bytes' => $media->size_bytes,
                        'position' => $media->position,
                        'settings_version' => $this->settingsVersion($settings),
                    ],
                );

                $media->load('uploader:id,name');
                $active = $activeCount + 1;

                return [
                    'action' => 'upload',
                    'media' => $this->mediaPayload($media, $lockedWork),
                    'media_policy' => $this->mediaPolicy($lockedWork, $settings),
                    'counts' => $this->counts($lockedWork, $settings, $active),
                ];
            });
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($path);

            throw $exception;
        }
    }

    public function content(int $workId, int $mediaId): StreamedResponse
    {
        Work::query()->findOrFail($workId);
        $media = WorkMedia::query()
            ->where('work_id', $workId)
            ->whereKey($mediaId)
            ->first();

        if ($media === null || $media->disk !== self::DISK) {
            abort(404, 'وسيط العمل غير موجود.');
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($media->path)) {
            abort(404, 'ملف وسيط العمل غير موجود.');
        }

        return $disk->response(
            $media->path,
            $this->safeOriginalName($media->original_name, $media->extension),
            [
                'Content-Type' => $media->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    /**
     * @param array<string, string|null> $requestContext
     * @return array<string, mixed>
     */
    public function delete(
        int $workId,
        int $mediaId,
        User $actor,
        array $requestContext,
    ): array {
        $settings = $this->settingsStore->getGlobalSettings();

        return DB::transaction(function () use (
            $workId,
            $mediaId,
            $settings,
            $actor,
            $requestContext,
        ): array {
            $work = Work::query()->lockForUpdate()->findOrFail($workId);
            $media = WorkMedia::query()
                ->where('work_id', $work->id)
                ->whereKey($mediaId)
                ->lockForUpdate()
                ->first();

            if ($media === null) {
                abort(404, 'وسيط العمل غير موجود.');
            }

            $this->assertEditable($work);
            $wasCover = $work->cover_media_id === $media->id;

            if ($wasCover) {
                $work->forceFill(['cover_media_id' => null])->save();
            }

            $media->delete();

            $this->recordAuditEvent(
                $media,
                $actor,
                $requestContext,
                'works.media.deleted',
                'delete',
                [
                    'work_id' => $work->id,
                    'kind' => $media->kind,
                    'position' => $media->position,
                    'was_cover' => $wasCover,
                    'settings_version' => $this->settingsVersion($settings),
                ],
            );

            $active = WorkMedia::query()->where('work_id', $work->id)->count();

            return [
                'action' => 'delete',
                'deleted_media_id' => $media->id,
                'cover_cleared' => $wasCover,
                'physical_file_retained' => true,
                'counts' => $this->counts($work, $settings, $active),
            ];
        });
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function validatedFileMetadata(
        Work $work,
        UploadedFile $file,
        array $settings,
    ): array {
        $mimeType = $file->getMimeType();
        if (! is_string($mimeType)) {
            throw ValidationException::withMessages([
                'file' => ['نوع ملف وسيط العمل غير مسموح أو لا يطابق نمط العمل.'],
            ]);
        }
        $this->assertMimeMatchesMediaType($work, $mimeType);

        $sizeBytes = $file->getSize();

        if (! is_int($sizeBytes) || $sizeBytes < 1) {
            throw ValidationException::withMessages([
                'file' => ['تعذر التحقق من الحجم الفعلي لملف وسيط العمل.'],
            ]);
        }

        $maxFileSizeKb = $this->mediaLimits($settings)['max_file_size_kb'];

        if (is_int($maxFileSizeKb) && $sizeBytes > ($maxFileSizeKb * 1024)) {
            throw ValidationException::withMessages([
                'file' => ['يتجاوز ملف وسيط العمل الحد الأقصى المسموح للحجم.'],
            ]);
        }

        $kind = $work->media_type === Work::MEDIA_TYPE_VIDEO
            ? WorkMedia::KIND_VIDEO
            : WorkMedia::KIND_IMAGE;
        $width = null;
        $height = null;

        if ($kind === WorkMedia::KIND_IMAGE) {
            $dimensions = @getimagesize($file->getRealPath());

            if ($dimensions === false
                || ! isset($dimensions[0], $dimensions[1])
                || $dimensions[0] < 1
                || $dimensions[1] < 1) {
                throw ValidationException::withMessages([
                    'file' => ['تعذر قراءة أبعاد صورة وسيط العمل بصورة آمنة.'],
                ]);
            }

            $width = (int) $dimensions[0];
            $height = (int) $dimensions[1];
        }

        return [
            'original_name' => $this->safeOriginalName(
                $file->getClientOriginalName(),
                self::MIME_EXTENSIONS[$mimeType],
            ),
            'mime_type' => $mimeType,
            'extension' => self::MIME_EXTENSIONS[$mimeType],
            'kind' => $kind,
            'size_bytes' => $sizeBytes,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function assertMimeMatchesMediaType(Work $work, string $mimeType): void
    {
        if (! in_array($mimeType, $this->allowedMimeTypes($work->media_type), true)) {
            throw ValidationException::withMessages([
                'file' => ['نوع ملف وسيط العمل غير مسموح أو لا يطابق نمط العمل.'],
            ]);
        }
    }

    private function assertEditable(Work $work): void
    {
        if (! in_array($work->status, self::EDITABLE_STATUSES, true)) {
            throw new WorksMediaConflictException(
                'work_state_not_editable',
                ['current_status' => $work->status],
                'لا يمكن تعديل وسائط العمل في حالته الحالية.',
            );
        }
    }

    /** @param array<string, mixed> $settings */
    private function assertMediaTypeAllowed(Work $work, array $settings): void
    {
        if ($work->media_type === null) {
            throw new WorksMediaConflictException(
                'media_type_required',
                [],
                'يجب تحديد نمط وسائط العمل قبل رفع الملفات.',
            );
        }

        $allowedTypes = $this->allowedMediaTypes($settings);

        if (! in_array($work->media_type, $allowedTypes, true)) {
            throw new WorksMediaConflictException(
                'media_type_not_allowed',
                [
                    'current_media_type' => $work->media_type,
                    'allowed_media_types' => $allowedTypes,
                    'settings_version' => $this->settingsVersion($settings),
                ],
                'نمط وسائط العمل غير مسموح وفق الإعدادات الحالية.',
            );
        }
    }

    /** @param array<string, mixed> $settings */
    private function assertItemLimit(Work $work, array $settings, int $currentCount): void
    {
        $effectiveMax = $this->effectiveMaxItems($work->media_type, $settings);

        if ($effectiveMax !== null && $currentCount >= $effectiveMax) {
            throw new WorksMediaConflictException(
                'media_items_limit_reached',
                [
                    'current_count' => $currentCount,
                    'effective_max_items' => $effectiveMax,
                    'settings_version' => $this->settingsVersion($settings),
                ],
                'بلغ العمل الحد الأقصى لعناصر الوسائط.',
            );
        }
    }

    /** @param array<string, mixed> $settings */
    private function mediaPolicy(Work $work, array $settings): array
    {
        $limits = $this->mediaLimits($settings);

        return [
            'source' => 'work_settings',
            'settings_version' => $this->settingsVersion($settings),
            'work_media_type' => $work->media_type,
            'allowed_media_types' => $this->allowedMediaTypes($settings),
            'allowed_file_kinds' => $this->allowedFileKinds($work->media_type),
            'allowed_mime_types' => $this->allowedMimeTypes($work->media_type),
            'configured_limits' => [
                'max_items' => $limits['max_items'],
                'max_file_size_kb' => $limits['max_file_size_kb'],
            ],
            'effective_limits' => [
                'max_items' => $this->effectiveMaxItems($work->media_type, $settings),
                'max_file_size_kb' => $limits['max_file_size_kb'],
            ],
            'enforcement' => [
                'media_type' => true,
                'max_items' => true,
                'max_file_size_kb' => true,
                'mime_type' => true,
            ],
        ];
    }

    /** @param array<string, mixed> $settings */
    private function counts(Work $work, array $settings, int $active): array
    {
        $effectiveMax = $this->effectiveMaxItems($work->media_type, $settings);

        return [
            'active' => $active,
            'remaining' => $effectiveMax === null
                ? null
                : max(0, $effectiveMax - $active),
        ];
    }

    /** @return array<string, mixed> */
    private function workPayload(Work $work): array
    {
        return [
            'id' => $work->id,
            'status' => $work->status,
            'media_type' => $work->media_type,
            'cover_media_id' => $work->cover_media_id,
        ];
    }

    /** @return array<string, mixed> */
    private function mediaPayload(WorkMedia $media, Work $work): array
    {
        return [
            'id' => $media->id,
            'kind' => $media->kind,
            'original_name' => $media->original_name,
            'mime_type' => $media->mime_type,
            'extension' => $media->extension,
            'size_bytes' => $media->size_bytes,
            'size_kb' => round($media->size_bytes / 1024, 2),
            'position' => $media->position,
            'width' => $media->width,
            'height' => $media->height,
            'duration_ms' => $media->duration_ms,
            'processing_status' => $media->processing_status,
            'is_cover' => $work->cover_media_id === $media->id,
            'uploaded_by' => $media->uploader === null
                ? null
                : [
                    'id' => $media->uploader->id,
                    'name' => $media->uploader->name,
                ],
            'created_at' => $media->created_at?->toJSON(),
            'updated_at' => $media->updated_at?->toJSON(),
            'content_endpoint' => '/api/admin/works/'.$work->id
                .'/media/'.$media->id.'/content',
        ];
    }

    /** @param array<string, mixed> $settings */
    private function allowedMediaTypes(array $settings): array
    {
        return $this->mediaLimits($settings)['allowed_types'] ?? Work::MEDIA_TYPES;
    }

    /** @return list<string> */
    private function allowedFileKinds(?string $mediaType): array
    {
        return match ($mediaType) {
            Work::MEDIA_TYPE_IMAGE,
            Work::MEDIA_TYPE_GALLERY => [WorkMedia::KIND_IMAGE],
            Work::MEDIA_TYPE_VIDEO => [WorkMedia::KIND_VIDEO],
            default => [],
        };
    }

    /** @return list<string> */
    private function allowedMimeTypes(?string $mediaType): array
    {
        return match ($mediaType) {
            Work::MEDIA_TYPE_IMAGE,
            Work::MEDIA_TYPE_GALLERY => self::IMAGE_MIME_TYPES,
            Work::MEDIA_TYPE_VIDEO => self::VIDEO_MIME_TYPES,
            default => [],
        };
    }

    /** @param array<string, mixed> $settings */
    private function effectiveMaxItems(?string $mediaType, array $settings): ?int
    {
        if ($mediaType === null) {
            return null;
        }

        $configuredMax = $this->mediaLimits($settings)['max_items'];

        if (in_array($mediaType, [Work::MEDIA_TYPE_IMAGE, Work::MEDIA_TYPE_VIDEO], true)) {
            return is_int($configuredMax) ? min(1, $configuredMax) : 1;
        }

        return $mediaType === Work::MEDIA_TYPE_GALLERY && is_int($configuredMax)
            ? $configuredMax
            : null;
    }

    /** @param array<string, mixed> $settings */
    private function mediaLimits(array $settings): array
    {
        $limits = $settings['values']['media_limits'] ?? [];

        if (! is_array($limits)) {
            $limits = [];
        }

        return [
            'max_items' => is_int($limits['max_items'] ?? null)
                ? $limits['max_items']
                : null,
            'max_file_size_kb' => is_int($limits['max_file_size_kb'] ?? null)
                ? $limits['max_file_size_kb']
                : null,
            'allowed_types' => is_array($limits['allowed_types'] ?? null)
                ? array_values($limits['allowed_types'])
                : null,
        ];
    }

    /** @param array<string, mixed> $settings */
    private function settingsVersion(array $settings): int
    {
        return (int) ($settings['version'] ?? 1);
    }

    private function safeOriginalName(string $originalName, ?string $extension): string
    {
        $name = basename(str_replace('\\', '/', $originalName));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = Str::limit(trim($name), 200, '');

        return $name !== ''
            ? $name
            : 'media'.($extension ? '.'.$extension : '');
    }

    /**
     * @param array<string, string|null> $requestContext
     * @param array<string, mixed> $metadata
     */
    private function recordAuditEvent(
        WorkMedia $media,
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
            'target_type' => 'work_media',
            'target_id' => $media->getKey(),
            'action' => $action,
            'outcome' => 'success',
            'ip_address' => $requestContext['ip_address'] ?? null,
            'user_agent' => $requestContext['user_agent'] ?? null,
            'metadata' => $metadata,
        ]);
    }
}
