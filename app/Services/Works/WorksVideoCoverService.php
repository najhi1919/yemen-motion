<?php

namespace App\Services\Works;

use App\Exceptions\WorksMediaConflictException;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkMedia;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class WorksVideoCoverService
{
    public const DISK = 'works_private';

    public const EDITABLE_STATUSES = [
        Work::STATUS_DRAFT,
        Work::STATUS_CHANGES_REQUESTED,
    ];

    public const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private readonly WorksVideoCoverImageGenerator $imageGenerator,
        private readonly WorksSettingsStore $settingsStore,
        private readonly AuditEventLogger $auditEventLogger,
    ) {}

    /**
     * @param array<string, string|null> $requestContext
     * @return array<string, mixed>
     */
    public function useCurrentPoster(
        int $workId,
        int $mediaId,
        User $actor,
        array $requestContext,
    ): array {
        $this->validatedVideo($workId, $mediaId);

        return DB::transaction(function () use (
            $workId,
            $mediaId,
            $actor,
            $requestContext,
        ): array {
            [$work, $media] = $this->validatedVideo($workId, $mediaId, true);
            $this->assertPosterAvailable($media);
            $changed = $work->cover_media_id !== $media->id;

            if ($changed) {
                $work->forceFill(['cover_media_id' => $media->id])->save();
                $this->recordAudit(
                    $media,
                    $actor,
                    $requestContext,
                    'use_current_video_poster',
                    [
                        'work_id' => $work->id,
                        'media_id' => $media->id,
                        'mode' => 'current_poster',
                    ],
                );
            }

            return $this->response(
                'use_current_video_poster',
                $changed,
                $work,
                $media,
                'current_poster',
            );
        });
    }

    /**
     * @param array<string, string|null> $requestContext
     * @return array<string, mixed>
     */
    public function selectFrame(
        int $workId,
        int $mediaId,
        int $timeMs,
        User $actor,
        array $requestContext,
    ): array {
        [, $media] = $this->validatedVideo($workId, $mediaId);
        $this->assertFrameTime($media, $timeMs);

        $temporaryDirectory = $this->temporaryDirectory();
        $temporaryVideo = $temporaryDirectory.'/'.Str::uuid().'.video';
        $temporaryCover = $temporaryDirectory.'/'.Str::uuid().'.jpg';
        $newPath = $this->coverPath($workId, $mediaId);
        $stored = false;
        $committed = false;

        try {
            $this->copyPrivateVideo($media->path, $temporaryVideo);
            $this->imageGenerator->generateFrame(
                $temporaryVideo,
                $temporaryCover,
                $timeMs,
            );
            $this->storeGeneratedCover($temporaryCover, $newPath);
            $stored = true;

            $transaction = DB::transaction(function () use (
                $workId,
                $mediaId,
                $timeMs,
                $newPath,
                $actor,
                $requestContext,
            ): array {
                [$work, $lockedMedia] = $this->validatedVideo($workId, $mediaId, true);
                $this->assertFrameTime($lockedMedia, $timeMs);
                $oldPosterPath = $lockedMedia->poster_path;

                $lockedMedia->forceFill(['poster_path' => $newPath])->save();
                $work->forceFill(['cover_media_id' => $lockedMedia->id])->save();

                $this->recordAudit(
                    $lockedMedia,
                    $actor,
                    $requestContext,
                    'select_video_frame',
                    [
                        'work_id' => $work->id,
                        'media_id' => $lockedMedia->id,
                        'mode' => 'frame',
                        'time_ms' => $timeMs,
                    ],
                );

                return [
                    'data' => $this->response(
                        'select_video_frame',
                        true,
                        $work,
                        $lockedMedia,
                        'frame',
                        $timeMs,
                    ),
                    'old_poster_path' => $oldPosterPath,
                ];
            });

            $committed = true;
            $this->deleteReplacedPoster($transaction['old_poster_path'], $newPath);

            return $transaction['data'];
        } catch (Throwable $exception) {
            if ($stored && ! $committed) {
                Storage::disk(self::DISK)->delete($newPath);
            }

            throw $exception;
        } finally {
            @unlink($temporaryVideo);
            @unlink($temporaryCover);
        }
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, string|null> $requestContext
     * @return array<string, mixed>
     */
    public function uploadCover(
        int $workId,
        int $mediaId,
        UploadedFile $file,
        array $settings,
        User $actor,
        array $requestContext,
    ): array {
        $this->validatedVideo($workId, $mediaId);
        $metadata = $this->validatedImageMetadata(
            $file,
            $settings === [] ? $this->settingsStore->getGlobalSettings() : $settings,
        );

        $temporaryDirectory = $this->temporaryDirectory();
        $temporaryCover = $temporaryDirectory.'/'.Str::uuid().'.jpg';
        $newPath = $this->coverPath($workId, $mediaId);
        $stored = false;
        $committed = false;

        try {
            $this->imageGenerator->normalizeImage($file->getRealPath(), $temporaryCover);
            $this->storeGeneratedCover($temporaryCover, $newPath);
            $stored = true;

            $transaction = DB::transaction(function () use (
                $workId,
                $mediaId,
                $newPath,
                $metadata,
                $actor,
                $requestContext,
            ): array {
                [$work, $media] = $this->validatedVideo($workId, $mediaId, true);
                $oldPosterPath = $media->poster_path;

                $media->forceFill(['poster_path' => $newPath])->save();
                $work->forceFill(['cover_media_id' => $media->id])->save();

                $this->recordAudit(
                    $media,
                    $actor,
                    $requestContext,
                    'upload_video_cover',
                    [
                        'work_id' => $work->id,
                        'media_id' => $media->id,
                        'mode' => 'uploaded_image',
                        'original_mime_type' => $metadata['mime_type'],
                        'size_bytes' => $metadata['size_bytes'],
                        'width' => $metadata['width'],
                        'height' => $metadata['height'],
                    ],
                );

                return [
                    'data' => $this->response(
                        'upload_video_cover',
                        true,
                        $work,
                        $media,
                        'uploaded_image',
                    ),
                    'old_poster_path' => $oldPosterPath,
                ];
            });

            $committed = true;
            $this->deleteReplacedPoster($transaction['old_poster_path'], $newPath);

            return $transaction['data'];
        } catch (Throwable $exception) {
            if ($stored && ! $committed) {
                Storage::disk(self::DISK)->delete($newPath);
            }

            throw $exception;
        } finally {
            @unlink($temporaryCover);
        }
    }

    /**
     * @return array{0: Work, 1: WorkMedia}
     */
    private function validatedVideo(int $workId, int $mediaId, bool $lock = false): array
    {
        $workQuery = Work::query()->whereKey($workId);
        if ($lock) {
            $workQuery->lockForUpdate();
        }
        $work = $workQuery->firstOrFail();
        $this->assertEditable($work);

        if ($work->media_type !== Work::MEDIA_TYPE_VIDEO) {
            throw ValidationException::withMessages([
                'work' => ['إدارة غلاف الفيديو متاحة لأعمال الفيديو فقط.'],
            ]);
        }

        $mediaQuery = WorkMedia::query()
            ->where('work_id', $work->id)
            ->whereKey($mediaId);
        if ($lock) {
            $mediaQuery->lockForUpdate();
        }
        $media = $mediaQuery->first();

        if ($media === null) {
            abort(404, 'وسيط العمل غير موجود.');
        }

        if ($media->kind !== WorkMedia::KIND_VIDEO) {
            throw ValidationException::withMessages([
                'media' => ['يجب أن يكون وسيط الغلاف فيديو.'],
            ]);
        }

        if ($media->processing_status !== WorkMedia::PROCESSING_READY) {
            throw ValidationException::withMessages([
                'media' => ['يجب أن يكون الفيديو جاهزًا قبل إدارة غلافه.'],
            ]);
        }

        if ($media->disk !== self::DISK) {
            throw ValidationException::withMessages([
                'media' => ['وسيط الفيديو غير متاح على القرص الخاص المعتمد.'],
            ]);
        }

        if (! Storage::disk(self::DISK)->exists($media->path)) {
            throw ValidationException::withMessages([
                'media' => ['ملف الفيديو الأصلي غير متاح.'],
            ]);
        }

        return [$work, $media];
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

    private function assertPosterAvailable(WorkMedia $media): void
    {
        if ($media->poster_path === null
            || ! Storage::disk(self::DISK)->exists($media->poster_path)) {
            throw ValidationException::withMessages([
                'cover_media_id' => ['صورة معاينة الفيديو غير متاحة.'],
            ]);
        }
    }

    private function assertFrameTime(WorkMedia $media, int $timeMs): void
    {
        if (! is_int($media->duration_ms) || $media->duration_ms < 1) {
            throw ValidationException::withMessages([
                'time_ms' => ['مدة الفيديو غير متاحة لاختيار لقطة الغلاف.'],
            ]);
        }

        if ($timeMs < 0 || $timeMs >= $media->duration_ms) {
            throw ValidationException::withMessages([
                'time_ms' => ['يجب أن يقع زمن اللقطة داخل مدة الفيديو.'],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{mime_type: string, size_bytes: int, width: int, height: int}
     */
    private function validatedImageMetadata(UploadedFile $file, array $settings): array
    {
        $mimeType = $file->getMimeType();
        if (! is_string($mimeType)
            || ! in_array($mimeType, self::IMAGE_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'file' => ['نوع صورة الغلاف غير مدعوم أو لا يطابق محتوى الملف.'],
            ]);
        }

        $sizeBytes = $file->getSize();
        if (! is_int($sizeBytes) || $sizeBytes < 1) {
            throw ValidationException::withMessages([
                'file' => ['تعذر التحقق من الحجم الفعلي لصورة الغلاف.'],
            ]);
        }

        $maximumKb = $this->effectiveMaxFileSizeKb($settings);
        if (is_int($maximumKb) && $sizeBytes > ($maximumKb * 1024)) {
            throw ValidationException::withMessages([
                'file' => ['تتجاوز صورة الغلاف الحد الأقصى المسموح للحجم.'],
            ]);
        }

        $dimensions = @getimagesize($file->getRealPath());
        if ($dimensions === false
            || ! isset($dimensions[0], $dimensions[1])
            || $dimensions[0] < 1
            || $dimensions[1] < 1) {
            throw ValidationException::withMessages([
                'file' => ['تعذر قراءة أبعاد صورة الغلاف بصورة آمنة.'],
            ]);
        }

        return [
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'width' => (int) $dimensions[0],
            'height' => (int) $dimensions[1],
        ];
    }

    private function temporaryDirectory(): string
    {
        $path = storage_path('app/tmp/works-video-cover');
        File::ensureDirectoryExists($path, 0700, true);

        return $path;
    }

    private function copyPrivateVideo(string $storagePath, string $localPath): void
    {
        $source = Storage::disk(self::DISK)->readStream($storagePath);
        $destination = @fopen($localPath, 'wb');

        if (! is_resource($source) || ! is_resource($destination)) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($destination)) {
                fclose($destination);
            }

            throw new RuntimeException('تعذر تجهيز ملف الفيديو لاستخراج الغلاف.');
        }

        try {
            $copied = stream_copy_to_stream($source, $destination);
        } finally {
            fclose($source);
            fclose($destination);
        }

        if ($copied === false || $copied < 1) {
            @unlink($localPath);

            throw new RuntimeException('تعذر تجهيز ملف الفيديو لاستخراج الغلاف.');
        }
    }

    private function storeGeneratedCover(string $localPath, string $storagePath): void
    {
        $stream = @fopen($localPath, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('تعذر قراءة صورة غلاف الفيديو المجهزة.');
        }

        try {
            try {
                $stored = Storage::disk(self::DISK)->put($storagePath, $stream);
            } finally {
                fclose($stream);
            }

            if (! $stored
                || ! Storage::disk(self::DISK)->exists($storagePath)
                || Storage::disk(self::DISK)->size($storagePath) < 1) {
                throw new RuntimeException('تعذر تخزين صورة غلاف الفيديو.');
            }
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($storagePath);

            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('تعذر تخزين صورة غلاف الفيديو.');
        }
    }

    private function coverPath(int $workId, int $mediaId): string
    {
        return "works/{$workId}/derived/{$mediaId}-cover-".Str::uuid().'.jpg';
    }

    private function deleteReplacedPoster(?string $oldPath, string $newPath): void
    {
        if ($oldPath !== null
            && $oldPath !== $newPath
            && Storage::disk(self::DISK)->exists($oldPath)) {
            Storage::disk(self::DISK)->delete($oldPath);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function response(
        string $action,
        bool $changed,
        Work $work,
        WorkMedia $media,
        string $mode,
        ?int $timeMs = null,
    ): array {
        $data = [
            'action' => $action,
            'changed' => $changed,
            'work_id' => $work->id,
            'media_id' => $media->id,
            'cover_media_id' => $work->cover_media_id,
            'mode' => $mode,
        ];

        if ($timeMs !== null) {
            $data['time_ms'] = $timeMs;
        }

        return $data;
    }

    /**
     * @param array<string, string|null> $requestContext
     * @param array<string, mixed> $metadata
     */
    private function recordAudit(
        WorkMedia $media,
        User $actor,
        array $requestContext,
        string $action,
        array $metadata,
    ): void {
        $this->auditEventLogger->record([
            'event_type' => 'works.media.video_cover_updated',
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

    /** @param array<string, mixed> $settings */
    private function effectiveMaxFileSizeKb(array $settings): ?int
    {
        $limits = array_filter([
            $this->mediaLimits($settings)['max_file_size_kb'],
            $this->phpIniSizeKb('upload_max_filesize'),
            $this->safePostMaxSizeKb(),
            $this->configuredTransportMaxKb(),
        ], static fn (mixed $limit): bool => is_int($limit) && $limit > 0);

        return $limits === [] ? null : min($limits);
    }

    private function safePostMaxSizeKb(): ?int
    {
        $postMaxKb = $this->phpIniSizeKb('post_max_size');
        if ($postMaxKb === null) {
            return null;
        }

        $marginKb = min(1024, max(64, (int) ceil($postMaxKb * 0.01)));

        return max(1, $postMaxKb - $marginKb);
    }

    private function configuredTransportMaxKb(): ?int
    {
        $value = config('works-media.transport_max_kb');
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value > 0 ? (int) $value : null;
        }

        return null;
    }

    private function phpIniSizeKb(string $key): ?int
    {
        $value = trim((string) ini_get($key));
        if ($value === '' || $value === '-1' || $value === '0') {
            return null;
        }
        if (! preg_match('/^(\d+(?:\.\d+)?)\s*([kmgt]?)$/i', $value, $matches)) {
            return null;
        }

        $multiplier = match (strtolower($matches[2])) {
            't' => 1024 ** 3,
            'g' => 1024 ** 2,
            'm' => 1024,
            'k' => 1,
            default => 1 / 1024,
        };
        $kilobytes = (int) floor((float) $matches[1] * $multiplier);

        return $kilobytes > 0 ? $kilobytes : null;
    }

    /** @param array<string, mixed> $settings */
    private function mediaLimits(array $settings): array
    {
        $limits = $settings['values']['media_limits'] ?? [];

        return [
            'max_file_size_kb' => is_array($limits)
                && is_int($limits['max_file_size_kb'] ?? null)
                    ? $limits['max_file_size_kb']
                    : null,
        ];
    }
}
