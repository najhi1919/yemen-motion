<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Designer\PublicDesignerProfileService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicDesignerProfileMediaController extends Controller
{
    private const DISK = 'works_private';

    public function avatar(string $username, PublicDesignerProfileService $service): StreamedResponse
    {
        return $this->content($service->publicProfile($username)->avatar_path);
    }

    public function cover(string $username, PublicDesignerProfileService $service): StreamedResponse
    {
        return $this->content($service->publicProfile($username)->cover_path);
    }

    public function organizationLogo(string $username, PublicDesignerProfileService $service): StreamedResponse
    {
        $profile = $service->publicProfile($username);
        $org = $profile->organization;

        abort_if($org === null || ! $org->show_publicly || $org->logo_path === null, 404, 'الوسيط العام غير متاح.');

        return $this->content($org->logo_path);
    }

    private function content(?string $path): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);
        abort_if($path === null || ! $disk->exists($path), 404, 'الوسيط العام غير متاح.');
        $mime = $disk->mimeType($path) ?: 'application/octet-stream';

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
