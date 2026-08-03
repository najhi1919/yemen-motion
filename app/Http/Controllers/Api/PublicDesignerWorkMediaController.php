<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Designer\PublicDesignerProfileService;
use App\Services\Works\WorksMediaService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicDesignerWorkMediaController extends Controller
{
    public function content(
        string $username,
        string $workCode,
        int $media,
        PublicDesignerProfileService $profiles,
        WorksMediaService $mediaService,
    ): StreamedResponse {
        $eligible = $profiles->publicCoverMedia($username, $workCode, $media);
        $response = $mediaService->content($eligible['work']->getKey(), $eligible['media']->getKey());

        return $this->publicResponse($response);
    }

    public function poster(
        string $username,
        string $workCode,
        int $media,
        PublicDesignerProfileService $profiles,
        WorksMediaService $mediaService,
    ): StreamedResponse {
        $eligible = $profiles->publicCoverMedia($username, $workCode, $media, true);
        $response = $mediaService->poster($eligible['work']->getKey(), $eligible['media']->getKey());

        return $this->publicResponse($response);
    }

    private function publicResponse(StreamedResponse $response): StreamedResponse
    {
        $response->headers->set('Cache-Control', 'public, max-age=3600');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Disposition', 'inline');

        return $response;
    }
}
