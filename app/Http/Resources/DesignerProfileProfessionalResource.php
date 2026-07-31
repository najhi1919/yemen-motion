<?php

namespace App\Http\Resources;

use App\Models\DesignerProfileSpecialty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignerProfileProfessionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'years_of_experience' => $this->years_of_experience,
            'professional_note' => $this->professional_note,
            'availability' => $this->availability,
            'visibility' => [
                'availability' => (bool) $this->show_availability_publicly,
                'specialties' => (bool) $this->show_specialties_publicly,
                'skills' => (bool) $this->show_skills_publicly,
                'tools' => (bool) $this->show_tools_publicly,
                'languages' => (bool) $this->show_languages_publicly,
                'experience' => (bool) $this->show_experience_publicly,
            ],
            'specialties' => collect(DesignerProfileSpecialty::KINDS)->mapWithKeys(fn (string $kind): array => [
                $kind => $this->specialties->where('kind', $kind)->values()->map(
                    static fn ($item): array => ['name' => $item->name],
                )->all(),
            ])->all(),
            'skills' => $this->skills->map(static fn ($item): array => [
                'name' => $item->name,
                'level' => $item->level,
            ])->all(),
            'tools' => $this->tools->map(static fn ($item): array => [
                'name' => $item->name,
                'level' => $item->level,
            ])->all(),
            'languages' => $this->languages->map(static fn ($item): array => [
                'name' => $item->name,
                'level' => $item->level,
            ])->all(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
