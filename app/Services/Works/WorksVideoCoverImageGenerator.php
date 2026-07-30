<?php

namespace App\Services\Works;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class WorksVideoCoverImageGenerator
{
    public function generateFrame(
        string $videoPath,
        string $outputPath,
        int $timeMs,
    ): void {
        $seconds = number_format($timeMs / 1000, 3, '.', '');

        $this->run([
            'ffmpeg',
            '-hide_banner',
            '-loglevel',
            'error',
            '-y',
            '-ss',
            $seconds,
            '-i',
            $videoPath,
            '-frames:v',
            '1',
            '-vf',
            "scale='min(1280,iw)':-2",
            '-q:v',
            '3',
            '-f',
            'image2',
            $outputPath,
        ], $outputPath, 'تعذر استخراج اللقطة المحددة من الفيديو.');
    }

    public function normalizeImage(string $imagePath, string $outputPath): void
    {
        $details = @getimagesize($imagePath);
        $mimeType = is_array($details) ? ($details['mime'] ?? null) : null;

        if (! is_string($mimeType)
            || ! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('نوع صورة غلاف الفيديو غير مدعوم.');
        }

        $this->run([
            'ffmpeg',
            '-hide_banner',
            '-loglevel',
            'error',
            '-y',
            '-i',
            $imagePath,
            '-frames:v',
            '1',
            '-vf',
            "scale='min(1280,iw)':-2",
            '-q:v',
            '3',
            '-f',
            'image2',
            $outputPath,
        ], $outputPath, 'تعذر تجهيز صورة غلاف الفيديو.');
    }

    /**
     * @param list<string> $arguments
     */
    private function run(array $arguments, string $outputPath, string $message): void
    {
        $process = new Process($arguments);
        $process->setTimeout(120);

        try {
            $process->run();
        } catch (Throwable) {
            @unlink($outputPath);

            throw new RuntimeException($message);
        }

        clearstatcache(true, $outputPath);

        if (! $process->isSuccessful()
            || ! is_file($outputPath)
            || filesize($outputPath) < 1) {
            @unlink($outputPath);

            throw new RuntimeException($message);
        }
    }
}
