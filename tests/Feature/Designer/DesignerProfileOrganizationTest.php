<?php

namespace Tests\Feature\Designer;

use App\Models\AuditEvent;
use App\Models\DesignerProfile;
use App\Models\DesignerProfileOrganization;
use App\Models\User;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesignerProfileOrganizationTest extends TestCase
{
    use RefreshDatabase;

    private int $designerSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthRolesSeeder::class);
    }

    public function test_owner_get_returns_null_when_no_organization(): void
    {
        $designer = $this->readyDesigner();
        Sanctum::actingAs($designer);

        $this->getJson('/api/designer/profile/organization')
            ->assertOk()
            ->assertJsonPath('data.organization', null)
            ->assertJsonPath('data.updated_at', null);
    }

    public function test_owner_get_returns_organization_data_without_raw_logo_path(): void
    {
        $designer = $this->readyDesigner();
        $this->createOrganization($designer, [
            'organization_name' => 'Test',
            'organization_type' => 'studio',
            'logo_path' => 'orgs/logo.png',
            'show_publicly' => true,
        ]);
        Sanctum::actingAs($designer);

        $response = $this->getJson('/api/designer/profile/organization')->assertOk();
        $this->assertSame('Test', $response->json('data.organization.name'));
        $this->assertSame('studio', $response->json('data.organization.type'));
        $this->assertTrue($response->json('data.organization.has_logo'));
        $this->assertArrayNotHasKey('logo_path', $response->json('data.organization'));
        $this->assertNotNull($response->json('data.updated_at'));
    }

    public function test_put_creates_organization_when_none_exists(): void
    {
        $designer = $this->readyDesigner();
        $updatedAt = $designer->designerProfile->updated_at;
        Sanctum::actingAs($designer);

        $this->putJson('/api/designer/profile/organization', $this->validPayload())
            ->assertOk()
            ->assertJsonPath('data.changed', true);

        $this->assertDatabaseHas('designer_profile_organizations', [
            'designer_profile_id' => $designer->designerProfile->id,
            'organization_name' => 'Test Organization',
            'show_publicly' => true,
        ]);
        $this->assertTrue($updatedAt->equalTo($designer->designerProfile->fresh()->updated_at));
    }

    public function test_put_rejects_invalid_organization_types(): void
    {
        $designer = $this->readyDesigner();
        Sanctum::actingAs($designer);

        foreach (['freelancer', 'business'] as $invalidType) {
            $this->putJson('/api/designer/profile/organization', $this->validPayload([
                'organization_type' => $invalidType,
            ]))->assertUnprocessable();
        }
    }

    public function test_put_rejects_http_website_url(): void
    {
        $designer = $this->readyDesigner();
        Sanctum::actingAs($designer);

        $this->putJson('/api/designer/profile/organization', $this->validPayload([
            'website_url' => 'http://example.com',
        ]))->assertUnprocessable();

        $this->putJson('/api/designer/profile/organization', $this->validPayload([
            'website_url' => 'https://example.com',
        ]))->assertOk();
    }

    public function test_put_with_null_expected_version_fails_if_exists(): void
    {
        $designer = $this->readyDesigner();
        $this->createOrganization($designer);
        Sanctum::actingAs($designer);

        $this->putJson('/api/designer/profile/organization', $this->validPayload([
            'expected_updated_at' => null,
        ]))->assertStatus(409)->assertJsonPath('data.code', 'organization_version_conflict');
    }

    public function test_put_with_old_expected_version_fails(): void
    {
        $designer = $this->readyDesigner();
        $this->createOrganization($designer);
        Sanctum::actingAs($designer);

        $this->putJson('/api/designer/profile/organization', $this->validPayload([
            'expected_updated_at' => now()->subDay()->toJSON(),
        ]))->assertStatus(409)->assertJsonPath('data.code', 'organization_version_conflict');
    }

    public function test_delete_organization_with_old_version_fails(): void
    {
        $designer = $this->readyDesigner();
        $this->createOrganization($designer);
        Sanctum::actingAs($designer);

        $this->deleteJson('/api/designer/profile/organization', [
            'expected_updated_at' => now()->subDay()->toJSON(),
        ])->assertStatus(409)->assertJsonPath('data.code', 'organization_version_conflict');
    }

    public function test_post_logo_with_old_version_fails_and_cleans_orphan_file(): void
    {
        Storage::fake('works_private');
        $designer = $this->readyDesigner();
        $this->createOrganization($designer);
        Sanctum::actingAs($designer);

        $filesBefore = Storage::disk('works_private')->allFiles();

        $this->postJson('/api/designer/profile/organization/logo', [
            'expected_updated_at' => now()->subDay()->toJSON(),
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertStatus(409)->assertJsonPath('data.code', 'organization_version_conflict');

        $filesAfter = Storage::disk('works_private')->allFiles();
        $this->assertSame($filesBefore, $filesAfter);
    }

    public function test_delete_logo_with_old_version_fails(): void
    {
        $designer = $this->readyDesigner();
        $this->createOrganization($designer);
        Sanctum::actingAs($designer);

        $this->deleteJson('/api/designer/profile/organization/logo', [
            'expected_updated_at' => now()->subDay()->toJSON(),
        ])->assertStatus(409)->assertJsonPath('data.code', 'organization_version_conflict');
    }

    public function test_put_no_op_preserves_version_and_avoids_audit(): void
    {
        $designer = $this->readyDesigner();
        $org = $this->createOrganization($designer, [
            'show_publicly' => false,
        ]);
        $updatedAt = $org->updated_at;
        Sanctum::actingAs($designer);

        $this->putJson('/api/designer/profile/organization', $this->validPayload([
            'show_publicly' => false,
            'expected_updated_at' => $updatedAt->toJSON(),
        ]))->assertOk()->assertJsonPath('data.changed', false);

        $this->assertTrue($updatedAt->equalTo($org->fresh()->updated_at));
        $this->assertSame(0, AuditEvent::query()->where('event_type', 'designer.profile.organization.updated')->count());
    }

    public function test_delete_logo_no_op_preserves_version_and_avoids_audit(): void
    {
        $designer = $this->readyDesigner();
        $org = $this->createOrganization($designer, ['logo_path' => null]);
        $updatedAt = $org->updated_at;
        Sanctum::actingAs($designer);

        $this->deleteJson('/api/designer/profile/organization/logo', [
            'expected_updated_at' => $updatedAt->toJSON(),
        ])->assertOk()->assertJsonPath('data.changed', false);

        $this->assertTrue($updatedAt->equalTo($org->fresh()->updated_at));
        $this->assertSame(0, AuditEvent::query()->where('event_type', 'designer.profile.organization.logo_removed')->count());
    }

    public function test_real_update_changes_version_and_writes_safe_audit(): void
    {
        $designer = $this->readyDesigner();
        $org = $this->createOrganization($designer);
        $updatedAt = $org->updated_at;
        $profileUpdatedAt = $designer->designerProfile->updated_at;
        Sanctum::actingAs($designer);

        $this->putJson('/api/designer/profile/organization', $this->validPayload([
            'organization_name' => 'New Name',
            'description' => 'A wonderful studio.',
            'website_url' => 'https://example.com',
            'expected_updated_at' => $updatedAt->toJSON(),
        ]))->assertOk()->assertJsonPath('data.changed', true);

        $org->refresh();
        $this->assertFalse($updatedAt->equalTo($org->updated_at));
        $this->assertTrue($profileUpdatedAt->equalTo($designer->designerProfile->fresh()->updated_at));

        $audit = AuditEvent::query()->where('event_type', 'designer.profile.organization.updated')->firstOrFail();
        $metadataJson = json_encode($audit->metadata);

        $this->assertStringNotContainsString('New Name', $metadataJson);
        $this->assertStringNotContainsString('A wonderful studio.', $metadataJson);
        $this->assertStringNotContainsString('https://example.com', $metadataJson);
        $this->assertStringNotContainsString('logo_path', $metadataJson);
    }

    public function test_create_and_update_api_tokens_are_reusable_without_refresh(): void
    {
        $designer = $this->readyDesigner();
        Sanctum::actingAs($designer);

        $response1 = $this->putJson('/api/designer/profile/organization', $this->validPayload([
            'expected_updated_at' => null,
        ]))->assertOk();

        $token1 = $response1->json('data.updated_at');

        $response2 = $this->putJson('/api/designer/profile/organization', $this->validPayload([
            'organization_name' => 'Second Name',
            'expected_updated_at' => $token1,
        ]))->assertOk()->assertJsonPath('data.changed', true);

        $token2 = $response2->json('data.updated_at');
        $this->assertNotSame($token1, $token2);

        $response3 = $this->putJson('/api/designer/profile/organization', $this->validPayload([
            'organization_name' => 'Second Name',
            'expected_updated_at' => $token2,
        ]));

        $response3->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.updated_at', $token2);
    }

    public function test_public_profile_hides_organization_when_show_publicly_is_false(): void
    {
        $designer = $this->readyDesigner([], true);
        $this->createOrganization($designer, ['show_publicly' => false]);

        Sanctum::actingAs($designer);
        $this->getJson('/api/designer/profile/organization')->assertOk()->assertJsonPath('data.organization.show_publicly', false);

        $json = $this->getJson("/api/designers/{$designer->username}")
            ->assertOk()
            ->json('data.profile.organization');

        $this->assertSame(['visible' => false], $json);
    }

    public function test_public_profile_hides_organization_when_absent(): void
    {
        $designer = $this->readyDesigner([], true);

        $json = $this->getJson("/api/designers/{$designer->username}")
            ->assertOk()
            ->json('data.profile.organization');

        $this->assertSame(['visible' => false], $json);
    }

    public function test_public_profile_shows_organization_when_show_publicly_is_true(): void
    {
        $designer = $this->readyDesigner([], true);
        $this->createOrganization($designer, [
            'organization_name' => 'Test Organization',
            'organization_type' => 'studio',
            'show_publicly' => true,
        ]);

        $this->getJson("/api/designers/{$designer->username}")
            ->assertOk()
            ->assertJsonPath('data.profile.organization.visible', true)
            ->assertJsonPath('data.profile.organization.name', 'Test Organization')
            ->assertJsonPath('data.profile.organization.type', 'studio')
            ->assertJsonPath('data.profile.organization.description', 'A wonderful studio.')
            ->assertJsonPath('data.profile.organization.website_url', 'https://example.com')
            ->assertJsonPath('data.profile.organization.logo_url', null);
    }

    public function test_logo_validation_rejects_svg_and_large_files(): void
    {
        Storage::fake('works_private');
        $designer = $this->readyDesigner();
        $org = $this->createOrganization($designer);
        Sanctum::actingAs($designer);

        $this->postJson('/api/designer/profile/organization/logo', [
            'expected_updated_at' => $org->updated_at->toJSON(),
            'logo' => UploadedFile::fake()->create('logo.svg', 100, 'image/svg+xml'),
        ])->assertUnprocessable();

        $this->postJson('/api/designer/profile/organization/logo', [
            'expected_updated_at' => $org->updated_at->toJSON(),
            'logo' => UploadedFile::fake()->image('logo.png')->size(3000),
        ])->assertUnprocessable();

        $this->postJson('/api/designer/profile/organization/logo', [
            'expected_updated_at' => $org->updated_at->toJSON(),
            'logo' => UploadedFile::fake()->image('logo.png')->size(1000),
        ])->assertOk();
    }

    public function test_logo_lifecycle_uploads_replaces_and_removes(): void
    {
        Storage::fake('works_private');
        $designer = $this->readyDesigner();
        $org = $this->createOrganization($designer);
        Sanctum::actingAs($designer);

        $response1 = $this->postJson('/api/designer/profile/organization/logo', [
            'expected_updated_at' => $org->updated_at->toJSON(),
            'logo' => UploadedFile::fake()->image('logo1.png'),
        ])->assertOk();
        $token1 = $response1->json('data.updated_at');

        $org->refresh();
        $this->assertNotNull($org->logo_path);
        Storage::disk('works_private')->assertExists($org->logo_path);
        $this->assertSame(1, AuditEvent::query()->where('event_type', 'designer.profile.organization.logo_uploaded')->count());

        $this->getJson('/api/designer/profile/organization')
            ->assertOk()
            ->assertJsonPath('data.organization.has_logo', true)
            ->assertJsonMissingPath('data.organization.logo_path');

        $oldPath = $org->logo_path;
        $response2 = $this->postJson('/api/designer/profile/organization/logo', [
            'expected_updated_at' => $token1,
            'logo' => UploadedFile::fake()->image('logo2.png'),
        ])->assertOk();
        $token2 = $response2->json('data.updated_at');

        $org->refresh();
        $currentPath = $org->logo_path;
        $this->assertNotSame($oldPath, $currentPath);
        Storage::disk('works_private')->assertExists($currentPath);
        Storage::disk('works_private')->assertMissing($oldPath);

        $this->deleteJson('/api/designer/profile/organization/logo', [
            'expected_updated_at' => $token2,
        ])->assertOk();

        $org->refresh();
        $this->assertNull($org->logo_path);
        Storage::disk('works_private')->assertMissing($currentPath);
        $this->assertSame(1, AuditEvent::query()->where('event_type', 'designer.profile.organization.logo_removed')->count());
    }

    public function test_delete_organization_removes_row_and_logo(): void
    {
        Storage::fake('works_private');
        $designer = $this->readyDesigner();
        $org = $this->createOrganization($designer, ['logo_path' => 'orgs/fake.png']);
        Storage::disk('works_private')->put('orgs/fake.png', 'content');
        Sanctum::actingAs($designer);

        $this->deleteJson('/api/designer/profile/organization', [
            'expected_updated_at' => $org->updated_at->toJSON(),
        ])->assertOk();

        $this->assertDatabaseMissing('designer_profile_organizations', ['designer_profile_id' => $designer->designerProfile->id]);
        Storage::disk('works_private')->assertMissing('orgs/fake.png');
        $this->assertSame(1, AuditEvent::query()->where('event_type', 'designer.profile.organization.deleted')->count());
    }

    public function test_public_logo_route_serves_image_when_visible_and_published(): void
    {
        Storage::fake('works_private');
        $designer = $this->readyDesigner([], true);
        $this->createOrganization($designer, [
            'logo_path' => 'orgs/logo.png',
            'show_publicly' => true
        ]);

        $image = UploadedFile::fake()->image('logo.png');
        Storage::disk('works_private')->putFileAs('orgs', $image, 'logo.png');

        $response = $this->getJson("/api/designers/{$designer->username}/organization/logo")->assertOk();
        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));
    }

    public function test_public_logo_route_returns_404_when_hidden_absent_or_no_logo(): void
    {
        Storage::fake('works_private');

        $designer1 = $this->readyDesigner([], true);
        $this->createOrganization($designer1, [
            'logo_path' => 'orgs/1.png',
            'show_publicly' => false
        ]);
        $image1 = UploadedFile::fake()->image('1.png');
        Storage::disk('works_private')->putFileAs('orgs', $image1, '1.png');

        $designer2 = $this->readyDesigner([], true);

        $designer3 = $this->readyDesigner([], true);
        $this->createOrganization($designer3, [
            'logo_path' => null,
            'show_publicly' => true
        ]);

        $this->getJson("/api/designers/{$designer1->username}/organization/logo")->assertNotFound();
        $this->getJson("/api/designers/{$designer2->username}/organization/logo")->assertNotFound();
        $this->getJson("/api/designers/{$designer3->username}/organization/logo")->assertNotFound();
    }

    public function test_absence_of_organization_does_not_affect_profile_readiness(): void
    {
        $designer = $this->readyDesigner();
        Sanctum::actingAs($designer);

        $this->getJson('/api/designer/profile/publication')
            ->assertOk()
            ->assertJsonPath('data.readiness.ready', true)
            ->assertJsonPath('data.readiness.blockers', [])
            ->assertJsonPath('data.actions.can_publish', true);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);
        return $user;
    }

    private function readyDesigner(array $userAttributes = [], bool $published = false): User
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

        if ($published) {
            $profile->forceFill([
                'publication_status' => DesignerProfile::PUBLICATION_PUBLISHED,
                'published_at' => now()->subHour(),
                'hidden_at' => null,
            ])->save();
        }

        return $designer;
    }

    private function createOrganization(User $designer, array $overrides = []): DesignerProfileOrganization
    {
        $organization = $designer->designerProfile->organization()->create($this->organizationAttributes($overrides));
        return $organization->fresh();
    }

    private function organizationAttributes(array $overrides = []): array
    {
        return array_merge([
            'organization_name' => 'Test Organization',
            'organization_type' => 'studio',
            'description' => 'A wonderful studio.',
            'website_url' => 'https://example.com',
            'show_publicly' => true,
        ], $overrides);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'organization_name' => 'Test Organization',
            'organization_type' => 'studio',
            'description' => 'A wonderful studio.',
            'website_url' => 'https://example.com',
            'show_publicly' => true,
            'expected_updated_at' => null,
        ], $overrides);
    }
}
