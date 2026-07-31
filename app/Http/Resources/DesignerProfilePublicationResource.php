<?php

namespace App\Http\Resources;

use App\Models\DesignerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignerProfilePublicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->resource;
        /** @var DesignerProfile $profile */
        $profile = $result['profile'];
        $readiness = $result['readiness'];

        $data = [
            'expected_updated_at' => $profile->updated_at?->toISOString(),
            'publication' => [
                'status' => $profile->publication_status,
                'published_at' => $profile->published_at?->toISOString(),
                'hidden_at' => $profile->hidden_at?->toISOString(),
                'updated_at' => $profile->updated_at?->toISOString(),
            ],
            'readiness' => $readiness,
            'actions' => [
                'can_preview' => true,
                'can_publish' => $readiness['ready']
                    && $profile->publication_status !== DesignerProfile::PUBLICATION_PUBLISHED,
                'can_hide' => $profile->publication_status === DesignerProfile::PUBLICATION_PUBLISHED,
            ],
        ];

        if (array_key_exists('changed', $result)) {
            $data = ['changed' => (bool) $result['changed'], ...$data];
        }

        return $data;
    }
}
