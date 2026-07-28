<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designer\DesignerWorkMediaContentRequest;
use App\Models\WorkMedia;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DesignerWorksMediaController extends Controller
{
    public function content(DesignerWorkMediaContentRequest $request, int $work, int $media): StreamedResponse|Response
    {
        $workMedia = $this->ownedMedia($request, $work, $media);

        return $this->inlineFile($workMedia, $this->contentPath($workMedia));
    }

    public function poster(DesignerWorkMediaContentRequest $request, int $work, int $media): StreamedResponse|Response
    {
        $workMedia = $this->ownedMedia($request, $work, $media);
        abort_unless($workMedia->kind === WorkMedia::KIND_VIDEO, 404);

        return $this->inlineFile($workMedia, $this->posterPath($workMedia));
    }

    private function ownedMedia(DesignerWorkMediaContentRequest $request, int $work, int $media): WorkMedia
    {
        return WorkMedia::query()
            ->whereKey($media)
            ->where('work_id', $work)
            ->whereHas('work', fn ($query) => $query->where('designer_id', $request->user()->getKey()))
            ->firstOrFail();
    }

    private function inlineFile(WorkMedia $media, ?string $path): StreamedResponse|Response
    {
        abort_if(! $path, 404);
        $disk = $this->disk($media);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function disk(WorkMedia $media): Filesystem
    {
        $name = $media->getAttribute('disk')
            ?: $media->getAttribute('storage_disk')
            ?: config('filesystems.default');

        return Storage::disk((string) $name);
    }

    private function contentPath(WorkMedia $media): ?string
    {
        return $media->getAttribute('path')
            ?: $media->getAttribute('storage_path')
            ?: $media->getAttribute('file_path');
    }

    private function posterPath(WorkMedia $media): ?string
    {
        return $media->getAttribute('poster_path')
            ?: $media->getAttribute('poster_storage_path');
    }
}
