<?php

namespace App\Http\Resources;

use App\Models\DesignerProfile;
use App\Models\DesignerProfileSpecialty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignerProfilePreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DesignerProfile $profile */
        $profile = $this->resource;
        $professional = [
            'sections' => [
                'availability' => $this->section(
                    (bool) $profile->show_availability_publicly,
                    ['value' => $profile->availability],
                ),
                'specialties' => $this->section(
                    (bool) $profile->show_specialties_publicly,
                    [
                        'service' => $this->specialties($profile, DesignerProfileSpecialty::KIND_SERVICE),
                        'style' => $this->specialties($profile, DesignerProfileSpecialty::KIND_STYLE),
                    ],
                ),
                'skills' => $this->section(
                    (bool) $profile->show_skills_publicly,
                    ['items' => $this->leveledItems($profile->skills)],
                ),
                'tools' => $this->section(
                    (bool) $profile->show_tools_publicly,
                    ['items' => $this->leveledItems($profile->tools)],
                ),
                'languages' => $this->section(
                    (bool) $profile->show_languages_publicly,
                    ['items' => $this->leveledItems($profile->languages)],
                ),
                'experience' => $this->section(
                    (bool) $profile->show_experience_publicly,
                    ['years_of_experience' => $profile->years_of_experience],
                ),
            ],
        ];

        if (trim((string) $profile->professional_note) !== '') {
            $professional['additional_information'] = [
                'professional_note' => $profile->professional_note,
            ];
        }

        return [
            'identity' => [
                'username' => $profile->user?->username,
                'display_name' => $profile->display_name,
                'professional_title' => $profile->professional_title,
                'primary_specialty' => $profile->primary_specialty,
                'bio' => $profile->bio,
                'avatar_url' => $profile->avatar_path
                    ? url('/api/designer/profile/avatar/content').'?v='.$profile->updated_at?->timestamp
                    : null,
                'cover_url' => $profile->cover_path
                    ? url('/api/designer/profile/cover/content').'?v='.$profile->updated_at?->timestamp
                    : null,
                'cover_focal_point' => [
                    'x' => (int) $profile->cover_focal_x,
                    'y' => (int) $profile->cover_focal_y,
                ],
            ],
            'publication' => [
                'status' => $profile->publication_status,
                'is_publicly_visible' => $profile->publication_status === DesignerProfile::PUBLICATION_PUBLISHED,
                'preview_mode' => true,
            ],
            'professional' => $professional,
        ];
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function section(bool $visible, array $content): array
    {
        return $visible ? ['visible' => true, ...$content] : ['visible' => false];
    }

    /** @return list<array{name: string}> */
    private function specialties(DesignerProfile $profile, string $kind): array
    {
        return $profile->specialties
            ->where('kind', $kind)
            ->values()
            ->map(static fn ($item): array => ['name' => $item->name])
            ->all();
    }

    /** @return list<array{name: string, level: string}> */
    private function leveledItems($items): array
    {
        return $items->map(static fn ($item): array => [
            'name' => $item->name,
            'level' => $item->level,
        ])->all();
    }
}
