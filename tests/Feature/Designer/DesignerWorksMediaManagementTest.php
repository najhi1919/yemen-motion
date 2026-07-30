<?php

namespace Tests\Feature\Designer;

use App\Http\Controllers\Api\DesignerWorksMediaController;
use App\Jobs\ProcessWorkMedia;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkMedia;
use App\Models\WorkSetting;
use App\Services\Works\WorksVideoCoverImageGenerator;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class DesignerWorksMediaManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthRolesSeeder::class);
        Storage::fake('works_private');
        Queue::fake();
    }

    public function test_routes_resolve_and_read_routes_remain_present_without_put(): void
    {
        foreach ([
            ['GET', '/api/designer/works/1/media', 'index'],
            ['POST', '/api/designer/works/1/media', 'store'],
            ['PATCH', '/api/designer/works/1/media/order', 'reorder'],
            ['PATCH', '/api/designer/works/1/media/cover', 'updateCover'],
            ['DELETE', '/api/designer/works/1/media/2', 'destroy'],
            ['POST', '/api/designer/works/1/media/2/retry-processing', 'retryProcessing'],
            ['GET', '/api/designer/works/1/media/2/content', 'content'],
            ['GET', '/api/designer/works/1/media/2/poster', 'poster'],
            ['PATCH', '/api/designer/works/1/media/2/video-cover/current', 'useCurrentVideoCover'],
            ['PATCH', '/api/designer/works/1/media/2/video-cover/frame', 'selectVideoCoverFrame'],
            ['POST', '/api/designer/works/1/media/2/video-cover/upload', 'uploadVideoCover'],
        ] as [$method, $uri, $action]) {
            $route = Route::getRoutes()->match(Request::create($uri, $method));
            $this->assertSame(DesignerWorksMediaController::class.'@'.$action, $route->getActionName());
        }

        Sanctum::actingAs($this->designer());
        $this->putJson('/api/designer/works/1/media')->assertMethodNotAllowed();
    }

    public function test_guest_cannot_access_any_designer_media_route(): void
    {
        $this->getJson('/api/designer/works/1/media')->assertUnauthorized();
        $this->postJson('/api/designer/works/1/media')->assertUnauthorized();
        $this->patchJson('/api/designer/works/1/media/order', ['media_ids' => [1]])->assertUnauthorized();
        $this->patchJson('/api/designer/works/1/media/cover', ['cover_media_id' => null])->assertUnauthorized();
        $this->deleteJson('/api/designer/works/1/media/1')->assertUnauthorized();
        $this->postJson('/api/designer/works/1/media/1/retry-processing')->assertUnauthorized();
        $this->getJson('/api/designer/works/1/media/1/content')->assertUnauthorized();
        $this->getJson('/api/designer/works/1/media/1/poster')->assertUnauthorized();
        $this->patchJson('/api/designer/works/1/media/1/video-cover/current')->assertUnauthorized();
        $this->patchJson('/api/designer/works/1/media/1/video-cover/frame', ['time_ms' => 0])
            ->assertUnauthorized();
        $this->postJson('/api/designer/works/1/media/1/video-cover/upload')->assertUnauthorized();
    }

    public function test_client_staff_and_disabled_designer_cannot_access(): void
    {
        foreach (['client', 'staff'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            Sanctum::actingAs($user);
            $this->getJson('/api/designer/works/1/media')->assertForbidden();
            $this->postJson('/api/designer/works/1/media')->assertForbidden();
            $this->deleteJson('/api/designer/works/1/media/1')->assertForbidden();
            $this->patchJson('/api/designer/works/1/media/1/video-cover/current')->assertForbidden();
            $this->patchJson('/api/designer/works/1/media/1/video-cover/frame', ['time_ms' => 0])
                ->assertForbidden();
            $this->postJson('/api/designer/works/1/media/1/video-cover/upload')->assertForbidden();
        }

        $disabled = $this->designer(['disabled_at' => now()]);
        Sanctum::actingAs($disabled);
        $this->getJson('/api/designer/works/1/media')->assertForbidden();
        $this->postJson('/api/designer/works/1/media')->assertForbidden();
        $this->postJson('/api/designer/works/1/media/1/retry-processing')->assertForbidden();
        $this->patchJson('/api/designer/works/1/media/1/video-cover/current')->assertForbidden();
        $this->patchJson('/api/designer/works/1/media/1/video-cover/frame', ['time_ms' => 0])
            ->assertForbidden();
        $this->postJson('/api/designer/works/1/media/1/video-cover/upload')->assertForbidden();
    }

    public function test_designer_lists_only_owned_media_in_every_work_state(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);

        foreach ([
            Work::STATUS_DRAFT,
            Work::STATUS_SUBMITTED,
            Work::STATUS_IN_REVIEW,
            Work::STATUS_CHANGES_REQUESTED,
            Work::STATUS_APPROVED,
            Work::STATUS_PUBLISHED,
            Work::STATUS_REJECTED,
            Work::STATUS_HIDDEN,
            Work::STATUS_ARCHIVED,
        ] as $status) {
            $work = $this->work($designer, ['status' => $status]);
            $media = WorkMedia::factory()->create(['work_id' => $work->id]);
            $this->getJson($this->mediaUrl($work))
                ->assertOk()
                ->assertJsonPath('data.media.0.id', $media->id)
                ->assertJsonPath(
                    'data.media_state.editable',
                    in_array($status, [Work::STATUS_DRAFT, Work::STATUS_CHANGES_REQUESTED], true),
                );
        }

        $foreign = $this->work($this->designer());
        $this->getJson($this->mediaUrl($foreign))->assertNotFound();
    }

    public function test_multi_role_designer_remains_scoped_to_owned_work(): void
    {
        $designer = $this->designer();
        $designer->assignRole('super-admin');
        $own = $this->work($designer);
        $foreign = $this->work($this->designer());
        Sanctum::actingAs($designer);

        $this->getJson($this->mediaUrl($own))->assertOk();
        $this->getJson($this->mediaUrl($foreign))->assertNotFound();
    }

    public function test_index_is_exactly_allowlisted_ordered_and_uses_designer_urls(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_GALLERY]);
        WorkMedia::factory()->create(['work_id' => $work->id, 'position' => 2]);
        $first = WorkMedia::factory()->create(['work_id' => $work->id, 'position' => 1]);
        Sanctum::actingAs($designer);

        $response = $this->getJson($this->mediaUrl($work))->assertOk();
        $response->assertJsonPath('data.media.0.id', $first->id);
        $this->assertSame(
            ['id', 'status', 'media_type', 'cover_media_id'],
            array_keys($response->json('data.work')),
        );
        $this->assertSame([
            'id', 'kind', 'original_name', 'mime_type', 'extension', 'size_bytes',
            'position', 'width', 'height', 'duration_ms', 'processing_status',
            'processing_stage', 'processing_progress', 'processing_started_at',
            'processing_completed_at', 'processing_attempts', 'processing_message',
            'can_retry_processing', 'is_cover', 'created_at', 'updated_at',
            'content_url', 'poster_url',
        ], array_keys($response->json('data.media.0')));

        $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
        foreach (['"disk"', '"path"', 'poster_path', 'processing_error', 'uploaded_by', '/admin/', '/api/'] as $private) {
            $this->assertStringNotContainsString($private, $encoded);
        }
        $this->assertStringStartsWith('/designer/works/', $response->json('data.media.0.content_url'));
    }

    public function test_draft_and_changes_requested_allow_image_upload_with_safe_metadata(): void
    {
        foreach ([Work::STATUS_DRAFT, Work::STATUS_CHANGES_REQUESTED] as $status) {
            $designer = $this->designer();
            $work = $this->work($designer, ['status' => $status]);
            Sanctum::actingAs($designer);

            $response = $this->uploadImage($work)
                ->assertCreated()
                ->assertJsonPath('message', 'تم رفع وسيط العمل بنجاح.')
                ->assertJsonPath('data.media.kind', WorkMedia::KIND_IMAGE)
                ->assertJsonPath('data.media.processing_status', WorkMedia::PROCESSING_READY);
            $media = WorkMedia::query()->findOrFail($response->json('data.media.id'));
            $this->assertSame($designer->id, $media->uploaded_by);
            Storage::disk('works_private')->assertExists($media->path);
            $this->assertStringNotContainsString('/admin/', $response->json('data.media.content_url'));
        }
    }

    public function test_non_editable_statuses_return_safe_409_for_all_writes(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);

        foreach ([
            Work::STATUS_SUBMITTED,
            Work::STATUS_IN_REVIEW,
            Work::STATUS_APPROVED,
            Work::STATUS_PUBLISHED,
            Work::STATUS_REJECTED,
            Work::STATUS_HIDDEN,
            Work::STATUS_ARCHIVED,
        ] as $status) {
            $work = $this->work($designer, ['status' => $status]);
            $media = WorkMedia::factory()->image()->ready()->create(['work_id' => $work->id]);
            $expected = [
                'data' => ['reason' => 'work_state_not_editable', 'current_status' => $status],
                'message' => 'لا يمكن تعديل وسائط العمل في حالته الحالية.',
            ];

            $this->uploadImage($work)->assertStatus(409)->assertJson($expected);
            $this->deleteJson($this->itemUrl($work, $media))->assertStatus(409)->assertJson($expected);
            $this->patchJson($this->mediaUrl($work).'/order', ['media_ids' => [$media->id]])
                ->assertStatus(409)->assertJson($expected);
            $this->patchJson($this->mediaUrl($work).'/cover', ['cover_media_id' => $media->id])
                ->assertStatus(409)->assertJson($expected);
        }
    }

    public function test_foreign_work_and_cross_work_operations_return_404(): void
    {
        $designer = $this->designer();
        $own = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_GALLERY]);
        $foreign = $this->work($this->designer(), ['media_type' => Work::MEDIA_TYPE_GALLERY]);
        $foreignMedia = WorkMedia::factory()->create(['work_id' => $foreign->id]);
        Sanctum::actingAs($designer);

        $this->uploadImage($foreign)->assertNotFound();
        $this->deleteJson($this->itemUrl($foreign, $foreignMedia))->assertNotFound();
        $this->deleteJson($this->itemUrl($own, $foreignMedia))->assertNotFound();
        $this->patchJson($this->mediaUrl($own).'/cover', ['cover_media_id' => $foreignMedia->id])
            ->assertNotFound();
    }

    public function test_upload_rejects_extra_body_query_wrong_type_and_settings_limits(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);
        $imageWork = $this->work($designer);

        $this->post($this->mediaUrl($imageWork), [
            'file' => UploadedFile::fake()->image('safe.jpg'),
            'caption' => 'blocked',
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post($this->mediaUrl($imageWork).'?preview=1', [
            'file' => UploadedFile::fake()->image('safe.jpg'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post($this->mediaUrl($imageWork), [
            'file' => UploadedFile::fake()->create('fake.jpg', 2, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->setLimits(1, 1);
        $gallery = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_GALLERY]);
        $this->uploadImage($gallery)->assertCreated();
        $this->uploadImage($gallery, 'second.jpg')->assertStatus(409);
    }

    public function test_video_upload_is_pending_and_dispatches_processing(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_VIDEO]);
        Sanctum::actingAs($designer);

        $content = hex2bin(
            '000000206674797069736F6D0000020069736F6D69736F32617663316D703431',
        );

        if ($content === false) {
            self::fail('Unable to create the MP4 test fixture.');
        }

        $path = tempnam(sys_get_temp_dir(), 'ym-designer-media-');

        if ($path === false) {
            self::fail('Unable to create a temporary MP4 test file.');
        }

        file_put_contents($path, $content);

        try {
            $file = new UploadedFile(
                $path,
                'clip.mp4',
                null,
                null,
                true,
            );

            $response = $this->post($this->mediaUrl($work), ['file' => $file], [
                'Accept' => 'application/json',
            ])->assertCreated()
                ->assertJsonPath(
                    'data.media.processing_status',
                    WorkMedia::PROCESSING_PENDING,
                );

            Queue::assertPushed(
                ProcessWorkMedia::class,
                fn (ProcessWorkMedia $job): bool =>
                    $job->mediaId === $response->json('data.media.id'),
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_failed_upload_creates_no_row_file_or_media_audit(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer);
        Sanctum::actingAs($designer);
        $this->post($this->mediaUrl($work), [
            'file' => UploadedFile::fake()->create('bad.txt', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertDatabaseCount('work_media', 0);
        $this->assertSame([], Storage::disk('works_private')->allFiles());
        $this->assertSame(0, $this->mediaAuditCount());
    }

    public function test_delete_soft_deletes_retains_file_and_clears_cover(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer);
        $media = $this->storedImage($work);
        $work->forceFill(['cover_media_id' => $media->id])->save();
        Sanctum::actingAs($designer);

        $this->deleteJson($this->itemUrl($work, $media))
            ->assertOk()
            ->assertJsonPath('data.cover_cleared', true)
            ->assertJsonPath('data.physical_file_retained', true);
        $this->assertSoftDeleted('work_media', ['id' => $media->id]);
        Storage::disk('works_private')->assertExists($media->path);
        $this->assertNull($work->fresh()->cover_media_id);
    }

    public function test_delete_and_retry_requests_reject_body_and_query(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $media = WorkMedia::factory()->video()->failed()->create(['work_id' => $work->id]);
        Sanctum::actingAs($designer);

        $this->deleteJson($this->itemUrl($work, $media), ['force' => true])->assertUnprocessable();
        $this->deleteJson($this->itemUrl($work, $media).'?force=1')->assertUnprocessable();
        $this->postJson($this->itemUrl($work, $media).'/retry-processing', ['force' => true])
            ->assertUnprocessable();
    }

    public function test_reorder_requires_exact_active_set_normalizes_positions_and_preserves_cover(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_GALLERY]);
        $first = WorkMedia::factory()->create(['work_id' => $work->id, 'position' => 1]);
        $second = WorkMedia::factory()->create(['work_id' => $work->id, 'position' => 2]);
        $work->forceFill(['cover_media_id' => $first->id])->save();
        Sanctum::actingAs($designer);

        $this->patchJson($this->mediaUrl($work).'/order', ['media_ids' => [$second->id, $first->id]])
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.media.0.position', 1)
            ->assertJsonPath('data.work.cover_media_id', $first->id);
        $this->patchJson($this->mediaUrl($work).'/order', ['media_ids' => [$first->id]])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.media_ids.0',
                'يجب أن تمثل القائمة المجموعة الكاملة لجميع وسائط العمل الفعالة.',
            );
        $this->patchJson($this->mediaUrl($work).'/order', ['media_ids' => [$first->id, $first->id]])
            ->assertUnprocessable();
        $this->patchJson($this->mediaUrl($work).'/order?preview=1', [
            'media_ids' => [$first->id, $second->id],
        ])->assertUnprocessable();
    }

    public function test_reorder_no_op_preserves_timestamps_and_writes_no_audit(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_GALLERY]);
        $media = WorkMedia::factory()->create(['work_id' => $work->id, 'position' => 1]);
        $updatedAt = $media->updated_at;
        Sanctum::actingAs($designer);
        $before = $this->mediaAuditCount();

        $this->patchJson($this->mediaUrl($work).'/order', ['media_ids' => [$media->id]])
            ->assertOk()->assertJsonPath('data.changed', false);
        $this->assertTrue($updatedAt->equalTo($media->fresh()->updated_at));
        $this->assertSame($before, $this->mediaAuditCount());
    }

    public function test_cover_can_be_set_changed_cleared_and_no_op_is_stable(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_GALLERY]);
        $first = WorkMedia::factory()->image()->ready()->create(['work_id' => $work->id]);
        $second = WorkMedia::factory()->image()->ready()->create(['work_id' => $work->id]);
        Sanctum::actingAs($designer);

        $this->patchJson($this->mediaUrl($work).'/cover', ['cover_media_id' => $first->id])
            ->assertOk()->assertJsonPath('data.current_cover_media_id', $first->id);
        $this->patchJson($this->mediaUrl($work).'/cover', ['cover_media_id' => $second->id])
            ->assertOk()->assertJsonPath('data.current_cover_media_id', $second->id);
        $updatedAt = $work->fresh()->updated_at;
        $this->patchJson($this->mediaUrl($work).'/cover', ['cover_media_id' => $second->id])
            ->assertOk()->assertJsonPath('data.changed', false);
        $this->assertTrue($updatedAt->equalTo($work->fresh()->updated_at));
        $this->patchJson($this->mediaUrl($work).'/cover', ['cover_media_id' => null])
            ->assertOk()->assertJsonPath('data.current_cover_media_id', null);
    }

    public function test_invalid_video_failed_foreign_and_deleted_media_cannot_be_cover(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_GALLERY]);
        $video = WorkMedia::factory()->video()->ready()->create(['work_id' => $work->id]);
        $failed = WorkMedia::factory()->image()->failed()->create(['work_id' => $work->id]);
        $deleted = WorkMedia::factory()->image()->ready()->create(['work_id' => $work->id]);
        $deleted->delete();
        Sanctum::actingAs($designer);

        $this->patchJson($this->mediaUrl($work).'/cover', ['cover_media_id' => $video->id])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.cover_media_id.0',
                'يجب أن يشير الغلاف إلى صورة فعالة وجاهزة تابعة للعمل.',
            );
        $this->patchJson($this->mediaUrl($work).'/cover', ['cover_media_id' => $failed->id])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.cover_media_id.0',
                'يجب أن يشير الغلاف إلى صورة فعالة وجاهزة تابعة للعمل.',
            );
        $this->patchJson($this->mediaUrl($work).'/cover', ['cover_media_id' => $deleted->id])
            ->assertNotFound();
        $this->patchJson($this->mediaUrl($work).'/cover', [])->assertUnprocessable();
        $this->patchJson($this->mediaUrl($work).'/cover', [
            'cover_media_id' => null,
            'extra' => true,
        ])->assertUnprocessable();
    }

    public function test_failed_video_can_retry_and_pending_non_stalled_retry_is_no_op(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $failed = WorkMedia::factory()->video()->failed()->create(['work_id' => $work->id]);
        Sanctum::actingAs($designer);

        $this->postJson($this->itemUrl($work, $failed).'/retry-processing')
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.media.processing_status', WorkMedia::PROCESSING_PENDING);
        Queue::assertPushed(ProcessWorkMedia::class);

        $this->postJson($this->itemUrl($work, $failed).'/retry-processing')
            ->assertOk()
            ->assertJsonPath('data.changed', false);
    }

    public function test_retry_is_limited_to_owned_editable_video(): void
    {
        $designer = $this->designer();
        $foreignWork = $this->work($this->designer(), ['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $foreign = WorkMedia::factory()->video()->failed()->create(['work_id' => $foreignWork->id]);
        $published = $this->work($designer, [
            'media_type' => Work::MEDIA_TYPE_VIDEO,
            'status' => Work::STATUS_PUBLISHED,
        ]);
        $blocked = WorkMedia::factory()->video()->failed()->create(['work_id' => $published->id]);
        Sanctum::actingAs($designer);

        $this->postJson($this->itemUrl($foreignWork, $foreign).'/retry-processing')->assertNotFound();
        $this->postJson($this->itemUrl($published, $blocked).'/retry-processing')->assertStatus(409);
    }

    public function test_content_and_poster_are_owned_private_and_exclude_deleted_media(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $video = WorkMedia::factory()->video()->ready()->create([
            'work_id' => $work->id,
            'path' => "works/{$work->id}/video.mp4",
            'poster_path' => "works/{$work->id}/poster.jpg",
        ]);
        Storage::disk('works_private')->put($video->path, 'video');
        Storage::disk('works_private')->put($video->poster_path, 'poster');
        Sanctum::actingAs($designer);

        $this->get($this->itemUrl($work, $video).'/content')->assertOk();
        $this->get($this->itemUrl($work, $video).'/poster')->assertOk();
        $video->delete();
        $this->get($this->itemUrl($work, $video).'/content')->assertNotFound();
        $this->get($this->itemUrl($work, $video).'/poster')->assertNotFound();
    }

    public function test_video_cover_routes_enforce_ownership_and_editable_states(): void
    {
        $designer = $this->designer();
        $ownWork = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $ownVideo = $this->storedVideo($ownWork);
        $foreignWork = $this->work($this->designer(), ['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $foreignVideo = $this->storedVideo($foreignWork);
        $designer->assignRole('super-admin');
        Sanctum::actingAs($designer);

        $this->patchJson($this->videoCoverUrl($foreignWork, $foreignVideo).'/current')
            ->assertNotFound();
        $this->patchJson($this->videoCoverUrl($ownWork, $foreignVideo).'/current')
            ->assertNotFound();

        foreach ([
            Work::STATUS_SUBMITTED,
            Work::STATUS_IN_REVIEW,
            Work::STATUS_APPROVED,
            Work::STATUS_PUBLISHED,
            Work::STATUS_REJECTED,
            Work::STATUS_HIDDEN,
            Work::STATUS_ARCHIVED,
        ] as $status) {
            $blockedWork = $this->work($designer, [
                'media_type' => Work::MEDIA_TYPE_VIDEO,
                'status' => $status,
            ]);
            $blockedVideo = $this->storedVideo($blockedWork);
            $base = $this->videoCoverUrl($blockedWork, $blockedVideo);

            $this->patchJson($base.'/current')
                ->assertStatus(409)
                ->assertJsonPath('data.reason', 'work_state_not_editable');
            $this->patchJson($base.'/frame', ['time_ms' => 0])
                ->assertStatus(409)
                ->assertJsonPath('data.reason', 'work_state_not_editable');
            $this->post($base.'/upload', [
                'file' => UploadedFile::fake()->image('cover.jpg'),
            ], ['Accept' => 'application/json'])
                ->assertStatus(409)
                ->assertJsonPath('data.reason', 'work_state_not_editable');
        }
    }

    public function test_video_cover_rejects_incompatible_work_media_and_processing_states(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);

        $imageWork = $this->work($designer);
        $image = $this->storedImage($imageWork);
        $this->patchJson($this->videoCoverUrl($imageWork, $image).'/current')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work');

        $videoWork = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $wrongKind = $this->storedImage($videoWork);
        $this->patchJson($this->videoCoverUrl($videoWork, $wrongKind).'/current')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('media');

        foreach ([WorkMedia::PROCESSING_PENDING, WorkMedia::PROCESSING_FAILED] as $status) {
            $video = WorkMedia::factory()->video()->create([
                'work_id' => $videoWork->id,
                'disk' => 'works_private',
                'path' => "works/{$videoWork->id}/{$status}.mp4",
                'processing_status' => $status,
            ]);
            Storage::disk('works_private')->put($video->path, 'video');

            $this->patchJson($this->videoCoverUrl($videoWork, $video).'/current')
                ->assertUnprocessable()
                ->assertJsonValidationErrors('media');
        }
    }

    public function test_current_video_poster_requires_file_sets_cover_and_no_op_is_stable(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, [
            'media_type' => Work::MEDIA_TYPE_VIDEO,
            'status' => Work::STATUS_CHANGES_REQUESTED,
        ]);
        $video = $this->storedVideo($work);
        Storage::disk('works_private')->delete($video->poster_path);
        Sanctum::actingAs($designer);
        $url = $this->videoCoverUrl($work, $video).'/current';

        $this->patchJson($url)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cover_media_id');

        Storage::disk('works_private')->put($video->poster_path, 'poster');
        $response = $this->patchJson($url)
            ->assertOk()
            ->assertJsonPath('data.action', 'use_current_video_poster')
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.cover_media_id', $video->id)
            ->assertJsonPath('data.mode', 'current_poster');
        $this->assertSame($video->id, $work->fresh()->cover_media_id);
        $this->assertStringNotContainsString('poster_path', $response->getContent());
        $this->assertDatabaseCount('work_media', 1);

        $updatedAt = $work->fresh()->updated_at;
        $mediaUpdatedAt = $video->fresh()->updated_at;
        $auditCount = $this->videoCoverAuditCount();
        $this->patchJson($url)
            ->assertOk()
            ->assertJsonPath('data.changed', false);
        $this->assertTrue($updatedAt->equalTo($work->fresh()->updated_at));
        $this->assertTrue($mediaUpdatedAt->equalTo($video->fresh()->updated_at));
        $this->assertSame($auditCount, $this->videoCoverAuditCount());
    }

    public function test_video_frame_request_validation_is_strict_and_time_must_fit_duration(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $video = $this->storedVideo($work, ['duration_ms' => 5000]);
        Sanctum::actingAs($designer);
        $url = $this->videoCoverUrl($work, $video).'/frame';

        foreach ([
            ['time_ms' => -1],
            ['time_ms' => 1.5],
            ['time_ms' => 'one'],
            ['time_ms' => 5000],
            ['time_ms' => 5001],
        ] as $payload) {
            $this->patchJson($url, $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('time_ms');
        }

        $this->patchJson($url, ['time_ms' => 1000, 'extra' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request')
            ->assertJsonPath(
                'errors.request.0',
                'هذا الحقل غير مسموح به.',
            );
        $this->patchJson($url.'?preview=1', ['time_ms' => 1000])
            ->assertUnprocessable();
        $this->patchJson($this->videoCoverUrl($work, $video).'/current', ['extra' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request')
            ->assertJsonPath(
                'errors.request.0',
                'هذا الطلب لا يقبل بيانات إضافية.',
            );
        $this->patchJson($this->videoCoverUrl($work, $video).'/current?preview=1')
            ->assertUnprocessable();
    }

    public function test_select_video_frame_replaces_private_poster_sets_cover_and_audits_safely(): void
    {
        $this->fakeVideoCoverGenerator();
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $video = $this->storedVideo($work, ['duration_ms' => 5000]);
        $oldPoster = $video->poster_path;
        Sanctum::actingAs($designer);

        $response = $this->patchJson(
            $this->videoCoverUrl($work, $video).'/frame',
            ['time_ms' => 1250],
        )->assertOk()
            ->assertJsonPath('data.action', 'select_video_frame')
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.cover_media_id', $video->id)
            ->assertJsonPath('data.mode', 'frame')
            ->assertJsonPath('data.time_ms', 1250);

        $fresh = $video->fresh();
        $this->assertNotSame($oldPoster, $fresh->poster_path);
        $this->assertStringStartsWith(
            "works/{$work->id}/derived/{$video->id}-cover-",
            $fresh->poster_path,
        );
        $this->assertStringEndsWith('.jpg', $fresh->poster_path);
        Storage::disk('works_private')->assertMissing($oldPoster);
        Storage::disk('works_private')->assertExists($fresh->poster_path);
        $this->assertSame($video->id, $work->fresh()->cover_media_id);
        $this->assertDatabaseCount('work_media', 1);

        foreach (['disk', 'path', 'poster_path', 'processing_error', 'ffmpeg', 'tmp'] as $private) {
            $this->assertStringNotContainsString($private, strtolower($response->getContent()));
        }

        $audit = AuditEvent::query()
            ->where('event_type', 'works.media.video_cover_updated')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('select_video_frame', $audit->action);
        $this->assertSame([
            'work_id' => $work->id,
            'media_id' => $video->id,
            'mode' => 'frame',
            'time_ms' => 1250,
        ], $audit->metadata);
    }

    public function test_video_cover_upload_accepts_supported_images_and_enforces_request_contract(): void
    {
        $this->fakeVideoCoverGenerator();
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $video = $this->storedVideo($work);
        Sanctum::actingAs($designer);
        $url = $this->videoCoverUrl($work, $video).'/upload';

        foreach (['jpg', 'png', 'webp'] as $extension) {
            $oldPoster = $video->fresh()->poster_path;
            $response = $this->post($url, [
                'file' => UploadedFile::fake()->image('cover.'.$extension, 80, 45),
            ], ['Accept' => 'application/json'])
                ->assertOk()
                ->assertJsonPath('data.action', 'upload_video_cover')
                ->assertJsonPath('data.cover_media_id', $video->id)
                ->assertJsonPath('data.mode', 'uploaded_image');

            $this->assertStringNotContainsString('poster_path', $response->getContent());
            $fresh = $video->fresh();
            Storage::disk('works_private')->assertMissing($oldPoster);
            Storage::disk('works_private')->assertExists($fresh->poster_path);
            $this->assertStringStartsWith(
                "works/{$work->id}/derived/{$video->id}-cover-",
                $fresh->poster_path,
            );
            $this->assertStringEndsWith('.jpg', $fresh->poster_path);
        }
        $this->assertDatabaseCount('work_media', 1);

        $this->post($url, [
            'file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post($url, [
            'file' => UploadedFile::fake()->create('fake.jpg', 1, 'image/jpeg'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post($url, [
            'file' => UploadedFile::fake()->image('cover.jpg'),
            'extra' => true,
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('extra')
            ->assertJsonPath(
                'errors.extra.0',
                'هذا الحقل غير مسموح به.',
            );
        $this->post($url.'?preview=1', [
            'file' => UploadedFile::fake()->image('cover.jpg'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->setLimits(1, 1);
        $this->post($url, [
            'file' => UploadedFile::fake()->image('large.jpg', 600, 600),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $audit = AuditEvent::query()
            ->where('event_type', 'works.media.video_cover_updated')
            ->where('action', 'upload_video_cover')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(
            ['work_id', 'media_id', 'mode', 'original_mime_type', 'size_bytes', 'width', 'height'],
            array_keys($audit->metadata),
        );
        $this->assertStringNotContainsString(
            'path',
            strtolower(json_encode($audit->metadata, JSON_THROW_ON_ERROR)),
        );
    }

    public function test_video_cover_generator_failure_preserves_database_files_and_audits(): void
    {
        $this->fakeVideoCoverGenerator(true);
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_VIDEO]);
        $existingCover = WorkMedia::factory()->image()->ready()->create([
            'work_id' => $work->id,
        ]);
        $video = $this->storedVideo($work, ['duration_ms' => 5000]);
        $work->forceFill(['cover_media_id' => $existingCover->id])->save();
        $oldPoster = $video->poster_path;
        $filesBefore = Storage::disk('works_private')->allFiles();
        $auditCount = $this->videoCoverAuditCount();
        Sanctum::actingAs($designer);

        $this->patchJson(
            $this->videoCoverUrl($work, $video).'/frame',
            ['time_ms' => 1000],
        )->assertServerError();

        $this->assertSame($oldPoster, $video->fresh()->poster_path);
        $this->assertSame($existingCover->id, $work->fresh()->cover_media_id);
        $this->assertSame($filesBefore, Storage::disk('works_private')->allFiles());
        $this->assertSame($auditCount, $this->videoCoverAuditCount());
    }

    public function test_successful_operations_write_safe_audits_while_failed_operations_do_not(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer, ['media_type' => Work::MEDIA_TYPE_GALLERY]);
        Sanctum::actingAs($designer);
        $uploaded = $this->uploadImage($work)->assertCreated();
        $media = WorkMedia::query()->findOrFail($uploaded->json('data.media.id'));
        $afterUpload = $this->mediaAuditCount();
        $this->patchJson($this->mediaUrl($work).'/cover', ['cover_media_id' => $media->id])->assertOk();
        $this->patchJson($this->mediaUrl($work).'/order', ['media_ids' => [$media->id]])->assertOk();
        $this->deleteJson($this->itemUrl($work, $media))->assertOk();
        $this->assertGreaterThan($afterUpload, $this->mediaAuditCount());

        $beforeFailure = $this->mediaAuditCount();
        $this->deleteJson($this->itemUrl($work, $media))->assertNotFound();
        $this->assertSame($beforeFailure, $this->mediaAuditCount());
    }

    public function test_designer_remains_denied_from_admin_media_routes(): void
    {
        $designer = $this->designer();
        $work = $this->work($designer);
        $media = WorkMedia::factory()->create(['work_id' => $work->id]);
        Sanctum::actingAs($designer);

        $this->getJson("/api/admin/works/{$work->id}/media")->assertForbidden();
        $this->postJson("/api/admin/works/{$work->id}/media")->assertForbidden();
        $this->deleteJson("/api/admin/works/{$work->id}/media/{$media->id}")->assertForbidden();
    }

    private function designer(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('designer');

        return $user;
    }

    private function work(User $designer, array $attributes = []): Work
    {
        return Work::factory()->create([
            'designer_id' => $designer->id,
            'status' => Work::STATUS_DRAFT,
            'media_type' => Work::MEDIA_TYPE_IMAGE,
            ...$attributes,
        ]);
    }

    private function uploadImage(Work $work, string $name = 'image.jpg')
    {
        return $this->post($this->mediaUrl($work), [
            'file' => UploadedFile::fake()->image($name, 30, 20),
        ], ['Accept' => 'application/json']);
    }

    private function storedImage(Work $work): WorkMedia
    {
        $media = WorkMedia::factory()->image()->ready()->create([
            'work_id' => $work->id,
            'path' => "works/{$work->id}/image.jpg",
        ]);
        Storage::disk('works_private')->put($media->path, 'image');

        return $media;
    }

    private function storedVideo(Work $work, array $attributes = []): WorkMedia
    {
        $media = WorkMedia::factory()->video()->ready()->create([
            'work_id' => $work->id,
            'disk' => 'works_private',
            'path' => "works/{$work->id}/video-".uniqid().'.mp4',
            'poster_path' => "works/{$work->id}/poster-".uniqid().'.jpg',
            'duration_ms' => 10000,
            ...$attributes,
        ]);
        Storage::disk('works_private')->put($media->path, 'video');
        Storage::disk('works_private')->put($media->poster_path, 'poster');

        return $media;
    }

    private function mediaUrl(Work $work): string
    {
        return "/api/designer/works/{$work->id}/media";
    }

    private function itemUrl(Work $work, WorkMedia $media): string
    {
        return $this->mediaUrl($work)."/{$media->id}";
    }

    private function videoCoverUrl(Work $work, WorkMedia $media): string
    {
        return $this->itemUrl($work, $media).'/video-cover';
    }

    private function fakeVideoCoverGenerator(bool $fail = false): void
    {
        $this->mock(
            WorksVideoCoverImageGenerator::class,
            function (MockInterface $mock) use ($fail): void {
                if ($fail) {
                    $mock->shouldReceive('generateFrame')
                        ->andThrow(new RuntimeException('تعذر استخراج اللقطة المحددة من الفيديو.'));
                    $mock->shouldReceive('normalizeImage')
                        ->andThrow(new RuntimeException('تعذر تجهيز صورة غلاف الفيديو.'));

                    return;
                }

                $mock->shouldReceive('generateFrame')
                    ->andReturnUsing(
                        static function (
                            string $videoPath,
                            string $outputPath,
                            int $timeMs,
                        ): void {
                            file_put_contents($outputPath, 'generated-jpeg');
                        },
                    );
                $mock->shouldReceive('normalizeImage')
                    ->andReturnUsing(
                        static function (string $imagePath, string $outputPath): void {
                            file_put_contents($outputPath, 'normalized-jpeg');
                        },
                    );
            },
        );
    }

    private function setLimits(?int $maxItems, ?int $maxKb): void
    {
        WorkSetting::query()->updateOrCreate(
            ['scope' => 'global'],
            [
                'values' => [
                    'media_limits' => [
                        'max_items' => $maxItems,
                        'max_file_size_kb' => $maxKb,
                        'allowed_types' => null,
                    ],
                ],
                'version' => 1,
                'updated_by' => User::factory()->create()->id,
            ],
        );
    }

    private function mediaAuditCount(): int
    {
        return AuditEvent::query()
            ->where('event_type', 'like', 'works.media.%')
            ->count();
    }

    private function videoCoverAuditCount(): int
    {
        return AuditEvent::query()
            ->where('event_type', 'works.media.video_cover_updated')
            ->count();
    }
}
