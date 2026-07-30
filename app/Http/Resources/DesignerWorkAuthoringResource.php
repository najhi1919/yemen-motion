<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignerWorkAuthoringResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_code' => $this->public_code,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'status' => $this->status,
            'visibility_status' => $this->visibility_status,
            'media_type' => $this->media_type,
            'price_amount' => $this->price_amount,
            'delivery_days' => $this->delivery_days,
            'category_id' => $this->category_id,
            'cover_media_id' => $this->cover_media_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
