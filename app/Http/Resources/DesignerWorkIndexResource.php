<?php

namespace App\Http\Resources;

use App\Models\WorkMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignerWorkIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cover = $this->whenLoaded('coverMedia');
        $cover = $cover instanceof WorkMedia ? $cover : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'status' => $this->status,
            'media_type' => $this->media_type,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
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
