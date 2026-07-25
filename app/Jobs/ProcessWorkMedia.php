<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WorkMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessWorkMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public function __construct(public readonly int $mediaId)
    {
        $this->onQueue('works-media');
    }

    public function handle(): void
    {
        $lock = Cache::lock('works-media-processing:'.$this->mediaId, 300);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->process();
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        $media = WorkMedia::withTrashed()->find($this->mediaId);

        if ($media === null || $media->trashed() || $media->processing_status === WorkMedia::PROCESSING_READY) {
            return;
        }

        $media->forceFill([
            'processing_status' => WorkMedia::PROCESSING_FAILED,
            'processing_stage' => WorkMedia::STAGE_FAILED,
            'processing_completed_at' => now(),
            'processing_error' => $this->technicalError($exception, WorkMedia::STAGE_FAILED),
        ])->save();
    }

    private function process(): void
    {
        $media = WorkMedia::withTrashed()->find($this->mediaId);

        if ($media === null || $media->trashed()) {
            return;
        }

        if ($media->processing_status === WorkMedia::PROCESSING_READY) {
            return;
        }

        $disk = Storage::disk($media->disk);
        $temporaryInput = null;
        $temporaryPoster = null;
        $posterPath = 'works/'.$media->work_id.'/derived/'.$media->id.'-poster.jpg';
        $stage = WorkMedia::STAGE_VALIDATING;

        try {
            $this->updateProgress($media, $stage, 5, [
                'processing_attempts' => ((int) $media->processing_attempts) + 1,
                'processing_started_at' => now(),
                'processing_completed_at' => null,
                'processing_error' => null,
            ]);

            if ($media->kind !== WorkMedia::KIND_VIDEO || ! $disk->exists($media->path)) {
                throw new RuntimeException('Video source is unavailable or invalid.');
            }

            [$temporaryInput, $temporaryPoster] = $this->temporaryPaths($media);
            $source = $disk->readStream($media->path);
            $destination = fopen($temporaryInput, 'wb');

            if (! is_resource($source) || ! is_resource($destination)) {
                if (is_resource($source)) {
                    fclose($source);
                }
                if (is_resource($destination)) {
                    fclose($destination);
                }

                throw new RuntimeException('Video source could not be prepared.');
            }

            stream_copy_to_stream($source, $destination);
            fclose($source);
            fclose($destination);

            $stage = WorkMedia::STAGE_PROBING;
            $this->updateProgress($media, $stage, 20);
            $metadata = $this->probe($temporaryInput);

            $stage = WorkMedia::STAGE_EXTRACTING_METADATA;
            $this->updateProgress($media, $stage, 45, [
                'width' => $metadata['width'],
                'height' => $metadata['height'],
                'duration_ms' => $metadata['duration_ms'],
            ]);

            $stage = WorkMedia::STAGE_GENERATING_POSTER;
            $this->updateProgress($media, $stage, 65);
            $this->generatePoster($temporaryInput, $temporaryPoster);

            $posterStream = fopen($temporaryPoster, 'rb');
            if (! is_resource($posterStream) || ! $disk->put($posterPath, $posterStream)) {
                if (is_resource($posterStream)) {
                    fclose($posterStream);
                }

                throw new RuntimeException('Video poster could not be stored.');
            }
            fclose($posterStream);

            $stage = WorkMedia::STAGE_FINALIZING;
            $this->updateProgress($media, $stage, 90, ['poster_path' => $posterPath]);

            $media->forceFill([
                'processing_status' => WorkMedia::PROCESSING_READY,
                'processing_stage' => WorkMedia::STAGE_READY,
                'processing_progress' => 100,
                'processing_completed_at' => now(),
                'processing_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $disk->delete($posterPath);
            $media->forceFill([
                'poster_path' => null,
                'processing_status' => WorkMedia::PROCESSING_FAILED,
                'processing_stage' => WorkMedia::STAGE_FAILED,
                'processing_completed_at' => now(),
                'processing_error' => $this->technicalError($exception, $stage),
            ])->save();

            Log::error('Work media processing failed.', [
                'media_id' => $media->id,
                'stage' => $stage,
                'exception' => $exception::class,
                'message' => Str::limit($exception->getMessage(), 500),
            ]);

            throw $exception;
        } finally {
            foreach ([$temporaryInput, $temporaryPoster] as $temporaryPath) {
                if (is_string($temporaryPath) && is_file($temporaryPath)) {
                    @unlink($temporaryPath);
                }
            }
        }
    }

    /** @return array{width: int, height: int, duration_ms: int} */
    private function probe(string $input): array
    {
        $process = new Process([
            'ffprobe',
            '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height,duration:format=duration',
            '-of', 'json',
            $input,
        ]);
        $process->setTimeout(60);
        $process->mustRun();
        $payload = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $stream = $payload['streams'][0] ?? null;
        $duration = $stream['duration'] ?? $payload['format']['duration'] ?? null;
        $width = filter_var($stream['width'] ?? null, FILTER_VALIDATE_INT);
        $height = filter_var($stream['height'] ?? null, FILTER_VALIDATE_INT);

        if (! is_int($width) || $width < 1 || ! is_int($height) || $height < 1 || ! is_numeric($duration) || (float) $duration <= 0) {
            throw new RuntimeException('Video metadata is incomplete.');
        }

        return [
            'width' => $width,
            'height' => $height,
            'duration_ms' => max(1, (int) round((float) $duration * 1000)),
        ];
    }

    private function generatePoster(string $input, string $output): void
    {
        $process = new Process([
            'ffmpeg',
            '-hide_banner',
            '-loglevel', 'error',
            '-y',
            '-i', $input,
            '-frames:v', '1',
            '-vf', 'scale=min(1280\\,iw):-2',
            '-q:v', '3',
            $output,
        ]);
        $process->setTimeout(120);
        $process->mustRun();

        if (! is_file($output) || filesize($output) < 1) {
            throw new RuntimeException('Video poster was not generated.');
        }
    }

    /** @return array{string, string} */
    private function temporaryPaths(WorkMedia $media): array
    {
        $directory = storage_path('app/tmp/works-media');
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('Media processing workspace is unavailable.');
        }

        $token = (string) Str::uuid();

        return [
            $directory.'/'.$media->id.'-'.$token.'.'.$media->extension,
            $directory.'/'.$media->id.'-'.$token.'-poster.jpg',
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function updateProgress(WorkMedia $media, string $stage, int $progress, array $attributes = []): void
    {
        $media->forceFill([
            'processing_status' => WorkMedia::PROCESSING_PENDING,
            'processing_stage' => $stage,
            'processing_progress' => max(0, min(100, $progress)),
            ...$attributes,
        ])->save();
    }

    private function technicalError(Throwable $exception, string $stage): string
    {
        return Str::limit($stage.'|'.$exception::class.': '.$exception->getMessage(), 2000, '');
    }
}
