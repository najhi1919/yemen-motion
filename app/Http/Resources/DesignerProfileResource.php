<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'professional_title' => $this->professional_title,
            'primary_specialty' => $this->primary_specialty,
            'bio' => $this->bio,
            'identity_media' => [
                'avatar_url' => $this->avatar_path
                    ? url('/api/designer/profile/avatar/content').'?v='.$this->updated_at?->timestamp
                    : null,
                'cover_url' => $this->cover_path
                    ? url('/api/designer/profile/cover/content').'?v='.$this->updated_at?->timestamp
                    : null,
                'cover_focal_point' => [
                    'x' => (int) $this->cover_focal_x,
                    'y' => (int) $this->cover_focal_y,
                ],
            ],
            'availability' => $this->availability,
            'publication_status' => $this->publication_status,
            'published_at' => $this->published_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
