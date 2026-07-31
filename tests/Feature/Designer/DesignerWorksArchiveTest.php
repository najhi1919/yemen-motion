<?php

namespace Tests\Feature\Designer;

use App\Http\Controllers\Api\DesignerWorksArchiveController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkCategory;
use App\Models\WorkMedia;
use App\Models\WorkTag;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesignerWorksArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthRolesSeeder::class);
    }

    public function test_routes_use_expected_names_controller_patch_method_and_numeric_constraint(): void
    {
        $archive = Route::getRoutes()->getByName('designer.works.archive');
        $restore = Route::getRoutes()->getByName('designer.works.restore');

        $this->assertSame(DesignerWorksArchiveController::class.'@archive', $archive?->getActionName());
        $this->assertSame(DesignerWorksArchiveController::class.'@restore', $restore?->getActionName());
        $this->assertSame(['PATCH'], $archive?->methods());
        $this->assertSame(['PATCH'], $restore?->methods());
        $this->assertSame('[0-9]+', $archive?->wheres['work'] ?? null);
        $this->assertSame('[0-9]+', $restore?->wheres['work'] ?? null);
        $this->patchJson('/api/designer/works/not-a-number/archive')->assertNotFound();
    }

    public function test_post_put_delete_are_not_supported(): void
    {
        $designer = $this->userWithRole('designer');
        $work = $this->work($designer);
        Sanctum::actingAs($designer);

        foreach (['postJson', 'putJson', 'deleteJson'] as $method) {
            $this->{$method}($this->archiveUrl($work))->assertMethodNotAllowed();
            $this->{$method}($this->restoreUrl($work))->assertMethodNotAllowed();
        }
    }

    public function test_guest_non_designers_and_disabled_designer_are_denied(): void
    {
        $owner = $this->userWithRole('designer');
        $work = $this->work($owner);
        $body = $this->versionBody($work);
        $this->patchJson($this->archiveUrl($work), $body)->assertUnauthorized();
        $this->patchJson($this->restoreUrl($work), $body)->assertUnauthorized();

        foreach (['client', 'admin', 'staff'] as $role) {
            Sanctum::actingAs($this->userWithRole($role));
            $this->patchJson($this->archiveUrl($work), $body)->assertForbidden();
            $this->patchJson($this->restoreUrl($work), $body)->assertForbidden();
        }

        $disabled = $this->userWithRole('designer', ['disabled_at' => now()]);
        $disabledWork = $this->work($disabled);
        Sanctum::actingAs($disabled);
        $this->patchJson($this->archiveUrl($disabledWork), $this->versionBody($disabledWork))
            ->assertForbidden();
        $this->patchJson($this->restoreUrl($disabledWork), $this->versionBody($disabledWork))
            ->assertForbidden();
    }

    public function test_ownership_is_hidden_and_multi_role_cannot_bypass_it(): void
    {
        $designer = $this->userWithRole('designer');
        $designer->assignRole('super-admin');
        $owned = $this->work($designer);
        $foreign = $this->work($this->userWithRole('designer'));
        Sanctum::actingAs($designer);

        $this->patchJson($this->archiveUrl($owned), $this->versionBody($owned))->assertOk();
        $this->patchJson($this->archiveUrl($foreign), $this->versionBody($foreign))->assertNotFound();
        $this->patchJson('/api/designer/works/999999/archive', $this->versionBody($foreign))->assertNotFound();
    }

    public function test_exact_body_is_required_and_query_or_extra_sensitive_fields_are_rejected(): void
    {
        $designer = $this->userWithRole('designer');
        $work = $this->work($designer);
        Sanctum::actingAs($designer);

        $this->patchJson($this->archiveUrl($work), [])->assertUnprocessable()
            ->assertJsonValidationErrors('expected_updated_at');
        $this->patchJson($this->archiveUrl($work).'?force=1', $this->versionBody($work))
            ->assertUnprocessable()->assertJsonValidationErrors('unsupported_request_input');

        foreach (['status', 'visibility_status', 'designer_id', 'force', 'delete', 'metadata'] as $field) {
            $this->patchJson($this->archiveUrl($work), array_merge($this->versionBody($work), [$field => 'x']))
                ->assertUnprocessable()->assertJsonValidationErrors('unsupported_request_input');
        }
    }

    public function test_archive_accepts_each_designer_archivable_status(): void
    {
        $designer = $this->userWithRole('designer');
        Sanctum::actingAs($designer);

        foreach (Work::DESIGNER_ARCHIVABLE_STATUSES as $status) {
            $visibility = $status === Work::STATUS_PUBLISHED ? Work::VISIBILITY_PUBLIC : Work::VISIBILITY_HIDDEN;
            $work = $this->work($designer, $status, ['visibility_status' => $visibility]);
            $this->patchJson($this->archiveUrl($work), $this->versionBody($work))
                ->assertOk()
                ->assertJsonPath('data.changed', true)
                ->assertJsonPath('data.action', 'archive')
                ->assertJsonPath('data.previous_status', $status)
                ->assertJsonPath('data.work.status', Work::STATUS_ARCHIVED);
            $archived = $work->fresh();
            $this->assertSame(Work::STATUS_ARCHIVED, $archived->status);
            $this->assertSame(Work::VISIBILITY_HIDDEN, $archived->visibility_status);
            $this->assertSame($status, $archived->archived_from_status);
            $this->assertSame($visibility, $archived->archived_from_visibility_status);
            $this->assertNotNull($archived->archived_at);
        }
    }

    public function test_archive_rejects_review_controlled_statuses_without_changes(): void
    {
        $designer = $this->userWithRole('designer');
        Sanctum::actingAs($designer);

        foreach (Work::DESIGNER_ARCHIVE_BLOCKED_STATUSES as $status) {
            $work = $this->work($designer, $status);
            $this->patchJson($this->archiveUrl($work), $this->versionBody($work))
                ->assertConflict()
                ->assertJsonPath('data.code', 'work_state_not_archivable')
                ->assertJsonPath('data.current_status', $status);
            $this->assertSame($status, $work->fresh()->status);
        }

        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_published_archive_hides_immediately_unfeatures_unpins_and_preserves_work_data(): void
    {
        $designer = $this->userWithRole('designer');
        $category = WorkCategory::factory()->create();
        $tag = WorkTag::factory()->create();
        $work = $this->work($designer, Work::STATUS_PUBLISHED, [
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'category_id' => $category->id,
            'is_featured' => true,
            'is_pinned' => true,
            'cover_display_mode' => Work::COVER_DISPLAY_MODE_FIT,
            'cover_focal_x' => 23,
            'cover_focal_y' => 71,
        ]);
        $media = WorkMedia::factory()->ready()->create(['work_id' => $work->id, 'uploaded_by' => $designer->id]);
        $work->forceFill(['cover_media_id' => $media->id])->save();
        $work->tags()->sync([$tag->id]);
        $snapshot = $work->fresh();
        Sanctum::actingAs($designer);

        $this->patchJson($this->archiveUrl($snapshot), $this->versionBody($snapshot))->assertOk();
        $archived = $work->fresh();

        $this->assertSame(Work::STATUS_ARCHIVED, $archived->status);
        $this->assertSame(Work::VISIBILITY_HIDDEN, $archived->visibility_status);
        $this->assertFalse($archived->is_featured);
        $this->assertFalse($archived->is_pinned);
        foreach (['public_code', 'cover_media_id', 'category_id', 'cover_display_mode', 'cover_focal_x', 'cover_focal_y', 'reviewer_id', 'published_at', 'views_count', 'likes_count', 'reports_count'] as $field) {
            $this->assertEquals($snapshot->{$field}, $archived->{$field});
        }
        $this->assertSame([$media->id], $archived->media()->pluck('id')->all());
        $this->assertSame([$tag->id], $archived->tags()->pluck('work_tags.id')->all());
    }

    public function test_repeated_archive_is_no_op_without_timestamp_source_change_or_audit(): void
    {
        $designer = $this->userWithRole('designer');
        $work = $this->work($designer, Work::STATUS_ARCHIVED, [
            'archived_at' => now()->subDay(),
            'archived_from_status' => Work::STATUS_PUBLISHED,
            'archived_from_visibility_status' => Work::VISIBILITY_PUBLIC,
        ]);
        $snapshot = $work->fresh();
        Sanctum::actingAs($designer);

        $this->patchJson($this->archiveUrl($snapshot), $this->versionBody($snapshot))
            ->assertOk()->assertJsonPath('data.changed', false);
        $current = $work->fresh();
        $this->assertTrue($snapshot->updated_at->equalTo($current->updated_at));
        $this->assertTrue($snapshot->archived_at->equalTo($current->archived_at));
        $this->assertSame($snapshot->archived_from_status, $current->archived_from_status);
        $this->assertSame($snapshot->archived_from_visibility_status, $current->archived_from_visibility_status);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_restore_uses_safe_target_for_every_source_and_legacy_archive(): void
    {
        $designer = $this->userWithRole('designer');
        Sanctum::actingAs($designer);
        $cases = [
            [Work::STATUS_PUBLISHED, Work::VISIBILITY_PUBLIC, Work::STATUS_PUBLISHED, Work::VISIBILITY_PUBLIC],
            [Work::STATUS_PUBLISHED, Work::VISIBILITY_HIDDEN, Work::STATUS_PUBLISHED, Work::VISIBILITY_HIDDEN],
            [Work::STATUS_PUBLISHED, 'invalid', Work::STATUS_PUBLISHED, Work::VISIBILITY_PUBLIC],
            [Work::STATUS_HIDDEN, Work::VISIBILITY_HIDDEN, Work::STATUS_HIDDEN, Work::VISIBILITY_HIDDEN],
            [Work::STATUS_DRAFT, Work::VISIBILITY_HIDDEN, Work::STATUS_DRAFT, Work::VISIBILITY_HIDDEN],
            [Work::STATUS_CHANGES_REQUESTED, Work::VISIBILITY_HIDDEN, Work::STATUS_DRAFT, Work::VISIBILITY_HIDDEN],
            [Work::STATUS_REJECTED, Work::VISIBILITY_HIDDEN, Work::STATUS_DRAFT, Work::VISIBILITY_HIDDEN],
            [null, null, Work::STATUS_DRAFT, Work::VISIBILITY_HIDDEN],
        ];

        foreach ($cases as [$sourceStatus, $sourceVisibility, $targetStatus, $targetVisibility]) {
            $work = $this->work($designer, Work::STATUS_ARCHIVED, [
                'archived_at' => now(),
                'archived_from_status' => $sourceStatus,
                'archived_from_visibility_status' => $sourceVisibility,
                'is_featured' => false,
                'is_pinned' => false,
            ]);
            $this->patchJson($this->restoreUrl($work), $this->versionBody($work))
                ->assertOk()->assertJsonPath('data.work.status', $targetStatus)
                ->assertJsonPath('data.work.archive_state.is_archived', false);
            $restored = $work->fresh();
            $this->assertSame($targetStatus, $restored->status);
            $this->assertSame($targetVisibility, $restored->visibility_status);
            $this->assertNull($restored->archived_at);
            $this->assertNull($restored->archived_from_status);
            $this->assertNull($restored->archived_from_visibility_status);
            $this->assertFalse($restored->is_featured);
            $this->assertFalse($restored->is_pinned);
        }

        $this->assertSame(count($cases), AuditEvent::query()
            ->where('event_type', 'works.designer.restored')
            ->where('action', 'restore')
            ->where('target_type', 'work')
            ->count());
    }

    public function test_restore_rejects_non_archived_work(): void
    {
        $designer = $this->userWithRole('designer');
        $work = $this->work($designer);
        Sanctum::actingAs($designer);

        $this->patchJson($this->restoreUrl($work), $this->versionBody($work))
            ->assertConflict()
            ->assertJsonPath('data.code', 'work_not_archived')
            ->assertJsonPath('data.current_status', Work::STATUS_DRAFT);
    }

    public function test_version_conflict_returns_current_version_without_change_or_audit(): void
    {
        $designer = $this->userWithRole('designer');
        $work = $this->work($designer, Work::STATUS_PUBLISHED, ['visibility_status' => Work::VISIBILITY_PUBLIC]);
        Sanctum::actingAs($designer);

        $this->patchJson($this->archiveUrl($work), ['expected_updated_at' => '2000-01-01T00:00:00+00:00'])
            ->assertConflict()
            ->assertJsonPath('data.code', 'work_version_conflict')
            ->assertJsonPath('data.current_status', Work::STATUS_PUBLISHED)
            ->assertJsonPath('data.current_updated_at', $work->updated_at->toJSON());
        $this->assertSame(Work::STATUS_PUBLISHED, $work->fresh()->status);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_audit_uses_allowlisted_metadata_and_response_hides_source_and_private_fields(): void
    {
        $designer = $this->userWithRole('designer');
        $work = $this->work($designer, Work::STATUS_PUBLISHED, [
            'title' => 'عنوان سري للاختبار',
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'is_featured' => true,
            'is_pinned' => true,
        ]);
        Sanctum::actingAs($designer);

        $response = $this->patchJson($this->archiveUrl($work), $this->versionBody($work))->assertOk();
        $event = AuditEvent::query()->sole();
        $this->assertSame('works.designer.archived', $event->event_type);
        $this->assertSame('works', $event->category);
        $this->assertSame('notice', $event->severity);
        $this->assertSame('success', $event->outcome);
        $this->assertSame('work', $event->target_type);
        $this->assertSame('archive', $event->action);
        $this->assertEqualsCanonicalizing([
            'work_id',
            'previous_status',
            'current_status',
            'previous_visibility_status',
            'current_visibility_status',
            'archived_from_status',
            'archived_from_visibility_status',
            'featured_removed',
            'pinned_removed',
        ], array_keys($event->metadata));
        $this->assertTrue($event->metadata['featured_removed']);
        $this->assertTrue($event->metadata['pinned_removed']);
        $this->assertStringNotContainsString('عنوان سري للاختبار', json_encode($event->metadata));
        $response->assertJsonMissingPath('data.work.archived_from_status')
            ->assertJsonMissingPath('data.work.archived_from_visibility_status')
            ->assertJsonMissingPath('data.work.designer_id')
            ->assertJsonMissingPath('data.work.reviewer_id');
    }

    public function test_audit_failure_rolls_back_archive_and_restore_completely(): void
    {
        $designer = $this->userWithRole('designer');
        $archive = $this->work($designer, Work::STATUS_PUBLISHED, [
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'is_featured' => true,
            'is_pinned' => true,
        ]);
        $restore = $this->work($designer, Work::STATUS_ARCHIVED, [
            'archived_at' => now()->subHour(),
            'archived_from_status' => Work::STATUS_PUBLISHED,
            'archived_from_visibility_status' => Work::VISIBILITY_PUBLIC,
            'is_featured' => true,
            'is_pinned' => true,
        ]);
        AuditEvent::creating(static function (): void {
            throw new \RuntimeException('audit unavailable');
        });
        Sanctum::actingAs($designer);

        $this->patchJson($this->archiveUrl($archive), $this->versionBody($archive))->assertServerError();
        $this->patchJson($this->restoreUrl($restore), $this->versionBody($restore))->assertServerError();

        $archivedRollback = $archive->fresh();
        $this->assertSame(Work::STATUS_PUBLISHED, $archivedRollback->status);
        $this->assertSame(Work::VISIBILITY_PUBLIC, $archivedRollback->visibility_status);
        $this->assertNull($archivedRollback->archived_at);
        $this->assertNull($archivedRollback->archived_from_status);
        $this->assertNull($archivedRollback->archived_from_visibility_status);
        $this->assertTrue($archivedRollback->is_featured);
        $this->assertTrue($archivedRollback->is_pinned);

        $restoreRollback = $restore->fresh();
        $this->assertSame(Work::STATUS_ARCHIVED, $restoreRollback->status);
        $this->assertSame(Work::VISIBILITY_HIDDEN, $restoreRollback->visibility_status);
        $this->assertNotNull($restoreRollback->archived_at);
        $this->assertSame(Work::STATUS_PUBLISHED, $restoreRollback->archived_from_status);
        $this->assertSame(Work::VISIBILITY_PUBLIC, $restoreRollback->archived_from_visibility_status);
        $this->assertTrue($restoreRollback->is_featured);
        $this->assertTrue($restoreRollback->is_pinned);
    }

    private function work(User $designer, string $status = Work::STATUS_DRAFT, array $attributes = []): Work
    {
        return Work::factory()->create(array_merge([
            'designer_id' => $designer->id,
            'status' => $status,
        ], $attributes));
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    /** @return array{expected_updated_at: string} */
    private function versionBody(Work $work): array
    {
        return ['expected_updated_at' => $work->updated_at->toJSON()];
    }

    private function archiveUrl(Work $work): string
    {
        return "/api/designer/works/{$work->id}/archive";
    }

    private function restoreUrl(Work $work): string
    {
        return "/api/designer/works/{$work->id}/restore";
    }
}
