<?php

namespace Tests\Feature\Designer;

use App\Http\Controllers\Api\DesignerProfileProfessionalController;
use App\Models\AuditEvent;
use App\Models\DesignerProfile;
use App\Models\DesignerProfileLanguage;
use App\Models\DesignerProfileSkill;
use App\Models\DesignerProfileSpecialty;
use App\Models\DesignerProfileTool;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesignerProfileProfessionalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthRolesSeeder::class);
    }

    public function test_routes_have_expected_names_controller_and_methods(): void
    {
        $show = Route::getRoutes()->getByName('designer.profile.professional.show');
        $update = Route::getRoutes()->getByName('designer.profile.professional.update');
        $this->assertSame(DesignerProfileProfessionalController::class.'@show', $show?->getActionName());
        $this->assertSame(['GET', 'HEAD'], $show?->methods());
        $this->assertSame(DesignerProfileProfessionalController::class.'@update', $update?->getActionName());
        $this->assertSame(['PUT'], $update?->methods());
    }

    public function test_only_get_and_put_are_supported(): void
    {
        $designer = $this->designerWithProfile();
        Sanctum::actingAs($designer);
        foreach (['postJson', 'patchJson', 'deleteJson'] as $method) {
            $this->{$method}($this->endpoint())->assertMethodNotAllowed();
        }
    }

    public function test_guest_non_designers_disabled_designer_and_multi_role_contracts(): void
    {
        $this->getJson($this->endpoint())->assertUnauthorized();
        $this->putJson($this->endpoint(), [])->assertUnauthorized();

        foreach (['client', 'staff', 'admin'] as $role) {
            Sanctum::actingAs($this->userWithRole($role));
            $this->getJson($this->endpoint())->assertForbidden();
            $this->putJson($this->endpoint(), [])->assertForbidden();
        }

        $disabled = $this->designerWithProfile(['disabled_at' => now()]);
        Sanctum::actingAs($disabled);
        $this->getJson($this->endpoint())->assertForbidden();

        $multiRole = $this->designerWithProfile();
        $multiRole->assignRole('admin');
        Sanctum::actingAs($multiRole);
        $this->getJson($this->endpoint())->assertOk();
    }

    public function test_missing_basic_profile_returns_safe_404_for_get_and_put(): void
    {
        $designer = $this->userWithRole('designer');
        Sanctum::actingAs($designer);
        $this->getJson($this->endpoint())->assertNotFound()->assertJsonPath('message', 'أنشئ ملف المصمم الأساسي أولًا.');
        $this->putJson($this->endpoint(), $this->payloadFor(null))->assertNotFound()->assertJsonPath('message', 'أنشئ ملف المصمم الأساسي أولًا.');
    }

    public function test_get_returns_safe_defaults_completion_and_options(): void
    {
        $designer = $this->designerWithProfile();
        Sanctum::actingAs($designer);
        $this->getJson($this->endpoint())
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.professional.availability', 'unavailable')
            ->assertJsonPath('data.professional.years_of_experience', null)
            ->assertJsonPath('data.professional.visibility.availability', true)
            ->assertJsonPath('data.professional.specialties.service', [])
            ->assertJsonPath('data.completion.completed', 0)
            ->assertJsonPath('data.completion.total', 5)
            ->assertJsonPath('data.completion.percentage', 0)
            ->assertJsonPath('data.completion.missing', ['experience', 'specialties', 'skills', 'tools', 'languages'])
            ->assertJsonPath('data.options.availability', DesignerProfile::AVAILABILITIES)
            ->assertJsonPath('data.options.specialty_kinds', DesignerProfileSpecialty::KINDS)
            ->assertJsonPath('data.options.skill_levels', DesignerProfileSkill::LEVELS)
            ->assertJsonPath('data.options.tool_levels', DesignerProfileTool::LEVELS)
            ->assertJsonPath('data.options.language_levels', DesignerProfileLanguage::LEVELS);
    }

    public function test_get_and_put_reject_query_parameters_and_put_requires_version(): void
    {
        $designer = $this->designerWithProfile();
        Sanctum::actingAs($designer);
        $this->getJson($this->endpoint().'?public=1')->assertUnprocessable()->assertJsonValidationErrors('unsupported_query');
        $this->putJson($this->endpoint().'?force=1', $this->payloadFor($designer->designerProfile))->assertUnprocessable()->assertJsonValidationErrors('unsupported_request_input');
        $payload = $this->payloadFor($designer->designerProfile);
        unset($payload['expected_updated_at']);
        $this->putJson($this->endpoint(), $payload)->assertUnprocessable()->assertJsonValidationErrors('expected_updated_at');
    }

    public function test_extra_and_sensitive_body_fields_are_rejected(): void
    {
        $designer = $this->designerWithProfile();
        Sanctum::actingAs($designer);
        foreach (['id', 'user_id', 'designer_profile_id', 'publication_status', 'published_at', 'username', 'normalized_name', 'force', 'public', 'private'] as $field) {
            $this->putJson($this->endpoint(), [...$this->payloadFor($designer->designerProfile), $field => 'forbidden'])
                ->assertUnprocessable()->assertJsonValidationErrors('unsupported_request_input');
        }
        $payload = $this->payloadFor($designer->designerProfile);
        $payload['skills'][0]['id'] = 1;
        $this->putJson($this->endpoint(), $payload)->assertUnprocessable()->assertJsonValidationErrors('skills.0');
    }

    public function test_full_update_saves_all_fields_kinds_levels_visibility_and_order(): void
    {
        $designer = $this->designerWithProfile();
        Sanctum::actingAs($designer);
        $payload = $this->payloadFor($designer->designerProfile);
        $this->putJson($this->endpoint(), $payload)
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.professional.availability', 'available')
            ->assertJsonPath('data.professional.years_of_experience', 5)
            ->assertJsonPath('data.professional.professional_note', 'خبرة مهنية إضافية.')
            ->assertJsonPath('data.professional.visibility.tools', false)
            ->assertJsonPath('data.professional.specialties.service.0.name', 'الهوية البصرية')
            ->assertJsonPath('data.professional.specialties.occasion.0.name', 'المؤتمرات')
            ->assertJsonPath('data.professional.specialties.style.0.name', 'Minimal')
            ->assertJsonPath('data.professional.skills.0.level', 'expert')
            ->assertJsonPath('data.professional.tools.0.level', 'advanced')
            ->assertJsonPath('data.professional.languages.0.level', 'native');

        $profile = $designer->designerProfile->fresh();
        $this->assertSame(['الهوية البصرية', 'تصميم الشعارات'], $profile->specialties()->where('kind', 'service')->pluck('name')->all());
        $this->assertSame([0, 1], $profile->specialties()->where('kind', 'service')->pluck('sort_order')->all());
        $this->assertSame([0, 1], $profile->skills()->pluck('sort_order')->all());
    }

    public function test_resource_hides_ids_normalized_names_item_timestamps_and_user_data(): void
    {
        $designer = $this->designerWithProfile();
        Sanctum::actingAs($designer);
        $response = $this->putJson($this->endpoint(), $this->payloadFor($designer->designerProfile))->assertOk();
        $json = json_encode($response->json(), JSON_THROW_ON_ERROR);
        foreach (['normalized_name', 'designer_profile_id', 'publication_status', 'published_at', 'display_name', $designer->email] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }
        $this->assertArrayNotHasKey('id', $response->json('data.professional.skills.0'));
        $this->assertArrayNotHasKey('created_at', $response->json('data.professional.skills.0'));
    }

    public function test_names_are_cleaned_normalized_and_duplicates_are_rejected_per_section(): void
    {
        $designer = $this->designerWithProfile();
        Sanctum::actingAs($designer);
        $payload = $this->payloadFor($designer->designerProfile);
        $payload['skills'] = [['name' => '  Adobe   Design  ', 'level' => 'advanced']];
        $this->putJson($this->endpoint(), $payload)->assertOk()->assertJsonPath('data.professional.skills.0.name', 'Adobe Design');
        $this->assertDatabaseHas('designer_profile_skills', ['name' => 'Adobe Design', 'normalized_name' => 'adobe design']);

        $profile = $designer->designerProfile->fresh();
        foreach (['skills', 'tools', 'languages'] as $section) {
            $duplicate = $this->payloadFor($profile);
            $duplicate[$section] = [
                ['name' => 'Duplicate Name', 'level' => $section === 'languages' ? 'basic' : 'beginner'],
                ['name' => 'duplicate name', 'level' => $section === 'languages' ? 'native' : 'expert'],
            ];
            $this->putJson($this->endpoint(), $duplicate)->assertUnprocessable()->assertJsonValidationErrors("{$section}.1.name");
        }
    }

    public function test_specialty_duplicates_are_per_kind_and_same_name_across_kinds_is_allowed(): void
    {
        $designer = $this->designerWithProfile();
        Sanctum::actingAs($designer);
        $payload = $this->payloadFor($designer->designerProfile);
        $payload['specialties'] = ['service' => ['مشترك'], 'occasion' => ['مشترك'], 'style' => ['مشترك']];
        $this->putJson($this->endpoint(), $payload)->assertOk();

        $payload = $this->payloadFor($designer->designerProfile->fresh());
        $payload['specialties']['service'] = ['Branding', 'branding'];
        $this->putJson($this->endpoint(), $payload)->assertUnprocessable()->assertJsonValidationErrors('specialties.service.1');
    }

    public function test_collection_limits_years_note_and_levels_are_validated(): void
    {
        $designer = $this->designerWithProfile();
        Sanctum::actingAs($designer);
        $profile = $designer->designerProfile;
        $cases = [
            ['specialties.service', array_fill(0, 7, 'عنصر صالح')],
            ['skills', array_fill(0, 21, ['name' => 'مهارة صالحة', 'level' => 'expert'])],
            ['tools', array_fill(0, 21, ['name' => 'أداة صالحة', 'level' => 'expert'])],
            ['languages', array_fill(0, 9, ['name' => 'لغة صالحة', 'level' => 'native'])],
        ];
        foreach ($cases as [$path, $value]) {
            $payload = $this->payloadFor($profile);
            data_set($payload, $path, $value);
            $this->putJson($this->endpoint(), $payload)->assertUnprocessable()->assertJsonValidationErrors($path);
        }

        $payload = $this->payloadFor($profile);
        $payload['specialties'] = [
            'service' => ['خدمة 1', 'خدمة 2', 'خدمة 3', 'خدمة 4', 'خدمة 5'],
            'occasion' => ['مناسبة 1', 'مناسبة 2', 'مناسبة 3', 'مناسبة 4'],
            'style' => ['أسلوب 1', 'أسلوب 2', 'أسلوب 3', 'أسلوب 4'],
        ];
        $this->putJson($this->endpoint(), $payload)->assertUnprocessable()->assertJsonValidationErrors('specialties');

        foreach ([-1, 71] as $years) {
            $payload = $this->payloadFor($profile); $payload['years_of_experience'] = $years;
            $this->putJson($this->endpoint(), $payload)->assertUnprocessable()->assertJsonValidationErrors('years_of_experience');
        }
        $payload = $this->payloadFor($profile); $payload['professional_note'] = str_repeat('x', 1201);
        $this->putJson($this->endpoint(), $payload)->assertUnprocessable()->assertJsonValidationErrors('professional_note');

        foreach ([['skills', 'wrong'], ['tools', 'wrong'], ['languages', 'wrong']] as [$section, $level]) {
            $payload = $this->payloadFor($profile); $payload[$section][0]['level'] = $level;
            $this->putJson($this->endpoint(), $payload)->assertUnprocessable()->assertJsonValidationErrors("{$section}.0.level");
        }
    }

    public function test_years_boundaries_and_note_maximum_are_accepted(): void
    {
        $designer = $this->designerWithProfile();
        Sanctum::actingAs($designer);

        foreach ([0, 70] as $years) {
            $profile = $designer->designerProfile->fresh();
            $payload = $this->emptyPayloadFor($profile);
            $payload['years_of_experience'] = $years;
            $this->putJson($this->endpoint(), $payload)
                ->assertOk()->assertJsonPath('data.professional.years_of_experience', $years);
        }

        $profile = $designer->designerProfile->fresh();
        $payload = $this->emptyPayloadFor($profile);
        $payload['years_of_experience'] = 70;
        $payload['professional_note'] = str_repeat('x', 1200);
        $this->putJson($this->endpoint(), $payload)->assertOk();
    }

    public function test_replace_all_removes_old_items_and_profile_delete_cascades(): void
    {
        $designer = $this->designerWithProfile();
        $profile = $designer->designerProfile;
        $old = $profile->skills()->create(['name' => 'قديمة', 'normalized_name' => 'قديمة', 'level' => 'beginner', 'sort_order' => 0]);
        Sanctum::actingAs($designer);
        $this->putJson($this->endpoint(), $this->payloadFor($profile->fresh()))->assertOk();
        $this->assertDatabaseMissing('designer_profile_skills', ['id' => $old->id]);
        $profile->delete();
        $this->assertSame(0, DesignerProfileSpecialty::query()->count());
        $this->assertSame(0, DesignerProfileSkill::query()->count());
        $this->assertSame(0, DesignerProfileTool::query()->count());
        $this->assertSame(0, DesignerProfileLanguage::query()->count());
    }

    public function test_version_conflict_changes_nothing(): void
    {
        $designer = $this->designerWithProfile();
        $profile = $designer->designerProfile;
        Sanctum::actingAs($designer);
        $payload = $this->payloadFor($profile);
        $payload['expected_updated_at'] = '2000-01-01T00:00:00+00:00';
        $this->putJson($this->endpoint(), $payload)
            ->assertConflict()
            ->assertJsonPath('data.code', 'designer_profile_version_conflict')
            ->assertJsonPath('data.current_updated_at', $profile->updated_at->toJSON());
        $this->assertSame('unavailable', $profile->fresh()->availability);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_no_op_preserves_timestamp_relations_and_audit(): void
    {
        $designer = $this->designerWithProfile();
        $profile = $designer->designerProfile;
        Sanctum::actingAs($designer);
        $this->putJson($this->endpoint(), $this->payloadFor($profile))->assertOk();
        $profile = $profile->fresh();
        $payload = $this->payloadFor($profile);
        $before = $profile->updated_at;
        $skillIds = $profile->skills()->pluck('id')->all();
        $auditCount = AuditEvent::query()->count();
        $this->putJson($this->endpoint(), $payload)->assertOk()->assertJsonPath('data.changed', false);
        $this->assertTrue($before->equalTo($profile->fresh()->updated_at));
        $this->assertSame($skillIds, $profile->skills()->pluck('id')->all());
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    public function test_audit_metadata_is_allowlisted_and_contains_no_professional_values(): void
    {
        $designer = $this->designerWithProfile();
        Sanctum::actingAs($designer);
        $this->putJson($this->endpoint(), $this->payloadFor($designer->designerProfile))->assertOk();
        $event = AuditEvent::query()->sole();
        $this->assertSame('designer.profile.professional.updated', $event->event_type);
        $this->assertSame('designer_profiles', $event->category);
        $this->assertSame('designer_profile', $event->target_type);
        $this->assertSame('update_professional_data', $event->action);
        $this->assertEqualsCanonicalizing([
            'profile_id', 'previous_availability', 'current_availability',
            'previous_years_of_experience', 'current_years_of_experience',
            'previous_specialties_count', 'current_specialties_count',
            'previous_skills_count', 'current_skills_count', 'previous_tools_count',
            'current_tools_count', 'previous_languages_count', 'current_languages_count',
            'visibility_changed', 'changed_sections',
        ], array_keys($event->metadata));
        $metadata = json_encode($event->metadata, JSON_THROW_ON_ERROR);
        foreach (['الهوية البصرية', 'تصميم الشعارات', 'Adobe Photoshop', 'العربية', 'خبرة مهنية إضافية.'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $metadata);
        }
    }

    public function test_audit_failure_rolls_back_profile_and_all_relations(): void
    {
        $designer = $this->designerWithProfile();
        $profile = $designer->designerProfile;
        $profile->skills()->create(['name' => 'قديمة', 'normalized_name' => 'قديمة', 'level' => 'beginner', 'sort_order' => 0]);
        $before = $profile->fresh()->updated_at;
        AuditEvent::creating(static function (): void { throw new \RuntimeException('audit unavailable'); });
        Sanctum::actingAs($designer);
        $this->putJson($this->endpoint(), $this->payloadFor($profile->fresh()))->assertServerError();
        $current = $profile->fresh();
        $this->assertSame('unavailable', $current->availability);
        $this->assertNull($current->years_of_experience);
        $this->assertTrue($before->equalTo($current->updated_at));
        $this->assertSame(['قديمة'], $current->skills()->pluck('name')->all());
        $this->assertSame(0, $current->specialties()->count());
        $this->assertSame(0, $current->tools()->count());
        $this->assertSame(0, $current->languages()->count());
    }

    public function test_completion_reaches_all_five_sections(): void
    {
        $designer = $this->designerWithProfile();
        Sanctum::actingAs($designer);
        $this->putJson($this->endpoint(), $this->payloadFor($designer->designerProfile))
            ->assertOk()
            ->assertJsonPath('data.completion.completed', 5)
            ->assertJsonPath('data.completion.total', 5)
            ->assertJsonPath('data.completion.percentage', 100)
            ->assertJsonPath('data.completion.missing', [])
            ->assertJsonPath('data.completion.sections.experience.count', 1)
            ->assertJsonPath('data.completion.sections.specialties.count', 4)
            ->assertJsonPath('data.completion.sections.skills.count', 2)
            ->assertJsonPath('data.completion.sections.tools.count', 1)
            ->assertJsonPath('data.completion.sections.languages.count', 1);
    }

    public function test_no_public_professional_route_is_created(): void
    {
        $this->getJson('/api/designers/example/professional')->assertNotFound();
    }

    private function endpoint(): string { return '/api/designer/profile/professional'; }

    private function designerWithProfile(array $userAttributes = []): User
    {
        $designer = $this->userWithRole('designer', $userAttributes);
        $designer->designerProfile()->create([
            'display_name' => 'مصمم محترف', 'availability' => 'unavailable',
        ]);
        return $designer->fresh();
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);
        return $user;
    }

    private function emptyPayloadFor(?DesignerProfile $profile): array
    {
        return [
            'expected_updated_at' => $profile?->updated_at?->toJSON() ?? now()->toJSON(),
            'availability' => $profile?->availability ?? DesignerProfile::AVAILABILITY_UNAVAILABLE,
            'years_of_experience' => null,
            'professional_note' => null,
            'visibility' => [
                'availability' => $profile?->show_availability_publicly ?? true,
                'specialties' => $profile?->show_specialties_publicly ?? true,
                'skills' => $profile?->show_skills_publicly ?? true,
                'tools' => $profile?->show_tools_publicly ?? true,
                'languages' => $profile?->show_languages_publicly ?? true,
                'experience' => $profile?->show_experience_publicly ?? true,
            ],
            'specialties' => [
                'service' => [],
                'occasion' => [],
                'style' => [],
            ],
            'skills' => [],
            'tools' => [],
            'languages' => [],
        ];
    }

    private function payloadFor(?DesignerProfile $profile): array
    {
        return [
            ...$this->emptyPayloadFor($profile),
            'availability' => 'available',
            'years_of_experience' => 5,
            'professional_note' => 'خبرة مهنية إضافية.',
            'visibility' => ['availability' => true, 'specialties' => true, 'skills' => true, 'tools' => false, 'languages' => true, 'experience' => true],
            'specialties' => ['service' => ['الهوية البصرية', 'تصميم الشعارات'], 'occasion' => ['المؤتمرات'], 'style' => ['Minimal']],
            'skills' => [['name' => 'تصميم الشعارات', 'level' => 'expert'], ['name' => 'التحريك', 'level' => 'advanced']],
            'tools' => [['name' => 'Adobe Photoshop', 'level' => 'advanced']],
            'languages' => [['name' => 'العربية', 'level' => 'native']],
        ];
    }
}
