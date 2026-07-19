<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksMediaController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkMedia;
use App\Models\WorkSetting;
use App\Services\Audit\AuditEventLogger;
use App\Services\Works\WorksSettingsStore;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use LogicException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksAdminMediaApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
        Storage::fake('works_private');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_media_routes_resolve_to_the_four_expected_actions(): void
    {
        $routes = [
            ['GET', '/api/admin/works/10/media', 'index'],
            ['POST', '/api/admin/works/10/media', 'store'],
            ['GET', '/api/admin/works/10/media/20/content', 'content'],
            ['DELETE', '/api/admin/works/10/media/20', 'destroy'],
        ];

        foreach ($routes as [$method, $uri, $action]) {
            $route = Route::getRoutes()->match(
                \Illuminate\Http\Request::create($uri, $method),
            );

            $this->assertSame(
                WorksMediaController::class.'@'.$action,
                $route->getActionName(),
            );
        }
    }

    public function test_unsupported_media_methods_return_405(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->work();
        $media = WorkMedia::factory()->create(['work_id' => $work->id]);

        $this->putJson($this->mediaEndpoint($work), [])->assertMethodNotAllowed();
        $this->patchJson($this->mediaEndpoint($work), [])->assertMethodNotAllowed();
        $this->patchJson($this->mediaItemEndpoint($work, $media), [])
            ->assertMethodNotAllowed();
    }

    public function test_all_media_routes_require_authentication(): void
    {
        $this->getJson('/api/admin/works/1/media')->assertUnauthorized();
        $this->postJson('/api/admin/works/1/media')->assertUnauthorized();
        $this->getJson('/api/admin/works/1/media/1/content')->assertUnauthorized();
        $this->deleteJson('/api/admin/works/1/media/1')->assertUnauthorized();
        $this->assertSame(0, $this->mediaAuditEvents()->count());
    }

    public function test_client_designer_and_non_internal_roles_are_forbidden(): void
    {
        $work = $this->work();
        $media = WorkMedia::factory()->create(['work_id' => $work->id]);
        Role::create(['name' => 'contractor', 'guard_name' => 'web']);

        foreach (['client', 'designer', 'contractor'] as $role) {
            $this->actingAsRole($role, [
                'admin.works.access',
                'admin.works.media.view',
                'admin.works.update.media',
            ]);

            $this->getJson($this->mediaEndpoint($work))->assertForbidden();
            $this->postJson($this->mediaEndpoint($work))->assertForbidden();
            $this->getJson($this->contentEndpoint($work, $media))->assertForbidden();
            $this->deleteJson($this->mediaItemEndpoint($work, $media))->assertForbidden();
        }
    }

    public function test_access_and_operation_permissions_are_field_scoped(): void
    {
        $work = $this->work();
        $media = WorkMedia::factory()->create(['work_id' => $work->id]);
        Storage::disk('works_private')->put($media->path, 'permission-content');

        $this->actingAsRole('admin', ['admin.works.media.view']);
        $this->getJson($this->mediaEndpoint($work))->assertForbidden();

        $this->actingAsRole('admin', ['admin.works.access']);
        $this->getJson($this->mediaEndpoint($work))->assertForbidden();

        foreach (['admin.works.media.view', 'admin.works.update.media'] as $permission) {
            $this->actingAsRole('admin', ['admin.works.access', $permission]);
            $this->getJson($this->mediaEndpoint($work))->assertOk();
            $this->get($this->contentEndpoint($work, $media))->assertOk();
        }

        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.media.view',
        ]);
        $this->postJson($this->mediaEndpoint($work))->assertForbidden();
        $this->deleteJson($this->mediaItemEndpoint($work, $media))->assertForbidden();

        $this->actingAsRole('staff', [
            'admin.works.access',
            'admin.works.update.media',
        ]);
        $this->getJson($this->mediaEndpoint($work))->assertOk();
        $this->get($this->contentEndpoint($work, $media))->assertOk();

        $editableWork = $this->work();
        $uploaded = $this->uploadImage($editableWork)->assertCreated();
        $uploadedMedia = WorkMedia::query()->findOrFail($uploaded->json('data.media.id'));
        $this->deleteJson($this->mediaItemEndpoint($editableWork, $uploadedMedia))
            ->assertOk();
    }

    public function test_create_permission_alone_does_not_manage_existing_work_media(): void
    {
        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.create',
        ]);
        $work = $this->work();

        $this->getJson($this->mediaEndpoint($work))->assertForbidden();
        $this->postJson($this->mediaEndpoint($work))->assertForbidden();
    }

    public function test_draft_and_changes_requested_allow_upload_and_delete(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([Work::STATUS_DRAFT, Work::STATUS_CHANGES_REQUESTED] as $status) {
            $work = $this->work(['status' => $status]);
            $uploaded = $this->uploadImage($work)->assertCreated();
            $mediaId = $uploaded->json('data.media.id');

            $this->deleteJson('/api/admin/works/'.$work->id.'/media/'.$mediaId)
                ->assertOk()
                ->assertJsonPath('data.action', 'delete');
        }
    }

    public function test_non_editable_states_return_safe_409_for_upload_and_delete(): void
    {
        $this->actingAsRole('super-admin');
        $statuses = [
            Work::STATUS_SUBMITTED,
            Work::STATUS_IN_REVIEW,
            Work::STATUS_APPROVED,
            Work::STATUS_PUBLISHED,
            Work::STATUS_REJECTED,
            Work::STATUS_HIDDEN,
            Work::STATUS_ARCHIVED,
        ];

        foreach ($statuses as $status) {
            $work = $this->work(['status' => $status]);
            $media = WorkMedia::factory()->create(['work_id' => $work->id]);

            $this->uploadImage($work)
                ->assertStatus(409)
                ->assertExactJson([
                    'success' => false,
                    'data' => [
                        'reason' => 'work_state_not_editable',
                        'current_status' => $status,
                    ],
                    'message' => 'لا يمكن تعديل وسائط العمل في حالته الحالية.',
                    'errors' => null,
                ]);

            $this->deleteJson($this->mediaItemEndpoint($work, $media))
                ->assertStatus(409)
                ->assertJsonPath('data.reason', 'work_state_not_editable')
                ->assertJsonPath('data.current_status', $status);
        }

        $this->assertSame(0, $this->mediaAuditEvents()->count());
    }

    public function test_list_and_content_are_available_in_non_editable_states(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->work(['status' => Work::STATUS_PUBLISHED]);
        $media = WorkMedia::factory()->image()->ready()->create([
            'work_id' => $work->id,
            'path' => 'works/'.$work->id.'/published.jpg',
        ]);
        Storage::disk('works_private')->put($media->path, 'published-content');

        $this->getJson($this->mediaEndpoint($work))->assertOk();
        $this->get($this->contentEndpoint($work, $media))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_read_and_delete_requests_reject_body_and_query_input(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->work();
        $media = WorkMedia::factory()->create(['work_id' => $work->id]);

        $this->getJson($this->mediaEndpoint($work).'?preview=true')
            ->assertUnprocessable();
        $this->call('GET', $this->mediaEndpoint($work), ['extra' => true], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ])->assertUnprocessable();
        $this->getJson($this->contentEndpoint($work, $media).'?download=true')
            ->assertUnprocessable();
        $this->call(
            'GET',
            $this->contentEndpoint($work, $media),
            ['download' => true],
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        )->assertUnprocessable();
        $this->deleteJson($this->mediaItemEndpoint($work, $media).'?force=true')
            ->assertUnprocessable();
        $this->deleteJson($this->mediaItemEndpoint($work, $media), ['force' => true])
            ->assertUnprocessable();
    }

    public function test_upload_requires_exactly_one_file_and_rejects_extra_input(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->work();

        $this->postJson($this->mediaEndpoint($work))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
        $this->post($this->mediaEndpoint($work), [
            'file' => ['nested'],
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post($this->mediaEndpoint($work), [
            'file' => UploadedFile::fake()->image('valid.jpg'),
            'caption' => 'blocked',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('caption');
        $this->post($this->mediaEndpoint($work).'?preview=true', [
            'file' => UploadedFile::fake()->image('valid.jpg'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post($this->mediaEndpoint($work), [
            'file' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ],
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_image_mode_accepts_jpeg_png_and_webp_with_real_dimensions(): void
    {
        $this->actingAsRole('super-admin');

        foreach (['jpg', 'png', 'webp'] as $extension) {
            $work = $this->work(['media_type' => Work::MEDIA_TYPE_IMAGE]);
            $response = $this->post($this->mediaEndpoint($work), [
                'file' => UploadedFile::fake()->image('sample.'.$extension, 23, 17),
            ], ['Accept' => 'application/json'])->assertCreated();

            $this->assertSame(23, $response->json('data.media.width'));
            $this->assertSame(17, $response->json('data.media.height'));
            $this->assertSame('ready', $response->json('data.media.processing_status'));
            $this->assertNull($response->json('data.media.duration_ms'));
        }
    }

    public function test_image_and_gallery_reject_non_images_and_unsafe_image_formats(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([Work::MEDIA_TYPE_IMAGE, Work::MEDIA_TYPE_GALLERY] as $mediaType) {
            foreach ([
                $this->videoUpload('clip.mp4', 'video/mp4'),
                UploadedFile::fake()->image('animation.gif'),
                UploadedFile::fake()->createWithContent(
                    'vector.svg',
                    '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
                ),
                UploadedFile::fake()->create('blob.bin', 1, 'application/octet-stream'),
            ] as $file) {
                $work = $this->work(['media_type' => $mediaType]);
                $this->post($this->mediaEndpoint($work), ['file' => $file], [
                    'Accept' => 'application/json',
                ])->assertUnprocessable();
            }
        }
    }

    public function test_gallery_accepts_images_and_uses_its_configured_count_limit(): void
    {
        $this->actingAsRole('super-admin');
        $this->setMediaLimits(2, null, [Work::MEDIA_TYPE_GALLERY], 4);
        $work = $this->work(['media_type' => Work::MEDIA_TYPE_GALLERY]);

        $this->uploadImage($work, 'one.jpg')->assertCreated();
        $this->uploadImage($work, 'two.png')->assertCreated();
        $this->uploadImage($work, 'three.webp')
            ->assertStatus(409)
            ->assertJsonPath('data.reason', 'media_items_limit_reached')
            ->assertJsonPath('data.current_count', 2)
            ->assertJsonPath('data.effective_max_items', 2)
            ->assertJsonPath('data.settings_version', 4);
    }

    public function test_video_mode_accepts_supported_mimes_and_sets_pending_metadata(): void
    {
        $this->actingAsRole('super-admin');
        $cases = [
            ['clip.mp4', 'video/mp4', 'mp4'],
            ['clip.webm', 'video/webm', 'webm'],
            ['clip.mov', 'video/quicktime', 'mov'],
        ];

        foreach ($cases as [$name, $mime, $extension]) {
            $work = $this->work(['media_type' => Work::MEDIA_TYPE_VIDEO]);
            $response = $this->post($this->mediaEndpoint($work), [
                'file' => $this->videoUpload($name, $mime),
            ], ['Accept' => 'application/json'])->assertCreated();

            $this->assertSame('video', $response->json('data.media.kind'));
            $this->assertSame($mime, $response->json('data.media.mime_type'));
            $this->assertSame($extension, $response->json('data.media.extension'));
            $this->assertSame('pending', $response->json('data.media.processing_status'));
            $this->assertNull($response->json('data.media.width'));
            $this->assertNull($response->json('data.media.height'));
            $this->assertNull($response->json('data.media.duration_ms'));
        }
    }

    public function test_video_mode_rejects_images(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->work(['media_type' => Work::MEDIA_TYPE_VIDEO]);

        $this->uploadImage($work)->assertUnprocessable();
        $this->assertDatabaseCount('work_media', 0);
    }

    public function test_detected_mime_controls_extension_and_client_name_never_controls_path(): void
    {
        $this->actingAsRole('super-admin');
        $maliciousWork = $this->work();
        $this->post($this->mediaEndpoint($maliciousWork), [
            'file' => $this->realUploadedFile(
                'disguised.jpg',
                '<?php echo "not an image";',
            ),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $work = $this->work();
        $source = UploadedFile::fake()->image('source.jpg', 11, 9);
        $file = $this->realUploadedFile(
            '../../unsafe-name.php',
            file_get_contents($source->getRealPath()),
        );

        $response = $this->post($this->mediaEndpoint($work), ['file' => $file], [
            'Accept' => 'application/json',
        ])->assertCreated();
        $media = WorkMedia::query()->findOrFail($response->json('data.media.id'));

        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame('jpg', $media->extension);
        $this->assertMatchesRegularExpression(
            '#^works/'.$work->id.'/[0-9a-f-]{36}\.jpg$#',
            $media->path,
        );
        $this->assertStringNotContainsString('unsafe-name', $media->path);
        $this->assertStringNotContainsString('..', $media->original_name);
    }

    public function test_unreadable_image_dimensions_fail_safely_without_storage_or_database_row(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->work();
        $truncatedJpeg = hex2bin('FFD8FFE000104A46494600010100000100010000FFD9');

        if ($truncatedJpeg === false) {
            throw new RuntimeException('Unable to create the truncated JPEG fixture.');
        }

        $this->post($this->mediaEndpoint($work), [
            'file' => $this->realUploadedFile('truncated.jpg', $truncatedJpeg),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertDatabaseCount('work_media', 0);
        $this->assertSame([], Storage::disk('works_private')->allFiles());
        $this->assertSame(0, $this->mediaAuditEvents()->count());
    }

    public function test_media_type_and_allowed_types_conflicts_are_safe(): void
    {
        $this->actingAsRole('super-admin');
        $withoutType = $this->work(['media_type' => null]);

        $this->uploadImage($withoutType)
            ->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'data' => ['reason' => 'media_type_required'],
                'message' => 'يجب تحديد نمط وسائط العمل قبل رفع الملفات.',
                'errors' => null,
            ]);

        $this->setMediaLimits(null, null, [Work::MEDIA_TYPE_VIDEO], 8);
        $imageWork = $this->work(['media_type' => Work::MEDIA_TYPE_IMAGE]);
        $this->uploadImage($imageWork)
            ->assertStatus(409)
            ->assertJsonPath('data.reason', 'media_type_not_allowed')
            ->assertJsonPath('data.current_media_type', 'image')
            ->assertJsonPath('data.allowed_media_types', ['video'])
            ->assertJsonPath('data.settings_version', 8);
    }

    public function test_null_allowed_types_allows_all_three_work_media_modes(): void
    {
        $this->actingAsRole('super-admin');
        $this->setMediaLimits(null, null, null, 2);

        $this->uploadImage($this->work(['media_type' => 'image']))->assertCreated();
        $this->uploadImage($this->work(['media_type' => 'gallery']))->assertCreated();
        $video = $this->work(['media_type' => 'video']);
        $this->post($this->mediaEndpoint($video), [
            'file' => $this->videoUpload('clip.mp4', 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_max_file_size_is_applied_and_null_adds_no_settings_limit(): void
    {
        $this->actingAsRole('super-admin');
        $this->setMediaLimits(null, 1, null, 3);
        $limited = $this->work();

        $this->post($this->mediaEndpoint($limited), [
            'file' => UploadedFile::fake()->image('large.jpg')->size(2),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->setMediaLimits(null, null, null, 4);
        $unlimited = $this->work();
        $this->post($this->mediaEndpoint($unlimited), [
            'file' => UploadedFile::fake()->image('larger.jpg')->size(2048),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.media_policy.effective_limits.max_file_size_kb', null);
    }

    public function test_image_and_video_have_effective_limit_one_and_soft_deleted_rows_do_not_count(): void
    {
        $this->actingAsRole('super-admin');
        $this->setMediaLimits(10, null, null, 5);

        foreach ([Work::MEDIA_TYPE_IMAGE, Work::MEDIA_TYPE_VIDEO] as $mediaType) {
            $work = $this->work(['media_type' => $mediaType]);
            $deleted = WorkMedia::factory()->create(['work_id' => $work->id]);
            $deleted->delete();

            $response = $mediaType === Work::MEDIA_TYPE_IMAGE
                ? $this->uploadImage($work)
                : $this->post($this->mediaEndpoint($work), [
                    'file' => $this->videoUpload('clip.mp4', 'video/mp4'),
                ], ['Accept' => 'application/json']);

            $response->assertCreated()
                ->assertJsonPath('data.media_policy.effective_limits.max_items', 1)
                ->assertJsonPath('data.counts.active', 1)
                ->assertJsonPath('data.counts.remaining', 0);
        }
    }

    public function test_settings_are_read_once_per_upload_and_next_request_sees_changes(): void
    {
        $store = new class extends WorksSettingsStore
        {
            public int $calls = 0;

            /** @var list<string>|null */
            public ?array $allowedTypes = null;

            public function getGlobalSettings(): array
            {
                $this->calls++;

                return [
                    'scope' => 'global',
                    'version' => $this->calls,
                    'values' => [
                        'review_sla_hours' => null,
                        'direct_publish_trust_enabled' => false,
                        'media_limits' => [
                            'max_items' => null,
                            'max_file_size_kb' => null,
                            'allowed_types' => $this->allowedTypes,
                        ],
                    ],
                    'storage_record_found' => true,
                    'updated_at' => null,
                ];
            }
        };
        $this->app->instance(WorksSettingsStore::class, $store);
        $this->actingAsRole('super-admin');

        $this->uploadImage($this->work())->assertCreated();
        $this->assertSame(1, $store->calls);

        $store->allowedTypes = [Work::MEDIA_TYPE_VIDEO];
        $this->uploadImage($this->work())
            ->assertStatus(409)
            ->assertJsonPath('data.allowed_media_types', ['video']);
        $this->assertSame(2, $store->calls);
    }

    public function test_media_policy_is_exact_allowlisted_and_handles_null_media_type(): void
    {
        $this->actingAsRole('super-admin');
        $this->setMediaLimits(12, 51200, ['image', 'gallery'], 3);
        $work = $this->work(['media_type' => Work::MEDIA_TYPE_GALLERY]);

        $policy = $this->getJson($this->mediaEndpoint($work))
            ->assertOk()
            ->json('data.media_policy');

        $this->assertSame([
            'source' => 'work_settings',
            'settings_version' => 3,
            'work_media_type' => 'gallery',
            'allowed_media_types' => ['image', 'gallery'],
            'allowed_file_kinds' => ['image'],
            'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'configured_limits' => [
                'max_items' => 12,
                'max_file_size_kb' => 51200,
            ],
            'effective_limits' => [
                'max_items' => 12,
                'max_file_size_kb' => 51200,
            ],
            'enforcement' => [
                'media_type' => true,
                'max_items' => true,
                'max_file_size_kb' => true,
                'mime_type' => true,
            ],
        ], $policy);

        $nullPolicy = $this->getJson($this->mediaEndpoint(
            $this->work(['media_type' => null]),
        ))->assertOk()->json('data.media_policy');
        $this->assertSame([], $nullPolicy['allowed_file_kinds']);
        $this->assertSame([], $nullPolicy['allowed_mime_types']);
        $this->assertNull($nullPolicy['effective_limits']['max_items']);
        $this->assertStringNotContainsString(
            'review_sla_hours',
            json_encode($policy, JSON_THROW_ON_ERROR),
        );
    }

    public function test_successful_upload_persists_private_file_and_exact_safe_metadata(): void
    {
        $actor = $this->actingAsRole('super-admin');
        $work = $this->work(['media_type' => Work::MEDIA_TYPE_GALLERY]);
        $response = $this->uploadImage($work, 'client-photo.png', 31, 19)
            ->assertCreated()
            ->assertJsonPath('data.action', 'upload')
            ->assertJsonPath('data.counts.active', 1)
            ->assertJsonPath('data.counts.remaining', null);
        $media = WorkMedia::query()->findOrFail($response->json('data.media.id'));

        Storage::disk('works_private')->assertExists($media->path);
        Storage::disk('public')->assertMissing($media->path);
        $this->assertSame($work->id, $media->work_id);
        $this->assertSame($actor->id, $media->uploaded_by);
        $this->assertSame('works_private', $media->disk);
        $this->assertSame('client-photo.png', $media->original_name);
        $this->assertSame('image/png', $media->mime_type);
        $this->assertSame('png', $media->extension);
        $this->assertSame('image', $media->kind);
        $this->assertSame(1, $media->position);
        $this->assertSame(31, $media->width);
        $this->assertSame(19, $media->height);
        $this->assertSame('ready', $media->processing_status);
        $this->assertNull($media->processing_error);
    }

    public function test_positions_follow_highest_active_position(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->work(['media_type' => Work::MEDIA_TYPE_GALLERY]);
        WorkMedia::factory()->create(['work_id' => $work->id, 'position' => 7]);
        $deleted = WorkMedia::factory()->create(['work_id' => $work->id, 'position' => 20]);
        $deleted->delete();

        $this->uploadImage($work)
            ->assertCreated()
            ->assertJsonPath('data.media.position', 8);
    }

    public function test_audit_failure_rolls_back_upload_and_deletes_compensating_file(): void
    {
        $this->actingAsRole('super-admin');
        $this->app->instance(AuditEventLogger::class, new class extends AuditEventLogger
        {
            public function record(array $event): AuditEvent
            {
                parent::record($event);

                throw new LogicException('Forced media audit failure.');
            }
        });
        $work = $this->work();
        $this->withoutExceptionHandling();

        try {
            $this->uploadImage($work);
            $this->fail('The forced audit failure was not thrown.');
        } catch (LogicException $exception) {
            $this->assertSame('Forced media audit failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('work_media', 0);
        $this->assertDatabaseCount('audit_events', 0);
        $this->assertSame([], Storage::disk('works_private')->allFiles());
    }

    public function test_storage_failure_creates_neither_media_nor_media_audit(): void
    {
        $this->actingAsRole('super-admin');
        Storage::shouldReceive('disk')
            ->once()
            ->with('works_private')
            ->andThrow(new RuntimeException('Forced storage failure.'));
        $this->withoutExceptionHandling();

        try {
            $this->uploadImage($this->work());
            $this->fail('The forced storage failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced storage failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('work_media', 0);
        $this->assertSame(0, $this->mediaAuditEvents()->count());
    }

    public function test_list_is_ordered_allowlisted_and_includes_safe_uploader_and_content_endpoint(): void
    {
        $uploader = $this->actingAsRole('super-admin');
        $work = $this->work(['cover_media_id' => null]);
        $later = WorkMedia::factory()->failed()->create([
            'work_id' => $work->id,
            'uploaded_by' => $uploader->id,
            'position' => 5,
        ]);
        $first = WorkMedia::factory()->image()->ready()->create([
            'work_id' => $work->id,
            'uploaded_by' => null,
            'position' => 2,
        ]);
        $second = WorkMedia::factory()->image()->ready()->create([
            'work_id' => $work->id,
            'position' => 2,
        ]);

        $response = $this->getJson($this->mediaEndpoint($work))->assertOk();
        $items = $response->json('data.media');
        $this->assertSame([
            'id' => $work->id,
            'status' => Work::STATUS_DRAFT,
            'media_type' => Work::MEDIA_TYPE_IMAGE,
            'cover_media_id' => null,
        ], $response->json('data.work'));
        $this->assertSame([
            'can_view_media' => true,
            'can_update_media' => true,
        ], $response->json('data.field_access'));
        $this->assertSame([$first->id, $second->id, $later->id], array_column($items, 'id'));
        $this->assertSame([
            'id',
            'kind',
            'original_name',
            'mime_type',
            'extension',
            'size_bytes',
            'size_kb',
            'position',
            'width',
            'height',
            'duration_ms',
            'processing_status',
            'is_cover',
            'uploaded_by',
            'created_at',
            'updated_at',
            'content_endpoint',
        ], array_keys($items[0]));
        $this->assertNull($items[0]['uploaded_by']);
        $this->assertSame(
            '/api/admin/works/'.$work->id.'/media/'.$first->id.'/content',
            $items[0]['content_endpoint'],
        );
        $serialized = json_encode($response->json('data'), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('"disk"', $serialized);
        $this->assertStringNotContainsString('"path"', $serialized);
        $this->assertStringNotContainsString('processing_error', $serialized);
        $this->assertStringNotContainsString('deleted_at', $serialized);
    }

    public function test_content_streams_exact_private_file_inline_with_nosniff(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->work();
        $media = WorkMedia::factory()->image()->ready()->create([
            'work_id' => $work->id,
            'path' => 'works/'.$work->id.'/private.jpg',
            'original_name' => "safe\r\nname.jpg",
            'mime_type' => 'image/jpeg',
        ]);
        Storage::disk('works_private')->put($media->path, 'private-bytes');

        $response = $this->get($this->contentEndpoint($work, $media))->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString(
            'inline',
            (string) $response->headers->get('Content-Disposition'),
        );
        $this->assertStringNotContainsString(
            $media->path,
            (string) $response->headers->get('Content-Disposition'),
        );
        $this->assertSame('private-bytes', $response->streamedContent());
    }

    public function test_cross_work_missing_file_and_soft_deleted_content_return_safe_404(): void
    {
        $this->actingAsRole('super-admin');
        $firstWork = $this->work();
        $secondWork = $this->work();
        $media = WorkMedia::factory()->image()->create([
            'work_id' => $firstWork->id,
            'path' => 'works/'.$firstWork->id.'/missing.jpg',
        ]);

        $this->getJson($this->contentEndpoint($secondWork, $media))->assertNotFound();
        $missing = $this->getJson($this->contentEndpoint($firstWork, $media))
            ->assertNotFound();
        $this->assertStringNotContainsString($media->path, $missing->getContent());

        Storage::disk('works_private')->put($media->path, 'retained');
        $media->delete();
        $this->getJson($this->contentEndpoint($firstWork, $media))->assertNotFound();
    }

    public function test_delete_soft_deletes_hides_media_retains_file_and_frees_limit(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->work();
        $uploaded = $this->uploadImage($work)->assertCreated();
        $media = WorkMedia::query()->findOrFail($uploaded->json('data.media.id'));

        $this->deleteJson($this->mediaItemEndpoint($work, $media))
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => [
                    'action' => 'delete',
                    'deleted_media_id' => $media->id,
                    'cover_cleared' => false,
                    'physical_file_retained' => true,
                    'counts' => ['active' => 0, 'remaining' => 1],
                ],
                'message' => 'تم حذف وسيط العمل منطقيًا',
                'errors' => null,
            ]);

        $this->assertNotNull(WorkMedia::withTrashed()->findOrFail($media->id)->deleted_at);
        Storage::disk('works_private')->assertExists($media->path);
        $this->getJson($this->mediaEndpoint($work))
            ->assertOk()
            ->assertJsonCount(0, 'data.media');
        $this->getJson($this->contentEndpoint($work, $media))->assertNotFound();
        $this->uploadImage($work, 'replacement.jpg')->assertCreated();
    }

    public function test_deleting_cover_clears_reference_and_cross_work_delete_is_404(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->work();
        $media = WorkMedia::factory()->image()->create(['work_id' => $work->id]);
        $work->update(['cover_media_id' => $media->id]);

        $this->deleteJson($this->mediaItemEndpoint($work, $media))
            ->assertOk()
            ->assertJsonPath('data.cover_cleared', true);
        $this->assertNull($work->fresh()->cover_media_id);

        $otherWork = $this->work();
        $otherMedia = WorkMedia::factory()->create(['work_id' => $otherWork->id]);
        $this->deleteJson($this->mediaItemEndpoint($work, $otherMedia))
            ->assertNotFound();
        $this->assertNull($otherMedia->fresh()->deleted_at);
    }

    public function test_delete_audit_failure_rolls_back_media_and_cover_changes(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->work();
        $media = WorkMedia::factory()->image()->create(['work_id' => $work->id]);
        $work->update(['cover_media_id' => $media->id]);
        $this->app->instance(AuditEventLogger::class, new class extends AuditEventLogger
        {
            public function record(array $event): AuditEvent
            {
                parent::record($event);

                throw new LogicException('Forced delete audit failure.');
            }
        });
        $this->withoutExceptionHandling();

        try {
            $this->deleteJson($this->mediaItemEndpoint($work, $media));
            $this->fail('The forced delete audit failure was not thrown.');
        } catch (LogicException $exception) {
            $this->assertSame('Forced delete audit failure.', $exception->getMessage());
        }

        $this->assertNull($media->fresh()->deleted_at);
        $this->assertSame($media->id, $work->fresh()->cover_media_id);
        $this->assertSame(0, $this->mediaAuditEvents()->count());
    }

    public function test_upload_and_delete_record_one_safe_exact_audit_event_each(): void
    {
        $this->actingAsRole('super-admin');
        $this->setMediaLimits(5, 2048, null, 9);
        $work = $this->work(['media_type' => Work::MEDIA_TYPE_GALLERY]);
        $uploaded = $this->uploadImage($work, 'private-client-name.jpg')->assertCreated();
        $media = WorkMedia::query()->findOrFail($uploaded->json('data.media.id'));
        $this->deleteJson($this->mediaItemEndpoint($work, $media))->assertOk();

        $uploadEvent = $this->mediaAuditEvents()
            ->where('event_type', 'works.media.uploaded')
            ->sole();
        $deleteEvent = $this->mediaAuditEvents()
            ->where('event_type', 'works.media.deleted')
            ->sole();

        $this->assertSame('works', $uploadEvent->category);
        $this->assertSame('work_media', $uploadEvent->target_type);
        $this->assertSame($media->id, $uploadEvent->target_id);
        $this->assertSame('upload', $uploadEvent->action);
        $this->assertSame([
            'work_id',
            'kind',
            'size_bytes',
            'position',
            'settings_version',
        ], array_keys($uploadEvent->metadata));
        $this->assertSame([
            'work_id',
            'kind',
            'position',
            'was_cover',
            'settings_version',
        ], array_keys($deleteEvent->metadata));

        $serialized = $uploadEvent->toJson().$deleteEvent->toJson();
        foreach (['path', 'disk', 'original_name', 'private-client-name'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $serialized);
        }
    }

    public function test_403_409_422_and_missing_file_record_no_media_audit_events(): void
    {
        $work = $this->work();
        $this->actingAsRole('admin', ['admin.works.access']);
        $this->postJson($this->mediaEndpoint($work))->assertForbidden();

        $this->actingAsRole('super-admin');
        $locked = $this->work(['status' => Work::STATUS_SUBMITTED]);
        $this->uploadImage($locked)->assertStatus(409);
        $this->postJson($this->mediaEndpoint($work))->assertUnprocessable();
        $missing = WorkMedia::factory()->image()->create([
            'work_id' => $work->id,
            'path' => 'works/'.$work->id.'/not-there.jpg',
        ]);
        $this->getJson($this->contentEndpoint($work, $missing))->assertNotFound();

        $this->assertSame(0, $this->mediaAuditEvents()->count());
    }

    public function test_work_media_hidden_fields_remain_absent_from_api_payloads(): void
    {
        $this->actingAsRole('super-admin');
        $work = $this->work();
        $media = WorkMedia::factory()->failed()->create(['work_id' => $work->id]);

        $payload = $this->getJson($this->mediaEndpoint($work))
            ->assertOk()
            ->json('data.media.0');

        foreach (['disk', 'path', 'processing_error', 'deleted_at'] as $field) {
            $this->assertArrayNotHasKey($field, $payload);
        }
        $this->assertArrayNotHasKey('work', $payload);
        $this->assertArrayNotHasKey('uploader', $payload);
        $this->assertSame($media->id, $payload['id']);
    }

    private function work(array $attributes = []): Work
    {
        return Work::factory()->create([
            'status' => Work::STATUS_DRAFT,
            'media_type' => Work::MEDIA_TYPE_IMAGE,
            ...$attributes,
        ]);
    }

    private function actingAsRole(string $role, array $permissions = []): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function uploadImage(
        Work $work,
        string $name = 'image.jpg',
        int $width = 10,
        int $height = 10,
    ): \Illuminate\Testing\TestResponse {
        return $this->post($this->mediaEndpoint($work), [
            'file' => UploadedFile::fake()->image($name, $width, $height),
        ], ['Accept' => 'application/json']);
    }

    private function realUploadedFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ym-media-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary media test file.');
        }

        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    private function videoUpload(string $name, string $mimeType): UploadedFile
    {
        $content = match ($mimeType) {
            'video/mp4' => hex2bin(
                '000000206674797069736F6D0000020069736F6D69736F32617663316D703431',
            ),
            'video/webm' => hex2bin(
                '1A45DFA39F4286810142F7810142F2810442F381084282847765626D4287810242858102',
            ),
            'video/quicktime' => hex2bin(
                '0000001466747970717420200000000071742020',
            ),
            default => false,
        };

        if ($content === false) {
            throw new RuntimeException('Unsupported video MIME test fixture.');
        }

        return $this->realUploadedFile($name, $content.str_repeat("\0", 128));
    }

    private function mediaEndpoint(Work $work): string
    {
        return '/api/admin/works/'.$work->id.'/media';
    }

    private function mediaItemEndpoint(Work $work, WorkMedia $media): string
    {
        return $this->mediaEndpoint($work).'/'.$media->id;
    }

    private function contentEndpoint(Work $work, WorkMedia $media): string
    {
        return $this->mediaItemEndpoint($work, $media).'/content';
    }

    private function setMediaLimits(
        mixed $maxItems,
        mixed $maxFileSizeKb,
        mixed $allowedTypes,
        int $version,
    ): void {
        $setting = WorkSetting::query()
            ->where('scope', WorkSetting::SCOPE_GLOBAL)
            ->first() ?? new WorkSetting(['scope' => WorkSetting::SCOPE_GLOBAL]);

        $setting->forceFill([
            'values' => [
                'review_sla_hours' => null,
                'direct_publish_trust_enabled' => false,
                'media_limits' => [
                    'max_items' => $maxItems,
                    'max_file_size_kb' => $maxFileSizeKb,
                    'allowed_types' => $allowedTypes,
                ],
            ],
            'version' => $version,
        ])->save();
    }

    private function mediaAuditEvents(): \Illuminate\Database\Eloquent\Builder
    {
        return AuditEvent::query()->whereIn('event_type', [
            'works.media.uploaded',
            'works.media.deleted',
        ]);
    }
}
