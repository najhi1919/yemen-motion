<?php

namespace Tests\Feature\Designer;

use App\Http\Controllers\Api\DesignerProfilePublicationController;
use App\Models\AuditEvent;
use App\Models\DesignerProfile;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesignerProfilePublicationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private int $designerSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthRolesSeeder::class);
    }

    public function test_routes_have_expected_names_controllers_and_http_methods(): void
    {
        $routes = [
            'designer.profile.publication.show' => ['show', ['GET', 'HEAD']],
            'designer.profile.publication.preview' => ['preview', ['GET', 'HEAD']],
            'designer.profile.publication.publish' => ['publish', ['PATCH']],
            'designer.profile.publication.hide' => ['hide', ['PATCH']],
        ];

        foreach ($routes as $name => [$method, $httpMethods]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertSame(DesignerProfilePublicationController::class."@{$method}", $route?->getActionName());
            $this->assertSame($httpMethods, $route?->methods());
        }

        $designer = $this->readyDesigner();
        Sanctum::actingAs($designer);
        foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
            $this->{$method}($this->statusEndpoint())->assertMethodNotAllowed();
            $this->{$method}($this->previewEndpoint())->assertMethodNotAllowed();
        }
        foreach ([$this->publishEndpoint(), $this->hideEndpoint()] as $endpoint) {
            $this->getJson($endpoint)->assertMethodNotAllowed();
            $this->postJson($endpoint)->assertMethodNotAllowed();
            $this->putJson($endpoint)->assertMethodNotAllowed();
            $this->deleteJson($endpoint)->assertMethodNotAllowed();
        }
    }

    public function test_guest_non_designers_disabled_designer_and_multi_role_contracts(): void
    {
        $this->getJson($this->statusEndpoint())->assertUnauthorized();
        $this->patchJson($this->publishEndpoint(), [])->assertUnauthorized();

        foreach (['client', 'staff', 'admin'] as $role) {
            Sanctum::actingAs($this->userWithRole($role));
            $this->getJson($this->statusEndpoint())->assertForbidden();
            $this->patchJson($this->publishEndpoint(), [])->assertForbidden();
        }

        $disabled = $this->readyDesigner(['disabled_at' => now()]);
        Sanctum::actingAs($disabled);
        $this->getJson($this->statusEndpoint())->assertForbidden();
        $this->patchJson($this->publishEndpoint(), [])->assertForbidden();

        $multiRole = $this->readyDesigner();
        $multiRole->assignRole('admin');
        Sanctum::actingAs($multiRole);
        $this->getJson($this->statusEndpoint())->assertOk();
    }

    public function test_missing_profile_returns_safe_404_for_all_endpoints(): void
    {
        $designer = $this->userWithRole('designer', ['username' => 'missing-profile']);
        Sanctum::actingAs($designer);

        $this->getJson($this->statusEndpoint())->assertNotFound()->assertJsonPath('data.code', 'designer_profile_required');
        $this->getJson($this->previewEndpoint())->assertNotFound()->assertJsonPath('data.code', 'designer_profile_required');
        $this->patchJson($this->publishEndpoint(), ['expected_updated_at' => now()->toJSON()])->assertNotFound();
        $this->patchJson($this->hideEndpoint(), ['expected_updated_at' => now()->toJSON()])->assertNotFound();
    }

    public function test_draft_status_returns_defaults_and_all_eleven_blockers(): void
    {
        $designer = $this->blankDesigner();
        Sanctum::actingAs($designer);

        $response = $this->getJson($this->statusEndpoint())
            ->assertOk()
            ->assertJsonPath('data.publication.status', DesignerProfile::PUBLICATION_DRAFT)
            ->assertJsonPath('data.publication.published_at', null)
            ->assertJsonPath('data.publication.hidden_at', null)
            ->assertJsonPath('data.expected_updated_at', $this->profile($designer)->updated_at->toISOString())
            ->assertJsonPath('data.readiness.ready', false)
            ->assertJsonPath('data.readiness.completed', 0)
            ->assertJsonPath('data.readiness.total', 11)
            ->assertJsonPath('data.actions.can_preview', true)
            ->assertJsonPath('data.actions.can_publish', false)
            ->assertJsonPath('data.actions.can_hide', false);

        $this->assertSame([
            'username_missing', 'display_name_missing', 'professional_title_missing',
            'primary_specialty_missing', 'bio_missing', 'avatar_missing',
            'experience_missing', 'specialties_missing', 'skills_missing',
            'tools_missing', 'languages_missing',
        ], collect($response->json('data.readiness.blockers'))->pluck('code')->all());
        foreach ($response->json('data.readiness.blockers') as $blocker) {
            $this->assertSame(['code', 'section', 'message', 'action'], array_keys($blocker));
        }
    }

    public function test_readiness_reaches_eleven_without_cover_and_ignores_availability_and_privacy(): void
    {
        $designer = $this->readyDesigner();
        $profile = $this->profile($designer);
        $profile->update([
            'availability' => DesignerProfile::AVAILABILITY_UNAVAILABLE,
            'cover_path' => null,
            'show_availability_publicly' => false,
            'show_specialties_publicly' => false,
            'show_skills_publicly' => false,
            'show_tools_publicly' => false,
            'show_languages_publicly' => false,
            'show_experience_publicly' => false,
        ]);
        Sanctum::actingAs($designer);

        $this->getJson($this->statusEndpoint())
            ->assertOk()
            ->assertJsonPath('data.readiness.ready', true)
            ->assertJsonPath('data.readiness.completed', 11)
            ->assertJsonPath('data.readiness.total', 11)
            ->assertJsonPath('data.readiness.blockers', [])
            ->assertJsonPath('data.actions.can_publish', true);
    }

    public function test_owner_preview_works_in_draft_applies_visibility_and_preserves_order(): void
    {
        $designer = $this->readyDesigner();
        $profile = $this->profile($designer);
        $profile->forceFill([
            'professional_note' => 'معلومة إضافية آمنة.',
            'show_specialties_publicly' => false,
            'show_tools_publicly' => false,
            'show_experience_publicly' => false,
        ])->save();
        $profile->skills()->create([
            'name' => 'مهارة ثانية', 'normalized_name' => 'مهارة ثانية', 'level' => 'advanced', 'sort_order' => 1,
        ]);
        Sanctum::actingAs($designer);

        $response = $this->getJson($this->previewEndpoint())
            ->assertOk()
            ->assertJsonPath('data.preview.identity.username', 'ready-designer-1')
            ->assertJsonPath('data.preview.publication.status', 'draft')
            ->assertJsonPath('data.preview.publication.is_publicly_visible', false)
            ->assertJsonPath('data.preview.publication.preview_mode', true)
            ->assertJsonPath('data.preview.professional.sections.specialties.visible', false)
            ->assertJsonPath('data.preview.professional.sections.tools.visible', false)
            ->assertJsonPath('data.preview.professional.sections.experience.visible', false)
            ->assertJsonPath('data.preview.professional.sections.skills.visible', true)
            ->assertJsonPath('data.preview.professional.sections.skills.items.0.name', 'تصميم الشعارات')
            ->assertJsonPath('data.preview.professional.sections.skills.items.1.name', 'مهارة ثانية')
            ->assertJsonPath('data.preview.professional.additional_information.professional_note', 'معلومة إضافية آمنة.');

        $this->assertArrayNotHasKey('service', $response->json('data.preview.professional.sections.specialties'));
        $this->assertArrayNotHasKey('items', $response->json('data.preview.professional.sections.tools'));
        $this->assertArrayNotHasKey('years_of_experience', $response->json('data.preview.professional.sections.experience'));

        $profile->forceFill(['publication_status' => DesignerProfile::PUBLICATION_PUBLISHED])->save();
        $this->getJson($this->previewEndpoint())
            ->assertOk()
            ->assertJsonPath('data.preview.publication.status', 'published')
            ->assertJsonPath('data.preview.publication.is_publicly_visible', true);
        $profile->forceFill(['publication_status' => DesignerProfile::PUBLICATION_HIDDEN])->save();
        $this->getJson($this->previewEndpoint())
            ->assertOk()
            ->assertJsonPath('data.preview.publication.status', 'hidden')
            ->assertJsonPath('data.preview.publication.is_publicly_visible', false);
    }

    public function test_preview_and_status_do_not_leak_ids_paths_email_or_normalized_names(): void
    {
        $designer = $this->readyDesigner();
        Sanctum::actingAs($designer);

        foreach ([$this->getJson($this->statusEndpoint()), $this->getJson($this->previewEndpoint())] as $response) {
            $response->assertOk();
            $json = json_encode($response->json(), JSON_THROW_ON_ERROR);
            foreach (['designer_profile_id', 'normalized_name', 'sort_order', 'avatar-secret-path', $designer->email, 'disabled_at', 'user_id'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $json);
            }
        }
    }

    public function test_requests_reject_missing_version_extra_body_query_and_get_input(): void
    {
        $designer = $this->readyDesigner();
        Sanctum::actingAs($designer);

        $this->patchJson($this->publishEndpoint(), [])->assertUnprocessable()->assertJsonValidationErrors('expected_updated_at');
        foreach (['publication_status', 'force', 'user_id', 'hidden_at'] as $field) {
            $this->patchJson($this->publishEndpoint(), [
                'expected_updated_at' => $this->profile($designer)->updated_at->toJSON(),
                $field => 'forbidden',
            ])->assertUnprocessable()->assertJsonValidationErrors('unsupported_request_input');
        }
        $this->patchJson($this->publishEndpoint().'?force=1', [
            'expected_updated_at' => $this->profile($designer)->updated_at->toJSON(),
        ])->assertUnprocessable()->assertJsonValidationErrors('unsupported_request_input');
        $this->getJson($this->statusEndpoint().'?public=1')->assertUnprocessable()->assertJsonValidationErrors('unsupported_request_input');
        $this->getJson($this->previewEndpoint().'?private=1')->assertUnprocessable()->assertJsonValidationErrors('unsupported_request_input');
        $this->json('GET', $this->statusEndpoint(), ['force' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('unsupported_request_input');
    }

    public function test_publish_rejects_incomplete_profile_with_full_readiness_and_no_change_or_audit(): void
    {
        $designer = $this->blankDesigner();
        $profile = $this->profile($designer);
        Sanctum::actingAs($designer);

        $this->patchJson($this->publishEndpoint(), $this->versionPayload($profile))
            ->assertConflict()
            ->assertJsonPath('data.code', 'designer_profile_not_ready')
            ->assertJsonPath('data.readiness.ready', false)
            ->assertJsonPath('data.readiness.total', 11)
            ->assertJsonCount(11, 'data.readiness.blockers');
        $this->assertSame(DesignerProfile::PUBLICATION_DRAFT, $profile->fresh()->publication_status);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_draft_publish_succeeds_sets_timestamps_and_repeated_publish_is_no_op(): void
    {
        $designer = $this->readyDesigner();
        $profile = $this->profile($designer);
        Sanctum::actingAs($designer);

        $this->patchJson($this->publishEndpoint(), $this->versionPayload($profile))
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.publication.status', 'published')
            ->assertJsonPath('data.publication.hidden_at', null)
            ->assertJsonPath('data.actions.can_publish', false)
            ->assertJsonPath('data.actions.can_hide', true);
        $published = $profile->fresh();
        $this->assertNotNull($published->published_at);
        $this->assertNull($published->hidden_at);
        $beforeUpdatedAt = $published->updated_at;
        $beforePublishedAt = $published->published_at;
        $auditCount = AuditEvent::query()->count();

        $this->patchJson($this->publishEndpoint(), $this->versionPayload($published))
            ->assertOk()->assertJsonPath('data.changed', false);
        $current = $profile->fresh();
        $this->assertTrue($beforeUpdatedAt->equalTo($current->updated_at));
        $this->assertTrue($beforePublishedAt->equalTo($current->published_at));
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    public function test_hide_transition_preserves_published_at_and_hidden_no_op_preserves_state(): void
    {
        $designer = $this->publishedDesigner();
        $profile = $this->profile($designer);
        $publishedAt = $profile->published_at;
        Sanctum::actingAs($designer);

        $this->patchJson($this->hideEndpoint(), $this->versionPayload($profile))
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.publication.status', 'hidden')
            ->assertJsonPath('data.actions.can_hide', false)
            ->assertJsonPath('data.actions.can_publish', true);
        $hidden = $profile->fresh();
        $this->assertNotNull($hidden->hidden_at);
        $this->assertTrue($publishedAt->equalTo($hidden->published_at));
        $beforeUpdatedAt = $hidden->updated_at;
        $beforeHiddenAt = $hidden->hidden_at;
        $auditCount = AuditEvent::query()->count();

        $this->patchJson($this->hideEndpoint(), $this->versionPayload($hidden))
            ->assertOk()->assertJsonPath('data.changed', false);
        $current = $profile->fresh();
        $this->assertTrue($beforeUpdatedAt->equalTo($current->updated_at));
        $this->assertTrue($beforeHiddenAt->equalTo($current->hidden_at));
        $this->assertSame($auditCount, AuditEvent::query()->count());
    }

    public function test_draft_cannot_be_hidden(): void
    {
        $designer = $this->readyDesigner();
        $profile = $this->profile($designer);
        Sanctum::actingAs($designer);

        $this->patchJson($this->hideEndpoint(), $this->versionPayload($profile))
            ->assertConflict()
            ->assertJsonPath('data.code', 'designer_profile_not_published')
            ->assertJsonPath('data.current_status', 'draft');
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_hidden_profile_can_be_republished_only_when_ready(): void
    {
        $designer = $this->readyDesigner();
        $profile = $this->profile($designer);
        $originalPublishedAt = now()->subDay();
        $profile->refresh()->forceFill([
            'publication_status' => DesignerProfile::PUBLICATION_HIDDEN,
            'published_at' => $originalPublishedAt,
            'hidden_at' => now(),
        ])->save();
        Sanctum::actingAs($designer);

        $this->patchJson($this->publishEndpoint(), $this->versionPayload($profile->fresh()))
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.publication.status', 'published')
            ->assertJsonPath('data.publication.hidden_at', null);

        $profile->refresh()->forceFill([
            'publication_status' => DesignerProfile::PUBLICATION_HIDDEN,
            'hidden_at' => now(),
            'years_of_experience' => null,
        ])->save();
        $this->patchJson($this->publishEndpoint(), $this->versionPayload($profile->fresh()))
            ->assertConflict()
            ->assertJsonPath('data.code', 'designer_profile_not_ready')
            ->assertJsonPath('data.readiness.blockers.0.code', 'experience_missing');
        $this->assertSame(DesignerProfile::PUBLICATION_HIDDEN, $profile->fresh()->publication_status);
    }

    public function test_stale_version_returns_conflict_without_change(): void
    {
        $designer = $this->readyDesigner();
        $profile = $this->profile($designer);
        Sanctum::actingAs($designer);

        $this->patchJson($this->publishEndpoint(), ['expected_updated_at' => '2000-01-01T00:00:00+00:00'])
            ->assertConflict()
            ->assertJsonPath('data.code', 'designer_profile_publication_version_conflict')
            ->assertJsonPath('data.current_updated_at', $profile->updated_at->toJSON());
        $this->assertSame(DesignerProfile::PUBLICATION_DRAFT, $profile->fresh()->publication_status);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_publish_and_hide_audits_use_exact_safe_metadata_allowlist(): void
    {
        $designer = $this->readyDesigner();
        $profile = $this->profile($designer);
        Sanctum::actingAs($designer);
        $this->patchJson($this->publishEndpoint(), $this->versionPayload($profile))->assertOk();
        $this->patchJson($this->hideEndpoint(), $this->versionPayload($profile->fresh()))->assertOk();

        $events = AuditEvent::query()->orderBy('id')->get();
        $this->assertSame([
            'designer.profile.publication.published',
            'designer.profile.publication.hidden',
        ], $events->pluck('event_type')->all());
        $this->assertSame(['publish_profile', 'hide_profile'], $events->pluck('action')->all());
        foreach ($events as $event) {
            $this->assertSame('designer_profiles', $event->category);
            $this->assertSame('designer_profile', $event->target_type);
            $this->assertSame('success', $event->outcome);
            $this->assertEqualsCanonicalizing([
                'profile_id', 'previous_status', 'current_status',
                'readiness_completed', 'readiness_total',
            ], array_keys($event->metadata));
            $metadata = json_encode($event->metadata, JSON_THROW_ON_ERROR);
            foreach (['ready-designer-', 'مصمم جاهز', 'نبذة مكتملة', 'avatar-secret-path'] as $privateValue) {
                $this->assertStringNotContainsString($privateValue, $metadata);
            }
        }
    }

    public function test_audit_failure_rolls_back_publish(): void
    {
        $designer = $this->readyDesigner();
        $profile = $this->profile($designer);
        $before = $profile->updated_at;
        AuditEvent::creating(static function (): void {
            throw new \RuntimeException('audit unavailable');
        });
        Sanctum::actingAs($designer);

        $this->patchJson($this->publishEndpoint(), $this->versionPayload($profile))->assertServerError();
        $current = $profile->fresh();
        $this->assertSame(DesignerProfile::PUBLICATION_DRAFT, $current->publication_status);
        $this->assertNull($current->published_at);
        $this->assertNull($current->hidden_at);
        $this->assertTrue($before->equalTo($current->updated_at));
    }

    public function test_audit_failure_rolls_back_hide(): void
    {
        $designer = $this->publishedDesigner();
        $profile = $this->profile($designer);
        $before = $profile->updated_at;
        $publishedAt = $profile->published_at;
        AuditEvent::creating(static function (): void {
            throw new \RuntimeException('audit unavailable');
        });
        Sanctum::actingAs($designer);

        $this->patchJson($this->hideEndpoint(), $this->versionPayload($profile))->assertServerError();
        $current = $profile->fresh();
        $this->assertSame(DesignerProfile::PUBLICATION_PUBLISHED, $current->publication_status);
        $this->assertNull($current->hidden_at);
        $this->assertTrue($publishedAt->equalTo($current->published_at));
        $this->assertTrue($before->equalTo($current->updated_at));
    }

    public function test_no_public_designer_profile_route_is_created(): void
    {
        $this->getJson('/api/designers/example')->assertNotFound();
        $this->get('/designers/example')->assertNotFound();
    }

    private function statusEndpoint(): string
    {
        return '/api/designer/profile/publication';
    }

    private function previewEndpoint(): string
    {
        return '/api/designer/profile/publication/preview';
    }

    private function publishEndpoint(): string
    {
        return '/api/designer/profile/publication/publish';
    }

    private function hideEndpoint(): string
    {
        return '/api/designer/profile/publication/hide';
    }

    private function blankDesigner(): User
    {
        $designer = $this->userWithRole('designer');
        $designer->designerProfile()->create(['display_name' => '']);

        return $designer->fresh();
    }

    /** @param array<string, mixed> $userAttributes */
    private function readyDesigner(array $userAttributes = []): User
    {
        $designer = $this->userWithRole('designer', [
            'username' => 'ready-designer-'.(++$this->designerSequence),
            ...$userAttributes,
        ]);
        $profile = $designer->designerProfile()->create([
            'display_name' => 'مصمم جاهز',
            'professional_title' => 'مصمم جرافيك',
            'primary_specialty' => 'الهوية البصرية',
            'bio' => 'نبذة مكتملة للملف.',
            'avatar_path' => 'avatar-secret-path/image.jpg',
            'availability' => DesignerProfile::AVAILABILITY_AVAILABLE,
            'years_of_experience' => 5,
            'professional_note' => 'معلومات مهنية إضافية.',
        ]);
        $profile->specialties()->create([
            'kind' => 'service', 'name' => 'تصميم الشعارات', 'normalized_name' => 'تصميم الشعارات', 'sort_order' => 0,
        ]);
        $profile->skills()->create([
            'name' => 'تصميم الشعارات', 'normalized_name' => 'تصميم الشعارات', 'level' => 'expert', 'sort_order' => 0,
        ]);
        $profile->tools()->create([
            'name' => 'Adobe Photoshop', 'normalized_name' => 'adobe photoshop', 'level' => 'advanced', 'sort_order' => 0,
        ]);
        $profile->languages()->create([
            'name' => 'العربية', 'normalized_name' => 'العربية', 'level' => 'native', 'sort_order' => 0,
        ]);

        return $designer->fresh();
    }

    private function publishedDesigner(): User
    {
        $designer = $this->readyDesigner();
        $this->profile($designer)->forceFill([
            'publication_status' => DesignerProfile::PUBLICATION_PUBLISHED,
            'published_at' => now()->subHour(),
            'hidden_at' => null,
        ])->save();

        return $designer;
    }

    /** @param array<string, mixed> $attributes */
    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function profile(User $designer): DesignerProfile
    {
        return $designer->designerProfile()->firstOrFail();
    }

    /** @return array{expected_updated_at: string} */
    private function versionPayload(DesignerProfile $profile): array
    {
        return ['expected_updated_at' => $profile->updated_at->toJSON()];
    }
}
