<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Work;
use App\Models\WorkMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicDesignerWorkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cover = $this->relationLoaded('coverMedia') ? $this->coverMedia : null;
        $cover = $cover instanceof WorkMedia
            && (int) $cover->work_id === (int) $this->id
            && $cover->processing_status === WorkMedia::PROCESSING_READY
                ? $cover
                : null;
        $username = (string) $request->route('username');

        return [
            'public_code' => $this->public_code,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'media_type' => $this->media_type,
            'published_at' => $this->published_at?->toISOString(),
            'category' => $this->relationLoaded('category') && $this->category ? [
                'name_ar' => $this->category->name_ar,
                'name_en' => $this->category->name_en,
                'slug' => $this->category->slug,
            ] : null,
            'tags' => $this->relationLoaded('tags')
                ? $this->tags->map(static fn ($tag): array => [
                    'name_ar' => $tag->name_ar,
                    'name_en' => $tag->name_en,
                    'slug' => $tag->slug,
                ])->values()->all()
                : [],
            'cover_presentation' => [
                'display_mode' => in_array($this->cover_display_mode, Work::COVER_DISPLAY_MODES, true)
                    ? $this->cover_display_mode
                    : Work::COVER_DISPLAY_MODE_FILL,
                'focal_point' => [
                    'x' => max(0, min(100, (int) ($this->cover_focal_x ?? 50))),
                    'y' => max(0, min(100, (int) ($this->cover_focal_y ?? 50))),
                ],
            ],
            'cover_media' => $cover ? [
                'kind' => $cover->kind,
                'width' => $cover->width,
                'height' => $cover->height,
                'duration_ms' => $cover->duration_ms,
                'content_url' => $this->versionedRoute(
                    'public.designers.works.media.content',
                    $username,
                    $cover,
                ),
                'poster_url' => $cover->kind === WorkMedia::KIND_VIDEO
                    && $cover->getAttribute('poster_path') !== null
                        ? $this->versionedRoute(
                            'public.designers.works.media.poster',
                            $username,
                            $cover,
                        )
                        : null,
            ] : null,
        ];
    }

    private function versionedRoute(string $name, string $username, WorkMedia $media): string
    {
        $url = route($name, [
            'username' => $username,
            'workCode' => $this->public_code,
            'media' => $media->getKey(),
        ]);

        return $url.'?v='.($media->updated_at?->timestamp ?? 0);
    }
}
