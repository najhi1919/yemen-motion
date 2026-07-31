<?php

declare(strict_types=1);

namespace App\Services\Designer;

use App\Http\Requests\Designer\DesignerProfileProfessionalUpdateRequest;
use App\Models\DesignerProfile;
use App\Models\DesignerProfileLanguage;
use App\Models\DesignerProfileSkill;
use App\Models\DesignerProfileSpecialty;
use App\Models\DesignerProfileTool;
use App\Models\User;
use App\Services\Audit\AuditEventLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class DesignerProfileProfessionalService
{
    private const RELATIONS = ['specialties', 'skills', 'tools', 'languages'];

    public function __construct(private readonly AuditEventLogger $auditEventLogger) {}

    /** @return array<string, mixed> */
    public function show(User $actor): array
    {
        $profile = DesignerProfile::query()
            ->where('user_id', $actor->getKey())
            ->with(self::RELATIONS)
            ->first();

        if (! $profile) {
            $this->missingProfile();
        }

        return $this->result($profile, false);
    }

    /**
     * @param array<string, mixed> $validated
     * @param array<string, string|null> $requestContext
     * @return array<string, mixed>
     */
    public function update(User $actor, array $validated, array $requestContext): array
    {
        return DB::transaction(function () use ($actor, $validated, $requestContext): array {
            $profile = DesignerProfile::query()
                ->where('user_id', $actor->getKey())
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                $this->missingProfile();
            }

            $profile->load(self::RELATIONS);
            $expected = CarbonImmutable::parse((string) $validated['expected_updated_at']);
            if ($profile->updated_at === null || ! $expected->equalTo($profile->updated_at)) {
                throw new HttpResponseException(response()->json([
                    'message' => 'تغيرت بيانات الملف في الخادم. حمّل النسخة الأحدث ثم حاول مجددًا.',
                    'data' => [
                        'code' => 'designer_profile_version_conflict',
                        'current_updated_at' => $profile->updated_at?->toJSON(),
                    ],
                ], 409));
            }

            $current = $this->currentCanonical($profile);
            $next = $this->payloadCanonical($validated);
            $changedSections = $this->changedSections($current, $next);

            if ($changedSections === []) {
                return $this->result($profile, false);
            }

            $previousCounts = $this->counts($current);
            $profile->timestamps = false;
            $profile->forceFill([
                'availability' => $next['availability'],
                'years_of_experience' => $next['years_of_experience'],
                'professional_note' => $next['professional_note'],
                'show_availability_publicly' => $next['visibility']['availability'],
                'show_specialties_publicly' => $next['visibility']['specialties'],
                'show_skills_publicly' => $next['visibility']['skills'],
                'show_tools_publicly' => $next['visibility']['tools'],
                'show_languages_publicly' => $next['visibility']['languages'],
                'show_experience_publicly' => $next['visibility']['experience'],
            ])->save();
            $profile->timestamps = true;

            foreach (self::RELATIONS as $relation) {
                $profile->{$relation}()->delete();
            }

            foreach (DesignerProfileSpecialty::KINDS as $kind) {
                foreach ($next['specialties'][$kind] as $sortOrder => $item) {
                    $profile->specialties()->create([
                        'kind' => $kind,
                        'name' => $item['name'],
                        'normalized_name' => $item['normalized_name'],
                        'sort_order' => $sortOrder,
                    ]);
                }
            }

            foreach (['skills', 'tools', 'languages'] as $relation) {
                foreach ($next[$relation] as $sortOrder => $item) {
                    $profile->{$relation}()->create([
                        'name' => $item['name'],
                        'normalized_name' => $item['normalized_name'],
                        'level' => $item['level'],
                        'sort_order' => $sortOrder,
                    ]);
                }
            }

            $profile->touch();
            $profile->refresh()->load(self::RELATIONS);
            $currentCounts = $this->counts($next);

            $this->auditEventLogger->record([
                'event_type' => 'designer.profile.professional.updated',
                'category' => 'designer_profiles',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $actor->getKey(),
                'actor_role' => 'designer',
                'target_type' => 'designer_profile',
                'target_id' => $profile->getKey(),
                'action' => 'update_professional_data',
                'outcome' => 'success',
                'ip_address' => $requestContext['ip_address'] ?? null,
                'user_agent' => $requestContext['user_agent'] ?? null,
                'metadata' => [
                    'profile_id' => $profile->getKey(),
                    'previous_availability' => $current['availability'],
                    'current_availability' => $next['availability'],
                    'previous_years_of_experience' => $current['years_of_experience'],
                    'current_years_of_experience' => $next['years_of_experience'],
                    'previous_specialties_count' => $previousCounts['specialties'],
                    'current_specialties_count' => $currentCounts['specialties'],
                    'previous_skills_count' => $previousCounts['skills'],
                    'current_skills_count' => $currentCounts['skills'],
                    'previous_tools_count' => $previousCounts['tools'],
                    'current_tools_count' => $currentCounts['tools'],
                    'previous_languages_count' => $previousCounts['languages'],
                    'current_languages_count' => $currentCounts['languages'],
                    'visibility_changed' => $current['visibility'] !== $next['visibility'],
                    'changed_sections' => $changedSections,
                ],
            ]);

            return $this->result($profile, true);
        });
    }

    /** @return array<string, mixed> */
    private function currentCanonical(DesignerProfile $profile): array
    {
        $specialties = [];
        foreach (DesignerProfileSpecialty::KINDS as $kind) {
            $specialties[$kind] = $profile->specialties->where('kind', $kind)->values()->map(
                static fn ($item): array => [
                    'name' => $item->name,
                    'normalized_name' => $item->normalized_name,
                ],
            )->all();
        }

        return [
            'availability' => $profile->availability,
            'years_of_experience' => $profile->years_of_experience,
            'professional_note' => $profile->professional_note,
            'visibility' => [
                'availability' => (bool) $profile->show_availability_publicly,
                'specialties' => (bool) $profile->show_specialties_publicly,
                'skills' => (bool) $profile->show_skills_publicly,
                'tools' => (bool) $profile->show_tools_publicly,
                'languages' => (bool) $profile->show_languages_publicly,
                'experience' => (bool) $profile->show_experience_publicly,
            ],
            'specialties' => $specialties,
            'skills' => $this->leveledItems($profile->skills),
            'tools' => $this->leveledItems($profile->tools),
            'languages' => $this->leveledItems($profile->languages),
        ];
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    private function payloadCanonical(array $validated): array
    {
        $specialties = [];
        foreach (DesignerProfileSpecialty::KINDS as $kind) {
            $specialties[$kind] = array_map(fn (string $name): array => [
                'name' => DesignerProfileProfessionalUpdateRequest::cleanName($name),
                'normalized_name' => DesignerProfileProfessionalUpdateRequest::normalizedName($name),
            ], $validated['specialties'][$kind]);
        }

        $canonical = [
            'availability' => $validated['availability'],
            'years_of_experience' => $validated['years_of_experience'] ?? null,
            'professional_note' => $validated['professional_note'] ?? null,
            'visibility' => array_map(static fn (mixed $value): bool => (bool) $value, $validated['visibility']),
            'specialties' => $specialties,
        ];

        foreach (['skills', 'tools', 'languages'] as $section) {
            $canonical[$section] = array_map(static fn (array $item): array => [
                'name' => DesignerProfileProfessionalUpdateRequest::cleanName($item['name']),
                'normalized_name' => DesignerProfileProfessionalUpdateRequest::normalizedName($item['name']),
                'level' => $item['level'],
            ], $validated[$section]);
        }

        return $canonical;
    }

    /** @return list<array{name: string, normalized_name: string, level: string}> */
    private function leveledItems($items): array
    {
        return $items->map(static fn ($item): array => [
            'name' => $item->name,
            'normalized_name' => $item->normalized_name,
            'level' => $item->level,
        ])->all();
    }

    /** @return list<string> */
    private function changedSections(array $current, array $next): array
    {
        $mapping = [
            'availability' => ['availability'],
            'experience' => ['years_of_experience'],
            'professional_note' => ['professional_note'],
            'visibility' => ['visibility'],
            'specialties' => ['specialties'],
            'skills' => ['skills'],
            'tools' => ['tools'],
            'languages' => ['languages'],
        ];

        return array_keys(array_filter($mapping, static function (array $keys) use ($current, $next): bool {
            foreach ($keys as $key) {
                if ($current[$key] !== $next[$key]) {
                    return true;
                }
            }
            return false;
        }));
    }

    /** @return array{specialties: int, skills: int, tools: int, languages: int} */
    private function counts(array $canonical): array
    {
        return [
            'specialties' => array_sum(array_map('count', $canonical['specialties'])),
            'skills' => count($canonical['skills']),
            'tools' => count($canonical['tools']),
            'languages' => count($canonical['languages']),
        ];
    }

    /** @return array<string, mixed> */
    private function result(DesignerProfile $profile, bool $changed): array
    {
        return [
            'changed' => $changed,
            'profile' => $profile,
            'completion' => $this->completion($profile),
            'options' => [
                'availability' => DesignerProfile::AVAILABILITIES,
                'specialty_kinds' => DesignerProfileSpecialty::KINDS,
                'skill_levels' => DesignerProfileSkill::LEVELS,
                'tool_levels' => DesignerProfileTool::LEVELS,
                'language_levels' => DesignerProfileLanguage::LEVELS,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function completion(DesignerProfile $profile): array
    {
        $counts = [
            'experience' => $profile->years_of_experience === null ? 0 : 1,
            'specialties' => $profile->specialties->count(),
            'skills' => $profile->skills->count(),
            'tools' => $profile->tools->count(),
            'languages' => $profile->languages->count(),
        ];
        $sections = [];
        foreach ($counts as $key => $count) {
            $sections[$key] = ['complete' => $count > 0, 'count' => $count];
        }
        $missing = array_keys(array_filter($sections, static fn (array $section): bool => ! $section['complete']));
        $completed = 5 - count($missing);

        return [
            'completed' => $completed,
            'total' => 5,
            'percentage' => (int) round(($completed / 5) * 100),
            'missing' => $missing,
            'sections' => $sections,
        ];
    }

    private function missingProfile(): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'أنشئ ملف المصمم الأساسي أولًا.',
            'data' => ['code' => 'designer_profile_required'],
        ], 404));
    }
}
