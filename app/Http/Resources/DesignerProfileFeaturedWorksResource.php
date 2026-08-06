<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DesignerProfile;
use App\Models\Work;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignerProfileFeaturedWorksResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /**
         * @var array{
         *     profile: DesignerProfile,
         *     changed: bool,
         *     limit: int,
         *     selected: Collection<int, Work>,
         *     eligible: Collection<int, Work>
         * } $result
         */
        $result = $this->resource;

        return [
            'changed' => $result['changed'],
            'expected_updated_at' => $result['profile']->updated_at?->toISOString(),
            'limit' => $result['limit'],
            'selected' => DesignerWorkIndexResource::collection(
                $result['selected'],
            )->resolve($request),
            'eligible' => DesignerWorkIndexResource::collection(
                $result['eligible'],
            )->resolve($request),
        ];
    }
}
