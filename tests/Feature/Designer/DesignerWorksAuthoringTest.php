<?php

namespace Tests\Feature\Designer;

use App\Http\Controllers\Api\DesignerWorksAuthoringController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkSetting;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesignerWorksAuthoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthRolesSeeder::class);
    }

    public function test_routes_resolve_to_designer_authoring_controller(): void
    {
        $routes = [
            ['/api/designer/works', 'POST', 'store'],
            ['/api/designer/works/9/authoring', 'GET', 'show'],
            ['/api/designer/works/9', 'PATCH', 'update'],
        ];

        foreach ($routes as [$uri, $method, $action]) {
            $route = Route::getRoutes()->match(Request::create($uri, $method));
            $this->assertSame(DesignerWorksAuthoringController::class.'@'.$action, $route->getActionName());
        }
    }

    public function test_no_designer_put_or_delete_route_exists(): void
    {
        $work = Work::factory()->create();
        Sanctum::actingAs($this->designer());
        $this->putJson($this->endpoint($work), ['title' => 'No'])->assertMethodNotAllowed();
        $this->deleteJson($this->endpoint($work))->assertMethodNotAllowed();
    }

    public function test_guest_cannot_create_show_or_update(): void
    {
        $this->postJson('/api/designer/works', ['title' => 'Draft'])->assertUnauthorized();
        $this->getJson('/api/designer/works/1/authoring')->assertUnauthorized();
        $this->patchJson('/api/designer/works/1', ['title' => 'Draft'])->assertUnauthorized();
    }

    public function test_client_staff_and_disabled_designer_cannot_access_authoring(): void
    {
        foreach (['client', 'staff'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            Sanctum::actingAs($user);
            $this->postJson('/api/designer/works', ['title' => 'Draft'])->assertForbidden();
            $this->getJson('/api/designer/works/1/authoring')->assertForbidden();
            $this->patchJson('/api/designer/works/1', ['title' => 'Draft'])->assertForbidden();
        }

        $disabled = $this->designer(['disabled_at' => now()]);
        Sanctum::actingAs($disabled);
        $this->postJson('/api/designer/works', ['title' => 'Draft'])->assertForbidden();
        $this->getJson('/api/designer/works/1/authoring')->assertForbidden();
        $this->patchJson('/api/designer/works/1', ['title' => 'Draft'])->assertForbidden();
    }

    public function test_designer_creates_own_safe_draft_and_server_slug(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);
        $response = $this->postJson('/api/designer/works', ['title' => '  Motion Draft  '])
            ->assertCreated()
            ->assertJsonPath('data.work.title', 'Motion Draft')
            ->assertJsonPath('data.work.status', Work::STATUS_DRAFT)
            ->assertJsonPath('data.work.visibility_status', Work::VISIBILITY_HIDDEN)
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('message', 'تم إنشاء مسودة العمل بنجاح.');

        $work = Work::query()->findOrFail($response->json('data.work.id'));
        $this->assertSame($designer->id, $work->designer_id);
        $this->assertNotSame('', $work->slug);
        $this->assertNull($work->reviewer_id);
        $this->assertNull($work->category_id);
        $this->assertNull($work->cover_media_id);
        $this->assertFalse($work->is_featured);
        $this->assertFalse($work->is_pinned);
        $this->assertSame(0, $work->views_count);
    }

    public function test_designer_id_is_always_authenticated_user_and_body_override_is_rejected(): void
    {
        $designer = $this->designer();
        $other = $this->designer();
        Sanctum::actingAs($designer);
        $this->postJson('/api/designer/works', [
            'title' => 'Owned',
            'designer_id' => $other->id,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('works', 0);
    }

    public function test_multi_role_and_super_admin_designer_create_for_self_only(): void
    {
        foreach ([['client'], ['super-admin']] as $extraRoles) {
            $user = $this->designer();
            $user->assignRole($extraRoles);
            Sanctum::actingAs($user);
            $id = $this->postJson('/api/designer/works', ['title' => 'Owned'])
                ->assertCreated()->json('data.work.id');
            $this->assertDatabaseHas('works', ['id' => $id, 'designer_id' => $user->id]);
        }
    }

    public function test_duplicate_titles_generate_unique_slugs(): void
    {
        Sanctum::actingAs($this->designer());
        $first = $this->postJson('/api/designer/works', ['title' => 'Same Title'])
            ->assertCreated()->json('data.work.slug');
        $second = $this->postJson('/api/designer/works', ['title' => 'Same Title'])
            ->assertCreated()->json('data.work.slug');
        $this->assertNotSame($first, $second);
    }

    public function test_create_accepts_all_allowed_basic_fields(): void
    {
        Sanctum::actingAs($this->designer());
        $this->postJson('/api/designer/works', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.work.summary', 'ملخص')
            ->assertJsonPath('data.work.description', 'وصف')
            ->assertJsonPath('data.work.media_type', Work::MEDIA_TYPE_GALLERY)
            ->assertJsonPath('data.work.price_amount', '12.50')
            ->assertJsonPath('data.work.delivery_days', 7);
    }

    public function test_system_ownership_unknown_nested_and_query_fields_are_rejected(): void
    {
        Sanctum::actingAs($this->designer());
        $fields = [
            'id', 'slug', 'status', 'visibility_status', 'designer_id', 'reviewer_id',
            'category_id', 'cover_media_id', 'internal_notes', 'change_request_notes',
            'rejection_reason', 'is_featured', 'is_pinned', 'views_count', 'media', 'tags',
            'unknown',
        ];
        foreach ($fields as $field) {
            $this->postJson('/api/designer/works', ['title' => 'Draft', $field => 'blocked'])
                ->assertUnprocessable();
        }
        $this->postJson('/api/designer/works?preview=1', ['title' => 'Draft'])
            ->assertUnprocessable();
        $this->postJson('/api/designer/works', ['title' => ['nested']])
            ->assertUnprocessable();
    }

    public function test_text_price_delivery_and_media_validation_match_admin_contract(): void
    {
        Sanctum::actingAs($this->designer());
        $this->postJson('/api/designer/works', ['title' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.title.0', 'عنوان العمل مطلوب.');

        foreach ([' ', 'x', str_repeat('x', 161)] as $title) {
            $this->postJson('/api/designer/works', ['title' => $title])->assertUnprocessable();
        }
        foreach ([
            ['summary' => str_repeat('s', 1001)],
            ['description' => str_repeat('d', 30001)],
            ['price_amount' => '12.5'],
            ['price_amount' => -1],
            ['price_amount' => 1.234],
            ['delivery_days' => '7'],
            ['delivery_days' => 0],
            ['delivery_days' => 366],
            ['media_type' => 'audio'],
        ] as $fields) {
            $this->postJson('/api/designer/works', ['title' => 'Valid', ...$fields])
                ->assertUnprocessable();
        }
    }

    public function test_media_type_honors_current_settings(): void
    {
        $this->restrictMediaTypes([Work::MEDIA_TYPE_IMAGE]);
        Sanctum::actingAs($this->designer());
        $this->postJson('/api/designer/works', [
            'title' => 'Video',
            'media_type' => Work::MEDIA_TYPE_VIDEO,
        ])->assertUnprocessable();
        $this->postJson('/api/designer/works', [
            'title' => 'Image',
            'media_type' => Work::MEDIA_TYPE_IMAGE,
        ])->assertCreated();
    }

    public function test_create_response_is_allowlisted_and_private_safe(): void
    {
        Sanctum::actingAs($this->designer());
        $work = $this->postJson('/api/designer/works', ['title' => 'Safe'])
            ->assertCreated()->json('data.work');
        $this->assertSame([
            'id', 'public_code', 'title', 'slug', 'summary', 'description', 'status', 'visibility_status',
            'media_type', 'price_amount', 'delivery_days', 'category_id', 'cover_media_id',
            'created_at', 'updated_at',
        ], array_keys($work));
        $this->assertArrayNotHasKey('designer_id', $work);
        $this->assertArrayNotHasKey('internal_notes', $work);
    }

    public function test_designer_can_show_own_work_and_foreign_show_returns_404(): void
    {
        $designer = $this->designer();
        $own = Work::factory()->create(['designer_id' => $designer->id]);
        $foreign = Work::factory()->create(['designer_id' => $this->designer()->id]);
        Sanctum::actingAs($designer);
        $this->getJson("/api/designer/works/{$own->id}/authoring")
            ->assertOk()->assertJsonPath('data.work.id', $own->id);
        $this->getJson("/api/designer/works/{$foreign->id}/authoring")->assertNotFound();
    }

    public function test_show_excludes_private_admin_and_ownership_fields(): void
    {
        $designer = $this->designer();
        $work = Work::factory()->create([
            'designer_id' => $designer->id,
            'internal_notes' => 'secret',
        ]);
        Sanctum::actingAs($designer);
        $json = $this->getJson("/api/designer/works/{$work->id}/authoring")
            ->assertOk()->json('data.work');
        foreach (['designer_id', 'reviewer_id', 'internal_notes', 'change_request_notes', 'views_count'] as $key) {
            $this->assertArrayNotHasKey($key, $json);
        }
    }

    public function test_designer_updates_draft_and_changes_requested_work(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);
        foreach ([Work::STATUS_DRAFT, Work::STATUS_CHANGES_REQUESTED] as $status) {
            $work = Work::factory()->create(['designer_id' => $designer->id, 'status' => $status]);
            $this->patchJson($this->endpoint($work), ['title' => 'Updated'])
                ->assertOk()
                ->assertJsonPath('data.work.title', 'Updated')
                ->assertJsonPath('data.work.status', $status);
        }
    }

    public function test_non_editable_owned_statuses_return_409(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);
        $statuses = [
            Work::STATUS_SUBMITTED, Work::STATUS_IN_REVIEW, Work::STATUS_APPROVED,
            Work::STATUS_PUBLISHED, Work::STATUS_REJECTED, Work::STATUS_HIDDEN,
            Work::STATUS_ARCHIVED,
        ];
        foreach ($statuses as $status) {
            $work = Work::factory()->create(['designer_id' => $designer->id, 'status' => $status]);
            $this->patchJson($this->endpoint($work), ['title' => 'Blocked'])
                ->assertStatus(409)
                ->assertJsonPath('data.current_status', $status)
                ->assertJsonPath('message', 'لا يمكن تعديل العمل في حالته الحالية.');
            $this->assertSame($status, $work->fresh()->status);
        }
    }

    public function test_foreign_update_returns_404(): void
    {
        Sanctum::actingAs($this->designer());
        $foreign = Work::factory()->create(['designer_id' => $this->designer()->id]);
        $this->patchJson($this->endpoint($foreign), ['title' => 'Blocked'])->assertNotFound();
    }

    public function test_partial_update_preserves_unsent_fields_and_slug(): void
    {
        $designer = $this->designer();
        $work = Work::factory()->create([
            'designer_id' => $designer->id,
            'title' => 'Original',
            'summary' => 'Keep',
            'slug' => 'stable-slug',
        ]);
        Sanctum::actingAs($designer);
        $this->patchJson($this->endpoint($work), ['title' => 'Changed'])->assertOk();
        $work->refresh();
        $this->assertSame('Keep', $work->summary);
        $this->assertSame('stable-slug', $work->slug);
    }

    public function test_nullable_fields_can_be_cleared_and_empty_patch_is_rejected(): void
    {
        $designer = $this->designer();
        $work = Work::factory()->create([
            'designer_id' => $designer->id,
            'summary' => 'Clear',
            'description' => 'Clear',
            'media_type' => Work::MEDIA_TYPE_IMAGE,
            'price_amount' => 10,
            'delivery_days' => 4,
        ]);
        Sanctum::actingAs($designer);
        $this->patchJson($this->endpoint($work), [
            'summary' => null,
            'description' => null,
            'media_type' => null,
            'price_amount' => null,
            'delivery_days' => null,
        ])->assertOk();
        $this->assertDatabaseHas('works', [
            'id' => $work->id,
            'summary' => null,
            'description' => null,
            'media_type' => null,
            'price_amount' => null,
            'delivery_days' => null,
        ]);
        $this->patchJson($this->endpoint($work), [])->assertUnprocessable();
    }

    public function test_no_op_preserves_updated_at_and_writes_no_audit(): void
    {
        $designer = $this->designer();
        $work = Work::factory()->create(['designer_id' => $designer->id, 'title' => 'Same']);
        $updatedAt = $work->updated_at;
        Sanctum::actingAs($designer);
        $this->patchJson($this->endpoint($work), ['title' => 'Same'])
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.changed_keys', []);
        $this->assertTrue($updatedAt->equalTo($work->fresh()->updated_at));
        $this->assertSame(0, AuditEvent::query()->where('event_type', 'works.authoring.updated')->count());
    }

    public function test_failed_validation_makes_no_changes(): void
    {
        $designer = $this->designer();
        $work = Work::factory()->create(['designer_id' => $designer->id, 'title' => 'Original']);
        Sanctum::actingAs($designer);
        $this->patchJson($this->endpoint($work), ['title' => 'x'])->assertUnprocessable();
        $this->assertSame('Original', $work->fresh()->title);
    }

    public function test_changed_create_and_update_write_safe_audit_but_failures_do_not(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);
        $id = $this->postJson('/api/designer/works', ['title' => 'Created'])
            ->assertCreated()->json('data.work.id');
        $this->assertSame(1, AuditEvent::query()->where('event_type', 'works.authoring.created')->count());
        $this->patchJson("/api/designer/works/{$id}", ['title' => 'Changed'])->assertOk();
        $this->assertSame(1, AuditEvent::query()->where('event_type', 'works.authoring.updated')->count());

        $before = AuditEvent::query()->count();
        $this->patchJson("/api/designer/works/{$id}", ['title' => 'x'])->assertUnprocessable();
        $this->patchJson('/api/designer/works/999999', ['title' => 'Missing'])->assertNotFound();
        $this->assertSame($before, AuditEvent::query()->count());
    }

    public function test_designer_remains_denied_from_admin_authoring_routes(): void
    {
        $designer = $this->designer();
        $work = Work::factory()->create(['designer_id' => $designer->id]);
        Sanctum::actingAs($designer);
        $this->postJson('/api/admin/works', ['title' => 'Blocked'])->assertForbidden();
        $this->getJson("/api/admin/works/{$work->id}/authoring")->assertForbidden();
        $this->patchJson("/api/admin/works/{$work->id}", ['title' => 'Blocked'])->assertForbidden();
    }

    public function test_public_code_is_generated_unique_exposed_and_immutable(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);

        $first = $this->postJson('/api/designer/works', ['title' => 'First'])
            ->assertCreated();
        $second = $this->postJson('/api/designer/works', ['title' => 'Second'])
            ->assertCreated();
        $firstCode = $first->json('data.work.public_code');
        $secondCode = $second->json('data.work.public_code');

        $this->assertMatchesRegularExpression(
            '/^YM-W-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{10}$/',
            $firstCode,
        );
        $this->assertNotSame($firstCode, $secondCode);

        $work = Work::query()->findOrFail($first->json('data.work.id'));
        $this->getJson("/api/designer/works/{$work->id}/authoring")
            ->assertOk()->assertJsonPath('data.work.public_code', $firstCode);
        $this->patchJson($this->endpoint($work), ['title' => 'Changed'])
            ->assertOk()->assertJsonPath('data.work.public_code', $firstCode);
        $this->assertSame($firstCode, $work->fresh()->public_code);
    }

    public function test_public_code_is_rejected_from_designer_store_and_update_payloads(): void
    {
        $designer = $this->designer();
        Sanctum::actingAs($designer);
        $this->postJson('/api/designer/works', [
            'title' => 'Blocked',
            'public_code' => 'YM-W-AAAAAAAAAA',
        ])->assertUnprocessable();

        $work = Work::factory()->create(['designer_id' => $designer->id]);
        $this->patchJson($this->endpoint($work), [
            'public_code' => 'YM-W-BBBBBBBBBB',
        ])->assertUnprocessable();
    }

    private function designer(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('designer');

        return $user;
    }

    private function endpoint(Work $work): string
    {
        return "/api/designer/works/{$work->id}";
    }

    private function payload(): array
    {
        return [
            'title' => 'عمل جديد',
            'summary' => 'ملخص',
            'description' => 'وصف',
            'media_type' => Work::MEDIA_TYPE_GALLERY,
            'price_amount' => 12.50,
            'delivery_days' => 7,
        ];
    }

    private function restrictMediaTypes(array $types): void
    {
        WorkSetting::query()->updateOrCreate(
            ['scope' => 'global'],
            [
                'values' => ['media_limits' => ['allowed_types' => $types]],
                'version' => 1,
                'updated_by' => User::factory()->create()->id,
            ],
        );
    }
}
