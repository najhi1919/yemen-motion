<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DesignerProfile;
use App\Models\DesignerProfileSpecialty;
use App\Models\Work;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PublicDesignerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /**
         * @var array{
         *     profile: DesignerProfile,
         *     featuredWorks: Collection<int, Work>,
         *     works: Collection<int, Work>
         * } $payload
         */
        $payload = $this->resource;
        $profile = $payload['profile'];
        $featuredWorks = $payload['featuredWorks'];
        $works = $payload['works'];
        $username = (string) $profile->user->username;
        $avatarUrl = $profile->avatar_path
            ? $this->versionedRoute('public.designers.avatar', $username, $profile)
            : null;
        $coverUrl = $profile->cover_path
            ? $this->versionedRoute('public.designers.cover', $username, $profile)
            : null;

        return [
            'identity' => [
                'username' => $username,
                'display_name' => $profile->display_name,
                'professional_title' => $profile->professional_title,
                'primary_specialty' => $profile->primary_specialty,
                'bio' => $profile->bio,
                'avatar_url' => $avatarUrl,
                'cover_url' => $coverUrl,
                'cover_focal_point' => [
                    'x' => (int) $profile->cover_focal_x,
                    'y' => (int) $profile->cover_focal_y,
                ],
            ],
            'professional' => $this->professional($profile),
            'published_at' => $profile->published_at?->toISOString(),
            'featured_works' => [
                'items' => PublicDesignerWorkResource::collection(
                    $featuredWorks,
                )->resolve($request),
                'total' => $featuredWorks->count(),
            ],
            'works' => [
                'items' => PublicDesignerWorkResource::collection(
                    $works,
                )->resolve($request),
                'total' => $works->count(),
            ],
            'seo' => [
                'title' => collect([
                    $profile->display_name,
                    $profile->professional_title,
                    'Yemen Motion',
                ])->filter(static fn ($value): bool => trim((string) $value) !== '')
                    ->implode(' — '),
                'description' => Str::of(strip_tags((string) $profile->bio))
                    ->squish()
                    ->limit(160, '')
                    ->toString(),
                'canonical_path' => '/designers/'.$username,
                'image_url' => $coverUrl ?? $avatarUrl,
                'type' => 'profile',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function professional(DesignerProfile $profile): array
    {
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

        return $professional;
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
    private function leveledItems(Collection $items): array
    {
        return $items->map(static fn ($item): array => [
            'name' => $item->name,
            'level' => $item->level,
        ])->all();
    }

    private function versionedRoute(
        string $name,
        string $username,
        DesignerProfile $profile,
    ): string {
        return route($name, ['username' => $username])
            .'?v='.($profile->updated_at?->timestamp ?? 0);
    }
}
