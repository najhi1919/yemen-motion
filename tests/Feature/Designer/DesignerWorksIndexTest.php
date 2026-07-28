<?php

namespace Tests\Feature\Designer;

use App\Models\User;
use App\Models\Work;
use App\Models\WorkMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DesignerWorksIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $designer;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disk = (string) config('filesystems.default');
        Storage::fake($this->disk);
        $this->designer = $this->userWithRole('designer');
    }

    public function test_guest_cannot_access_index(): void
    {
        $this->getJson('/api/designer/works')->assertUnauthorized();
    }

    public function test_client_cannot_access_index(): void
    {
        Sanctum::actingAs($this->userWithRole('client'));
        $this->getJson('/api/designer/works')->assertForbidden();
    }

    public function test_staff_without_designer_role_cannot_access_index(): void
    {
        Sanctum::actingAs($this->userWithRole('staff'));
        $this->getJson('/api/designer/works')->assertForbidden();
    }

    public function test_disabled_designer_cannot_access_index(): void
    {
        $user = $this->userWithRole('designer');
        $user->forceFill(['disabled_at' => now()])->save();
        Sanctum::actingAs($user);
        $this->getJson('/api/designer/works')->assertForbidden();
    }

    public function test_designer_sees_only_owned_works(): void
    {
        $owned = $this->work($this->designer, Work::STATUS_DRAFT);
        $this->work($this->userWithRole('designer'), Work::STATUS_DRAFT);
        Sanctum::actingAs($this->designer);
        $this->getJson('/api/designer/works')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $owned->id);
    }

    public function test_multi_role_designer_still_sees_only_owned_works(): void
    {
        $this->attachRole($this->designer, 'super_admin');
        $owned = $this->work($this->designer, Work::STATUS_DRAFT);
        $this->work($this->userWithRole('designer'), Work::STATUS_DRAFT);
        Sanctum::actingAs($this->designer);
        $this->getJson('/api/designer/works')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $owned->id);
    }

    public function test_search_is_limited_to_owned_works(): void
    {
        $owned = $this->work($this->designer, Work::STATUS_DRAFT, ['title' => 'هوية يمنية']);
        $this->work($this->userWithRole('designer'), Work::STATUS_DRAFT, ['title' => 'هوية يمنية']);
        Sanctum::actingAs($this->designer);
        $this->getJson('/api/designer/works?q=هوية')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $owned->id);
    }

    public function test_draft_group_returns_draft_only(): void
    {
        $this->assertGroup('draft', [Work::STATUS_DRAFT], Work::STATUS_PUBLISHED);
    }

    public function test_review_group_returns_submitted_in_review_and_approved(): void
    {
        $this->assertGroup('review', [
            Work::STATUS_SUBMITTED,
            Work::STATUS_IN_REVIEW,
            Work::STATUS_APPROVED,
        ], Work::STATUS_DRAFT);
    }

    public function test_changes_group_returns_changes_requested(): void
    {
        $this->assertGroup('changes', [Work::STATUS_CHANGES_REQUESTED], Work::STATUS_DRAFT);
    }

    public function test_published_group_returns_published(): void
    {
        $this->assertGroup('published', [Work::STATUS_PUBLISHED], Work::STATUS_DRAFT);
    }

    public function test_closed_group_returns_rejected_hidden_and_archived(): void
    {
        $this->assertGroup('closed', [
            Work::STATUS_REJECTED,
            Work::STATUS_HIDDEN,
            Work::STATUS_ARCHIVED,
        ], Work::STATUS_DRAFT);
    }

    public function test_summary_counts_only_owned_works(): void
    {
        $this->work($this->designer, Work::STATUS_DRAFT);
        $this->work($this->designer, Work::STATUS_PUBLISHED);
        $this->work($this->userWithRole('designer'), Work::STATUS_DRAFT);
        Sanctum::actingAs($this->designer);
        $this->getJson('/api/designer/works')->assertJson([
            'summary' => ['total' => 2, 'draft' => 1, 'published' => 1],
        ]);
    }

    public function test_summary_is_not_reduced_by_search_or_group(): void
    {
        $this->work($this->designer, Work::STATUS_DRAFT, ['title' => 'مطابق']);
        $this->work($this->designer, Work::STATUS_PUBLISHED, ['title' => 'آخر']);
        Sanctum::actingAs($this->designer);
        $this->getJson('/api/designer/works?q=مطابق&group=draft')
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.published', 1);
    }

    public function test_sorting_and_pagination_work(): void
    {
        Work::factory()->count(13)->create(['designer_id' => $this->designer->id]);
        Sanctum::actingAs($this->designer);
        $this->getJson('/api/designer/works?sort=title&direction=asc&per_page=12&page=2')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.total', 13);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        Sanctum::actingAs($this->designer);
        $this->getJson('/api/designer/works?group=nope&sort=id&direction=nope&per_page=99')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['group', 'sort', 'direction', 'per_page']);
    }

    public function test_resource_excludes_private_and_admin_fields(): void
    {
        $this->work($this->designer, Work::STATUS_DRAFT);
        Sanctum::actingAs($this->designer);
        $this->getJson('/api/designer/works')
            ->assertOk()
            ->assertJsonMissingPath('data.0.designer_id')
            ->assertJsonMissingPath('data.0.reviewer_id')
            ->assertJsonMissingPath('data.0.internal_notes')
            ->assertJsonMissingPath('data.0.price_amount')
            ->assertJsonMissingPath('data.0.delivery_days');
    }

    public function test_cover_metadata_uses_explicit_cover_media_id_only(): void
    {
        $work = $this->work($this->designer, Work::STATUS_DRAFT);
        $firstMedia = WorkMedia::factory()->create(['work_id' => $work->id]);
        Sanctum::actingAs($this->designer);

        $this->getJson('/api/designer/works')->assertJsonPath('data.0.cover_media', null);

        $secondMedia = WorkMedia::factory()->create(['work_id' => $work->id]);
        $work->forceFill(['cover_media_id' => $secondMedia->id])->save();

        $response = $this->getJson('/api/designer/works')
            ->assertOk()
            ->assertJsonPath('data.0.cover_media.id', $secondMedia->id)
            ->assertJsonPath(
                'data.0.cover_media.content_url',
                "/designer/works/{$work->id}/media/{$secondMedia->id}/content",
            );

        $this->assertNotSame($firstMedia->id, $response->json('data.0.cover_media.id'));
        $contentUrl = $response->json('data.0.cover_media.content_url');
        $this->assertFalse(str_starts_with($contentUrl, 'http://'));
        $this->assertFalse(str_starts_with($contentUrl, 'https://'));
        $this->assertFalse(str_starts_with($contentUrl, '/api/'));
    }

    public function test_own_image_content_is_available(): void
    {
        [$work, $media] = $this->storedMedia($this->designer, WorkMedia::KIND_IMAGE, 'works/image.jpg');
        Sanctum::actingAs($this->designer);
        $this->get("/api/designer/works/{$work->id}/media/{$media->id}/content")->assertOk();
    }

    public function test_own_video_poster_is_available(): void
    {
        [$work, $media] = $this->storedMedia(
            $this->designer,
            WorkMedia::KIND_VIDEO,
            'works/video.mp4',
            'works/poster.jpg',
        );
        $work->forceFill(['cover_media_id' => $media->id])->save();
        Sanctum::actingAs($this->designer);
        $this->get("/api/designer/works/{$work->id}/media/{$media->id}/poster")->assertOk();
        $this->getJson('/api/designer/works')
            ->assertOk()
            ->assertJsonPath(
                'data.0.cover_media.poster_url',
                "/designer/works/{$work->id}/media/{$media->id}/poster",
            );
    }

    public function test_another_designer_work_media_returns_404(): void
    {
        [$work, $media] = $this->storedMedia(
            $this->userWithRole('designer'),
            WorkMedia::KIND_IMAGE,
            'works/foreign.jpg',
        );
        Sanctum::actingAs($this->designer);
        $this->get("/api/designer/works/{$work->id}/media/{$media->id}/content")->assertNotFound();
    }

    public function test_media_belonging_to_another_work_returns_404(): void
    {
        [$work] = $this->storedMedia($this->designer, WorkMedia::KIND_IMAGE, 'works/one.jpg');
        [, $otherMedia] = $this->storedMedia($this->designer, WorkMedia::KIND_IMAGE, 'works/two.jpg');
        Sanctum::actingAs($this->designer);
        $this->get("/api/designer/works/{$work->id}/media/{$otherMedia->id}/content")->assertNotFound();
    }

    public function test_client_cannot_access_media_routes(): void
    {
        [$work, $media] = $this->storedMedia($this->designer, WorkMedia::KIND_IMAGE, 'works/image.jpg');
        Sanctum::actingAs($this->userWithRole('client'));
        $this->get("/api/designer/works/{$work->id}/media/{$media->id}/content")->assertForbidden();
    }

    public function test_disabled_designer_cannot_access_media_routes(): void
    {
        $user = $this->userWithRole('designer');
        $user->forceFill(['disabled_at' => now()])->save();
        [$work, $media] = $this->storedMedia($user, WorkMedia::KIND_IMAGE, 'works/image.jpg');
        Sanctum::actingAs($user);
        $this->get("/api/designer/works/{$work->id}/media/{$media->id}/content")->assertForbidden();
    }

    public function test_designer_index_and_media_routes_remain_read_only(): void
    {
        foreach ([
            'designer.works.index',
            'designer.works.media.content',
            'designer.works.media.poster',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route);
            $this->assertSame(['GET', 'HEAD'], $route->methods());
        }
    }

    public function test_designer_remains_denied_from_admin_works_routes(): void
    {
        Sanctum::actingAs($this->designer);
        $this->getJson('/api/admin/works')->assertForbidden();
    }

    private function assertGroup(string $group, array $included, string $excluded): void
    {
        foreach ($included as $status) {
            $this->work($this->designer, $status);
        }
        $this->work($this->designer, $excluded);
        Sanctum::actingAs($this->designer);
        $response = $this->getJson("/api/designer/works?group={$group}")
            ->assertOk()
            ->assertJsonCount(count($included), 'data');
        $this->assertEqualsCanonicalizing($included, $response->json('data.*.status'));
    }

    private function work(User $designer, string $status, array $attributes = []): Work
    {
        return Work::factory()->create(array_merge([
            'designer_id' => $designer->id,
            'status' => $status,
        ], $attributes));
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $this->attachRole($user, $roleName);

        return $user;
    }

    private function attachRole(User $user, string $roleName): void
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    private function storedMedia(User $designer, string $kind, string $path, ?string $poster = null): array
    {
        $work = $this->work($designer, Work::STATUS_DRAFT);
        Storage::disk($this->disk)->put($path, 'media');
        if ($poster) {
            Storage::disk($this->disk)->put($poster, 'poster');
        }
        $media = WorkMedia::factory()->create([
            'work_id' => $work->id,
            'kind' => $kind,
            'disk' => $this->disk,
            'path' => $path,
            'poster_path' => $poster,
            'processing_status' => WorkMedia::PROCESSING_READY,
        ]);

        return [$work, $media];
    }
}
