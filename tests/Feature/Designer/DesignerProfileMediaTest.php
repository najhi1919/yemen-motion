<?php

namespace Tests\Feature\Designer;

use App\Models\DesignerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DesignerProfileMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('works_private');
        Role::findOrCreate('designer');
        Role::findOrCreate('client');
    }

    public function test_guest_is_denied(): void
    {
        $this->postJson('/api/designer/profile/avatar')
            ->assertUnauthorized();
    }

    public function test_client_is_denied(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');
        Sanctum::actingAs($client);

        $this->postJson('/api/designer/profile/avatar')
            ->assertForbidden();
    }

    public function test_disabled_designer_is_denied(): void
    {
        [$designer] = $this->designerWithProfile();
        $designer->forceFill(['disabled_at' => now()])->save();
        Sanctum::actingAs($designer);

        $this->deleteJson('/api/designer/profile/avatar')
            ->assertForbidden();
    }

    public function test_designer_without_profile_cannot_upload_media(): void
    {
        $designer = User::factory()->create();
        $designer->assignRole('designer');
        Sanctum::actingAs($designer);

        $this->post('/api/designer/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 512, 512),
        ])->assertNotFound();
    }

    public function test_valid_avatar_upload(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        Sanctum::actingAs($designer);

        $response = $this->post('/api/designer/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 512, 512),
        ])->assertOk()
            ->assertJsonPath('data.profile.identity_media.cover_focal_point.x', 50);

        $path = $profile->refresh()->avatar_path;

        Storage::disk('works_private')->assertExists($path);
        $response->assertJsonPath(
            'data.profile.identity_media.avatar_url',
            url('/api/designer/profile/avatar/content').'?v='.$profile->updated_at->timestamp
        );
    }

    public function test_valid_cover_upload(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        Sanctum::actingAs($designer);

        $this->post('/api/designer/profile/cover', [
            'cover' => UploadedFile::fake()->image('cover.webp', 1200, 400),
        ])->assertOk()
            ->assertJsonPath('data.profile.identity_media.cover_focal_point.x', 50)
            ->assertJsonPath('data.profile.identity_media.cover_focal_point.y', 50);

        Storage::disk('works_private')->assertExists($profile->refresh()->cover_path);
    }

    public function test_unsupported_mime_is_rejected(): void
    {
        [$designer] = $this->designerWithProfile();
        Sanctum::actingAs($designer);

        $this->post('/api/designer/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.gif', 512, 512),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }

    public function test_oversized_avatar_is_rejected(): void
    {
        [$designer] = $this->designerWithProfile();
        Sanctum::actingAs($designer);

        $this->post('/api/designer/profile/avatar', [
            'avatar' => UploadedFile::fake()
                ->image('avatar.jpg', 512, 512)
                ->size(4097),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }

    public function test_undersized_avatar_is_rejected(): void
    {
        [$designer] = $this->designerWithProfile();
        Sanctum::actingAs($designer);

        $this->post('/api/designer/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.png', 255, 255),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }

    public function test_undersized_cover_is_rejected(): void
    {
        [$designer] = $this->designerWithProfile();
        Sanctum::actingAs($designer);

        $this->post('/api/designer/profile/cover', [
            'cover' => UploadedFile::fake()->image('cover.jpg', 799, 239),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('cover');
    }

    public function test_replacing_avatar_removes_old_file(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        $oldPath = 'designer-profiles/'.$designer->id.'/avatar/old.jpg';
        Storage::disk('works_private')->put($oldPath, 'old');
        $profile->update(['avatar_path' => $oldPath]);
        Sanctum::actingAs($designer);

        $this->post('/api/designer/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('new.jpg', 512, 512),
        ])->assertOk();

        Storage::disk('works_private')->assertMissing($oldPath);
        Storage::disk('works_private')->assertExists($profile->refresh()->avatar_path);
    }

    public function test_replacing_cover_removes_old_file(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        $oldPath = 'designer-profiles/'.$designer->id.'/cover/old.jpg';
        Storage::disk('works_private')->put($oldPath, 'old');
        $profile->update(['cover_path' => $oldPath]);
        Sanctum::actingAs($designer);

        $this->post('/api/designer/profile/cover', [
            'cover' => UploadedFile::fake()->image('new.jpg', 1200, 400),
        ])->assertOk();

        Storage::disk('works_private')->assertMissing($oldPath);
        Storage::disk('works_private')->assertExists($profile->refresh()->cover_path);
    }

    public function test_deleting_avatar_clears_field_and_storage(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        $path = 'designer-profiles/'.$designer->id.'/avatar/current.jpg';
        Storage::disk('works_private')->put($path, 'avatar');
        $profile->update(['avatar_path' => $path]);
        Sanctum::actingAs($designer);

        $this->deleteJson('/api/designer/profile/avatar')
            ->assertOk()
            ->assertJsonPath('data.profile.identity_media.avatar_url', null);

        $this->assertNull($profile->refresh()->avatar_path);
        Storage::disk('works_private')->assertMissing($path);
    }

    public function test_deleting_cover_clears_field_and_resets_focal_point(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        $path = 'designer-profiles/'.$designer->id.'/cover/current.jpg';
        Storage::disk('works_private')->put($path, 'cover');
        $profile->update([
            'cover_path' => $path,
            'cover_focal_x' => 10,
            'cover_focal_y' => 90,
        ]);
        Sanctum::actingAs($designer);

        $this->deleteJson('/api/designer/profile/cover')
            ->assertOk()
            ->assertJsonPath('data.profile.identity_media.cover_url', null)
            ->assertJsonPath('data.profile.identity_media.cover_focal_point.x', 50)
            ->assertJsonPath('data.profile.identity_media.cover_focal_point.y', 50);

        Storage::disk('works_private')->assertMissing($path);
    }

    public function test_focal_point_accepts_zero_and_one_hundred(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        $profile->update(['cover_path' => 'designer-profiles/cover.jpg']);
        Sanctum::actingAs($designer);

        $this->patchJson('/api/designer/profile/cover/focal-point', [
            'x' => 0,
            'y' => 100,
        ])->assertOk()
            ->assertJsonPath('data.profile.identity_media.cover_focal_point.x', 0)
            ->assertJsonPath('data.profile.identity_media.cover_focal_point.y', 100);
    }

    public function test_focal_point_rejects_values_outside_range(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        $profile->update(['cover_path' => 'designer-profiles/cover.jpg']);
        Sanctum::actingAs($designer);

        $this->patchJson('/api/designer/profile/cover/focal-point', [
            'x' => -1,
            'y' => 101,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['x', 'y']);
    }

    public function test_focal_point_requires_existing_cover(): void
    {
        [$designer] = $this->designerWithProfile();
        Sanctum::actingAs($designer);

        $this->patchJson('/api/designer/profile/cover/focal-point', [
            'x' => 25,
            'y' => 75,
        ])->assertUnprocessable();
    }

    public function test_designer_can_only_modify_own_profile(): void
    {
        [$first, $firstProfile] = $this->designerWithProfile();
        [, $secondProfile] = $this->designerWithProfile();
        Sanctum::actingAs($first);

        $this->post('/api/designer/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 512, 512),
        ])->assertOk();

        $this->assertNotNull($firstProfile->refresh()->avatar_path);
        $this->assertNull($secondProfile->refresh()->avatar_path);
    }

    public function test_resource_exposes_urls_but_not_raw_paths(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        $profile->update([
            'avatar_path' => 'designer-profiles/'.$designer->id.'/avatar/secret.jpg',
            'cover_path' => 'designer-profiles/'.$designer->id.'/cover/secret.jpg',
        ]);
        Sanctum::actingAs($designer);

        $response = $this->deleteJson('/api/designer/profile/avatar')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['profile' => ['identity_media' => [
                    'avatar_url',
                    'cover_url',
                    'cover_focal_point' => ['x', 'y'],
                ]]],
            ]);

        $payload = $response->getContent();
        $this->assertStringNotContainsString('avatar_path', $payload);
        $this->assertStringNotContainsString('cover_path', $payload);
        $this->assertStringNotContainsString('/cover/secret.jpg', $payload);
    }

    public function test_media_changes_keep_profile_as_draft(): void
    {
        [$designer, $profile] = $this->designerWithProfile();
        Sanctum::actingAs($designer);

        $this->post('/api/designer/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 512, 512),
        ])->assertOk()
            ->assertJsonPath('data.profile.publication_status', 'draft');

        $this->assertSame('draft', $profile->refresh()->publication_status);
    }

    public function test_media_content_route_is_not_public(): void
    {
        $this->getJson('/api/designer/profile/avatar/content')
            ->assertUnauthorized();
        $this->getJson('/api/designer/profile/cover/content')
            ->assertUnauthorized();
    }

    private function designerWithProfile(): array
    {
        $designer = User::factory()->create();
        $designer->assignRole('designer');
        $profile = $designer->designerProfile()->create([
            'display_name' => 'مصمم يمني',
        ]);

        return [$designer, $profile];
    }
}
