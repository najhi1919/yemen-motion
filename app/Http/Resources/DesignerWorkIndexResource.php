<?php

namespace App\Http\Resources;

use App\Models\WorkMedia;
use App\Models\Work;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignerWorkIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cover = $this->whenLoaded('coverMedia');
        $cover = $cover instanceof WorkMedia ? $cover : null;
        $category = $this->relationLoaded('category') ? $this->category : null;
        $tags = $this->relationLoaded('tags') ? $this->tags : collect();

        return [
            'id' => $this->id,
            'public_code' => $this->public_code,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'status' => $this->status,
            'media_type' => $this->media_type,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'category' => $category ? [
                'id' => (int) $category->id,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'slug' => $category->slug,
            ] : null,
            'tags' => $tags->map(static fn ($tag): array => [
                'id' => (int) $tag->id,
                'name_ar' => $tag->name_ar,
                'name_en' => $tag->name_en,
                'slug' => $tag->slug,
            ])->values()->all(),
            'cover_presentation' => [
                'display_mode' => in_array(
                    $this->cover_display_mode,
                    Work::COVER_DISPLAY_MODES,
                    true,
                ) ? $this->cover_display_mode : Work::COVER_DISPLAY_MODE_FILL,
                'focal_point' => [
                    'x' => max(0, min(100, (int) ($this->cover_focal_x ?? 50))),
                    'y' => max(0, min(100, (int) ($this->cover_focal_y ?? 50))),
                ],
            ],
            'cover_media' => $cover ? [
                'id' => $cover->id,
                'kind' => $cover->kind,
                'processing_status' => $cover->processing_status,
                'content_url' => "/designer/works/{$this->id}/media/{$cover->id}/content",
                'poster_url' => $cover->kind === WorkMedia::KIND_VIDEO
                    && ($cover->getAttribute('poster_path') || $cover->getAttribute('poster_storage_path'))
                        ? "/designer/works/{$this->id}/media/{$cover->id}/poster"
                        : null,
            ] : null,
        ];
    }
}
