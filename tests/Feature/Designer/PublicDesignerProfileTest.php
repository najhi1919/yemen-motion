<?php

declare(strict_types=1);

namespace Tests\Feature\Designer;

use App\Http\Controllers\Api\PublicDesignerProfileController;
use App\Models\DesignerProfile;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkMedia;
use App\Models\WorkTag;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicDesignerProfileTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthRolesSeeder::class);
        Storage::fake('works_private');
    }

    public function test_public_routes_are_guest_readable_and_outside_sanctum(): void
    {
        [$designer] = $this->publishedDesigner();

        $route = Route::getRoutes()->getByName('public.designers.show');
        $this->assertSame(PublicDesignerProfileController::class, $route?->getActionName());
        $this->assertSame(['GET', 'HEAD'], $route?->methods());
        $this->assertNotContains('auth:sanctum', $route?->gatherMiddleware() ?? []);
        $this->assertNotContains('account.active', $route?->gatherMiddleware() ?? []);

        $this->getJson($this->profileEndpoint($designer))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profile.identity.username', $designer->username);
    }

    public function test_all_profile_gating_failures_return_the_same_public_404(): void
    {
        $responses = [];
        $responses[] = $this->getJson('/api/designers/does-not-exist');

        [$disabled] = $this->publishedDesigner();
        $disabled->forceFill(['disabled_at' => now()])->save();
        $responses[] = $this->getJson($this->profileEndpoint($disabled));

        [$notDesigner] = $this->publishedDesigner();
        $notDesigner->removeRole('designer');
        $responses[] = $this->getJson($this->profileEndpoint($notDesigner));

        [$draft, $draftProfile] = $this->publishedDesigner();
        $draftProfile->forceFill(['publication_status' => DesignerProfile::PUBLICATION_DRAFT])->save();
        $responses[] = $this->getJson($this->profileEndpoint($draft));

        [$hidden, $hiddenProfile] = $this->publishedDesigner();
        $hiddenProfile->forceFill(['publication_status' => DesignerProfile::PUBLICATION_HIDDEN])->save();
        $responses[] = $this->getJson($this->profileEndpoint($hidden));

        [$incomplete, $incompleteProfile] = $this->publishedDesigner();
        $incompleteProfile->skills()->delete();
        $responses[] = $this->getJson($this->profileEndpoint($incomplete));

        foreach ($responses as $response) {
            $response
                ->assertNotFound()
                ->assertExactJson([
                    'success' => false,
                    'message' => 'ملف المصمم غير متاح.',
                    'errors' => null,
                ]);
        }
    }

    public function test_private_sections_hide_content_and_public_sections_preserve_content(): void
    {
        [$designer, $profile] = $this->publishedDesigner();
        $profile->forceFill([
            'show_availability_publicly' => false,
            'show_specialties_publicly' => true,
            'show_skills_publicly' => false,
            'show_tools_publicly' => true,
            'show_languages_publicly' => false,
            'show_experience_publicly' => true,
        ])->save();

        $response = $this->getJson($this->profileEndpoint($designer))
            ->assertOk()
            ->assertJsonPath('data.profile.professional.sections.availability.visible', false)
            ->assertJsonPath('data.profile.professional.sections.specialties.visible', true)
            ->assertJsonPath('data.profile.professional.sections.specialties.service.0.name', 'تصميم الشعارات')
            ->assertJsonPath('data.profile.professional.sections.skills.visible', false)
            ->assertJsonPath('data.profile.professional.sections.tools.visible', true)
            ->assertJsonPath('data.profile.professional.sections.languages.visible', false)
            ->assertJsonPath('data.profile.professional.sections.experience.visible', true)
            ->assertJsonPath('data.profile.professional.additional_information.professional_note', 'معلومات مهنية عامة.');

        $this->assertSame(
            ['visible' => false],
            $response->json('data.profile.professional.sections.availability'),
        );
        $this->assertSame(
            ['visible' => false],
            $response->json('data.profile.professional.sections.skills'),
        );
        $this->assertSame(
            ['visible' => false],
            $response->json('data.profile.professional.sections.languages'),
        );
        $this->assertArrayNotHasKey(
            'occasion',
            $response->json('data.profile.professional.sections.specialties'),
        );
    }

    public function test_public_response_does_not_leak_ids_email_paths_or_internal_state(): void
    {
        [$designer, $profile] = $this->publishedDesigner();
        $profile->forceFill(['cover_path' => 'private/profile/cover-secret.jpg'])->save();
        $work = $this->publicWork($designer, ['description' => 'وصف داخلي طويل لا يعاد.']);
        $this->readyCover($work, $designer);

        $response = $this->getJson($this->profileEndpoint($designer))->assertOk();
        $json = json_encode($response->json(), JSON_THROW_ON_ERROR);

        foreach ([
            $designer->email,
            'private/profile/avatar-secret.jpg',
            'private/profile/cover-secret.jpg',
            'designer_id',
            'reviewer_id',
            'cover_media_id',
            'publication_status',
            'visibility_status',
            'normalized_name',
            'is_featured',
            'is_pinned',
            'archive_state',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }

        $this->assertNotNull($response->json('data.profile.seo.description'));
        foreach ($response->json('data.profile.works.items') as $item) {
            $this->assertArrayNotHasKey('description', $item);
        }
    }

    public function test_only_published_public_works_are_returned(): void
    {
        [$designer] = $this->publishedDesigner();
        $public = $this->publicWork($designer, ['title' => 'عمل عام']);
        Work::factory()->published()->create([
            'designer_id' => $designer->id,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'title' => 'منشور مخفي',
        ]);
        Work::factory()->hidden()->create(['designer_id' => $designer->id, 'title' => 'عمل مخفي']);
        Work::factory()->archived()->create(['designer_id' => $designer->id, 'title' => 'عمل مؤرشف']);
        Work::factory()->create(['designer_id' => $designer->id, 'title' => 'مسودة']);

        $this->getJson($this->profileEndpoint($designer))
            ->assertOk()
            ->assertJsonPath('data.profile.works.total', 1)
            ->assertJsonPath('data.profile.works.items.0.public_code', $public->public_code)
            ->assertJsonMissing(['title' => 'منشور مخفي'])
            ->assertJsonMissing(['title' => 'عمل مخفي'])
            ->assertJsonMissing(['title' => 'عمل مؤرشف'])
            ->assertJsonMissing(['title' => 'مسودة']);
    }

    public function test_works_are_ordered_by_published_at_then_id_descending(): void
    {
        [$designer] = $this->publishedDesigner();
        $older = $this->publicWork($designer, ['published_at' => now()->subDays(2)]);
        $tieFirst = $this->publicWork($designer, ['published_at' => now()->subDay()]);
        $tieSecond = $this->publicWork($designer, ['published_at' => $tieFirst->published_at]);

        $response = $this->getJson($this->profileEndpoint($designer))->assertOk();

        $this->assertSame([
            $tieSecond->public_code,
            $tieFirst->public_code,
            $older->public_code,
        ], collect($response->json('data.profile.works.items'))->pluck('public_code')->all());
    }

    public function test_public_work_contract_contains_only_safe_grid_fields(): void
    {
        [$designer] = $this->publishedDesigner();
        $category = WorkCategory::factory()->create();
        $tag = WorkTag::factory()->create();
        $work = $this->publicWork($designer, [
            'category_id' => $category->id,
            'cover_display_mode' => Work::COVER_DISPLAY_MODE_FIT,
            'cover_focal_x' => 25,
            'cover_focal_y' => 75,
        ]);
        $work->tags()->attach($tag);
        $cover = $this->readyCover($work, $designer);

        $response = $this->getJson($this->profileEndpoint($designer))->assertOk();
        $item = $response->json('data.profile.works.items.0');

        $this->assertSame([
            'public_code', 'slug', 'title', 'summary', 'media_type', 'published_at',
            'category', 'tags', 'cover_presentation', 'cover_media',
        ], array_keys($item));
        $this->assertSame(['name_ar', 'name_en', 'slug'], array_keys($item['category']));
        $this->assertSame(['name_ar', 'name_en', 'slug'], array_keys($item['tags'][0]));
        $this->assertSame(['kind', 'width', 'height', 'duration_ms', 'content_url', 'poster_url'], array_keys($item['cover_media']));
        $this->assertStringContainsString("/works/{$work->public_code}/media/{$cover->id}/content", $item['cover_media']['content_url']);
    }

    public function test_avatar_and_cover_use_public_routes_and_require_public_profile(): void
    {
        [$designer, $profile] = $this->publishedDesigner();
        $profile->forceFill(['cover_path' => 'private/profile/cover.jpg'])->save();
        Storage::disk('works_private')->put($profile->avatar_path, 'avatar-content');
        Storage::disk('works_private')->put($profile->cover_path, 'cover-content');

        $response = $this->getJson($this->profileEndpoint($designer))->assertOk();
        $this->assertStringContainsString("/api/designers/{$designer->username}/avatar?v=", $response->json('data.profile.identity.avatar_url'));
        $this->assertStringContainsString("/api/designers/{$designer->username}/cover?v=", $response->json('data.profile.identity.cover_url'));

        $avatarResponse = $this->get("/api/designers/{$designer->username}/avatar")
            ->assertOk();
        $this->assertPublicMediaHeaders($avatarResponse);
        $this->get("/api/designers/{$designer->username}/cover")->assertOk();

        $profile->forceFill(['publication_status' => DesignerProfile::PUBLICATION_HIDDEN])->save();
        $this->get("/api/designers/{$designer->username}/avatar")->assertNotFound();
        $this->get("/api/designers/{$designer->username}/cover")->assertNotFound();
    }

    public function test_work_content_requires_public_work_ready_owned_selected_cover(): void
    {
        [$designer] = $this->publishedDesigner();
        $work = $this->publicWork($designer);
        $cover = $this->readyCover($work, $designer);
        Storage::disk('works_private')->put($cover->path, 'cover-content');
        $endpoint = $this->workContentEndpoint($designer, $work, $cover);

        $contentResponse = $this->get($endpoint)->assertOk();
        $this->assertPublicMediaHeaders($contentResponse);

        $unselected = WorkMedia::factory()->image()->ready()->create([
            'work_id' => $work->id,
            'uploaded_by' => $designer->id,
        ]);
        $this->get($this->workContentEndpoint($designer, $work, $unselected))->assertNotFound();

        $cover->forceFill(['processing_status' => WorkMedia::PROCESSING_PENDING])->save();
        $this->get($endpoint)->assertNotFound();
        $cover->forceFill(['processing_status' => WorkMedia::PROCESSING_READY])->save();

        $work->forceFill(['visibility_status' => Work::VISIBILITY_HIDDEN])->save();
        $this->get($endpoint)->assertNotFound();
    }

    public function test_work_media_cannot_cross_work_or_designer_boundaries(): void
    {
        [$designer] = $this->publishedDesigner();
        $work = $this->publicWork($designer);
        $cover = $this->readyCover($work, $designer);
        $otherWork = $this->publicWork($designer);
        $otherCover = $this->readyCover($otherWork, $designer);

        $this->get($this->workContentEndpoint($designer, $work, $otherCover))->assertNotFound();

        [$otherDesigner] = $this->publishedDesigner();
        $this->get($this->workContentEndpoint($otherDesigner, $work, $cover))->assertNotFound();
    }

    public function test_poster_requires_ready_video_cover_with_existing_poster(): void
    {
        [$designer] = $this->publishedDesigner();
        $imageWork = $this->publicWork($designer);
        $image = $this->readyCover($imageWork, $designer);
        $this->get($this->workPosterEndpoint($designer, $imageWork, $image))->assertNotFound();

        $videoWork = $this->publicWork($designer, ['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $video = WorkMedia::factory()->video()->ready()->create([
            'work_id' => $videoWork->id,
            'uploaded_by' => $designer->id,
            'poster_path' => null,
        ]);
        $videoWork->forceFill(['cover_media_id' => $video->id])->save();
        $this->get($this->workPosterEndpoint($designer, $videoWork, $video))->assertNotFound();

        $video->forceFill(['poster_path' => 'works/posters/video.jpg'])->save();
        Storage::disk('works_private')->put($video->poster_path, 'poster-content');
        $this->get($this->workPosterEndpoint($designer, $videoWork, $video))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_seo_contract_uses_canonical_public_path_and_public_image(): void
    {
        [$designer, $profile] = $this->publishedDesigner();
        $profile->forceFill(['cover_path' => 'private/profile/cover.jpg'])->save();

        $this->getJson($this->profileEndpoint($designer))
            ->assertOk()
            ->assertJsonPath('data.profile.seo.canonical_path', "/designers/{$designer->username}")
            ->assertJsonPath('data.profile.seo.type', 'profile')
            ->assertJsonPath('data.profile.seo.description', 'نبذة عامة مكتملة للمصمم.')
            ->assertJsonPath('data.profile.seo.image_url', fn ($value) => str_contains($value, "/api/designers/{$designer->username}/cover?v="));
    }

    /** @return array{User, DesignerProfile} */
    private function publishedDesigner(): array
    {
        $designer = User::factory()->create([
            'username' => 'public-designer-'.(++$this->sequence),
        ]);
        $designer->assignRole('designer');
        $profile = $designer->designerProfile()->create([
            'display_name' => 'مصمم عام',
            'professional_title' => 'مصمم جرافيك',
            'primary_specialty' => 'الهوية البصرية',
            'bio' => 'نبذة عامة مكتملة للمصمم.',
            'avatar_path' => 'private/profile/avatar-secret.jpg',
            'availability' => DesignerProfile::AVAILABILITY_AVAILABLE,
            'years_of_experience' => 7,
            'professional_note' => 'معلومات مهنية عامة.',
        ]);
        $profile->forceFill([
            'publication_status' => DesignerProfile::PUBLICATION_PUBLISHED,
            'published_at' => now()->subHour(),
        ])->save();
        $profile->specialties()->create([
            'kind' => 'service',
            'name' => 'تصميم الشعارات',
            'normalized_name' => 'تصميم الشعارات',
            'sort_order' => 0,
        ]);
        $profile->skills()->create([
            'name' => 'تصميم الشعارات',
            'normalized_name' => 'تصميم الشعارات',
            'level' => 'expert',
            'sort_order' => 0,
        ]);
        $profile->tools()->create([
            'name' => 'Adobe Photoshop',
            'normalized_name' => 'adobe photoshop',
            'level' => 'advanced',
            'sort_order' => 0,
        ]);
        $profile->languages()->create([
            'name' => 'العربية',
            'normalized_name' => 'العربية',
            'level' => 'native',
            'sort_order' => 0,
        ]);

        return [$designer->fresh(), $profile->fresh()];
    }

    /** @param array<string, mixed> $attributes */
    private function publicWork(User $designer, array $attributes = []): Work
    {
        return Work::factory()->published()->create([
            'designer_id' => $designer->id,
            'title' => 'عمل عام '.($this->sequence++),
            'summary' => 'ملخص عام للعمل.',
            ...$attributes,
        ]);
    }

    private function readyCover(Work $work, User $designer): WorkMedia
    {
        $cover = WorkMedia::factory()->image()->ready()->create([
            'work_id' => $work->id,
            'uploaded_by' => $designer->id,
        ]);
        $work->forceFill(['cover_media_id' => $cover->id])->save();

        return $cover;
    }

    private function profileEndpoint(User $designer): string
    {
        return "/api/designers/{$designer->username}";
    }

    private function workContentEndpoint(User $designer, Work $work, WorkMedia $media): string
    {
        return "/api/designers/{$designer->username}/works/{$work->public_code}/media/{$media->id}/content";
    }

    private function workPosterEndpoint(User $designer, Work $work, WorkMedia $media): string
    {
        return "/api/designers/{$designer->username}/works/{$work->public_code}/media/{$media->id}/poster";
    }

    private function assertPublicMediaHeaders(TestResponse $response): void
    {
        $cacheDirectives = array_map(
            static fn (string $directive): string => trim($directive),
            explode(',', (string) $response->headers->get('cache-control')),
        );

        $this->assertContains('public', $cacheDirectives);
        $this->assertContains('max-age=3600', $cacheDirectives);
        $response->assertHeader('x-content-type-options', 'nosniff');
    }
}
