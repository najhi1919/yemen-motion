<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Work;
use App\Models\WorkMedia;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorksAuthoringMediaSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_media_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('work_media'));
    }

    public function test_work_media_table_has_all_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('work_media', [
            'id',
            'work_id',
            'uploaded_by',
            'disk',
            'path',
            'original_name',
            'mime_type',
            'extension',
            'kind',
            'size_bytes',
            'position',
            'width',
            'height',
            'duration_ms',
            'processing_status',
            'processing_error',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));
        $this->assertTrue(Schema::hasIndex('work_media', ['disk', 'path'], 'unique'));
        $this->assertTrue(Schema::hasIndex('work_media', ['kind']));
        $this->assertTrue(Schema::hasIndex('work_media', ['processing_status']));
        $this->assertTrue(Schema::hasIndex('work_media', ['work_id', 'position']));
        $this->assertTrue(Schema::hasIndex('work_media', ['work_id', 'kind']));
    }

    public function test_works_table_has_nullable_cover_media_id(): void
    {
        $this->assertTrue(Schema::hasColumn('works', 'cover_media_id'));
        $this->assertTrue(Schema::hasIndex('works', ['cover_media_id']));

        $work = Work::factory()->create(['cover_media_id' => null]);

        $this->assertNull($work->cover_media_id);
    }

    public function test_existing_work_factory_contract_remains_valid_without_a_cover(): void
    {
        $work = Work::factory()->create();

        $this->assertDatabaseHas('works', ['id' => $work->id]);
        $this->assertNull($work->cover_media_id);
    }

    public function test_work_media_factory_creates_metadata_without_creating_a_file(): void
    {
        $filesBefore = $this->privateWorksFiles();

        $media = WorkMedia::factory()->create();

        $this->assertSame('works_private', $media->disk);
        $this->assertStringStartsWith('works/', $media->path);
        $this->assertNotSame('', $media->original_name);
        $this->assertGreaterThan(0, $media->size_bytes);
        $this->assertSame($filesBefore, $this->privateWorksFiles());
    }

    public function test_work_media_belongs_to_work(): void
    {
        $work = Work::factory()->create();
        $media = WorkMedia::factory()->create(['work_id' => $work->id]);

        $this->assertTrue($media->work->is($work));
    }

    public function test_work_media_uploader_relation_resolves_an_existing_user(): void
    {
        $uploader = User::factory()->create();
        $media = WorkMedia::factory()->create(['uploaded_by' => $uploader->id]);

        $this->assertTrue($media->uploader->is($uploader));
    }

    public function test_deleting_uploader_nulls_uploaded_by_without_deleting_media(): void
    {
        $uploader = User::factory()->create();
        $media = WorkMedia::factory()->create(['uploaded_by' => $uploader->id]);

        $uploader->delete();
        $media->refresh();

        $this->assertNull($media->uploaded_by);
        $this->assertDatabaseHas('work_media', ['id' => $media->id]);
    }

    public function test_work_media_relation_orders_by_position_then_id(): void
    {
        $work = Work::factory()->create();
        $later = WorkMedia::factory()->create(['work_id' => $work->id, 'position' => 8]);
        $first = WorkMedia::factory()->create(['work_id' => $work->id, 'position' => 2]);
        $second = WorkMedia::factory()->create(['work_id' => $work->id, 'position' => 2]);

        $this->assertSame(
            [$first->id, $second->id, $later->id],
            $work->media()->pluck('id')->all(),
        );
    }

    public function test_work_media_relation_excludes_soft_deleted_media(): void
    {
        $work = Work::factory()->create();
        $active = WorkMedia::factory()->create(['work_id' => $work->id]);
        $deleted = WorkMedia::factory()->create(['work_id' => $work->id]);

        $deleted->delete();

        $this->assertSame([$active->id], $work->media()->pluck('id')->all());
    }

    public function test_with_trashed_can_explicitly_access_deleted_media(): void
    {
        $work = Work::factory()->create();
        $media = WorkMedia::factory()->create(['work_id' => $work->id]);

        $media->delete();

        $this->assertTrue($work->media()->withTrashed()->findOrFail($media->id)->is($media));
    }

    public function test_cover_media_relation_returns_the_selected_media(): void
    {
        $work = Work::factory()->create();
        $media = WorkMedia::factory()->image()->create(['work_id' => $work->id]);
        $work->update(['cover_media_id' => $media->id]);

        $this->assertTrue($work->fresh()->coverMedia->is($media));

        $media->delete();

        $this->assertNull($work->fresh()->coverMedia);
    }

    public function test_force_deleting_cover_media_nulls_cover_media_id(): void
    {
        $work = Work::factory()->create();
        $media = WorkMedia::factory()->image()->create(['work_id' => $work->id]);
        $work->update(['cover_media_id' => $media->id]);

        $media->forceDelete();

        $this->assertNull($work->fresh()->cover_media_id);
    }

    public function test_deleting_work_physically_cascades_its_media_records(): void
    {
        $work = Work::factory()->create();
        $media = WorkMedia::factory()->create(['work_id' => $work->id]);

        $work->delete();

        $this->assertDatabaseMissing('work_media', ['id' => $media->id]);
    }

    public function test_disk_and_path_combination_is_unique(): void
    {
        WorkMedia::factory()->create([
            'disk' => 'works_private',
            'path' => 'works/unique/media.jpg',
        ]);

        $this->expectException(QueryException::class);

        WorkMedia::factory()->create([
            'disk' => 'works_private',
            'path' => 'works/unique/media.jpg',
        ]);
    }

    public function test_same_path_is_allowed_on_a_different_disk(): void
    {
        $path = 'works/shared/media.jpg';

        WorkMedia::factory()->create(['disk' => 'works_private', 'path' => $path]);
        WorkMedia::factory()->create(['disk' => 'archive_private', 'path' => $path]);

        $this->assertDatabaseCount('work_media', 2);
    }

    public function test_position_can_be_temporarily_duplicated_within_a_work(): void
    {
        $work = Work::factory()->create();

        WorkMedia::factory()->count(2)->create([
            'work_id' => $work->id,
            'position' => 4,
        ]);

        $this->assertSame(2, $work->media()->where('position', 4)->count());
    }

    public function test_numeric_attributes_are_cast_to_integers(): void
    {
        $media = WorkMedia::factory()->create([
            'size_bytes' => 123456,
            'position' => 7,
            'width' => 1920,
            'height' => 1080,
            'duration_ms' => 9000,
        ])->fresh();

        $this->assertIsInt($media->work_id);
        $this->assertIsInt($media->uploaded_by);
        $this->assertIsInt($media->size_bytes);
        $this->assertIsInt($media->position);
        $this->assertIsInt($media->width);
        $this->assertIsInt($media->height);
        $this->assertIsInt($media->duration_ms);
    }

    public function test_work_media_kind_and_processing_constants_are_complete(): void
    {
        $this->assertSame(['image', 'video'], WorkMedia::KINDS);
        $this->assertSame('image', WorkMedia::KIND_IMAGE);
        $this->assertSame('video', WorkMedia::KIND_VIDEO);
        $this->assertSame(
            ['pending', 'ready', 'failed'],
            WorkMedia::PROCESSING_STATUSES,
        );
        $this->assertSame('pending', WorkMedia::PROCESSING_PENDING);
        $this->assertSame('ready', WorkMedia::PROCESSING_READY);
        $this->assertSame('failed', WorkMedia::PROCESSING_FAILED);
    }

    public function test_work_media_type_constants_are_complete(): void
    {
        $this->assertSame(['image', 'video', 'gallery'], Work::MEDIA_TYPES);
        $this->assertSame('image', Work::MEDIA_TYPE_IMAGE);
        $this->assertSame('video', Work::MEDIA_TYPE_VIDEO);
        $this->assertSame('gallery', Work::MEDIA_TYPE_GALLERY);
    }

    public function test_sensitive_storage_attributes_are_hidden_from_serialization(): void
    {
        $media = WorkMedia::factory()->failed()->create();

        $array = $media->toArray();
        $json = $media->toJson();

        $this->assertArrayNotHasKey('disk', $array);
        $this->assertArrayNotHasKey('path', $array);
        $this->assertArrayNotHasKey('processing_error', $array);
        $this->assertStringNotContainsString('"disk"', $json);
        $this->assertStringNotContainsString('"path"', $json);
        $this->assertStringNotContainsString('"processing_error"', $json);
    }

    public function test_works_private_disk_is_private_and_has_no_public_url(): void
    {
        $disk = config('filesystems.disks.works_private');

        $this->assertSame('local', $disk['driver']);
        $this->assertSame(storage_path('app/private/works'), $disk['root']);
        $this->assertTrue($disk['throw']);
        $this->assertFalse($disk['report']);
        $this->assertArrayNotHasKey('url', $disk);
        $this->assertArrayNotHasKey('visibility', $disk);
        $this->assertNotContains(
            storage_path('app/private/works'),
            config('filesystems.links'),
        );
    }

    public function test_factories_and_model_operations_do_not_create_storage_files(): void
    {
        $filesBefore = $this->privateWorksFiles();

        WorkMedia::factory()->image()->ready()->make();
        WorkMedia::factory()->video()->failed()->create();

        $this->assertSame($filesBefore, $this->privateWorksFiles());
    }

    /**
     * @return list<string>
     */
    private function privateWorksFiles(): array
    {
        $root = storage_path('app/private/works');

        if (! File::isDirectory($root)) {
            return [];
        }

        return collect(File::allFiles($root))
            ->map(fn (\SplFileInfo $file): string => $file->getPathname())
            ->sort()
            ->values()
            ->all();
    }
}
