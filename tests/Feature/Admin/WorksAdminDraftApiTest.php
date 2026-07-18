<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksAuthoringController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkSetting;
use App\Services\Audit\AuditEventLogger;
use App\Services\Works\WorksSettingsStore;
use Carbon\Carbon;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksAdminDraftApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_post_and_patch_routes_resolve_to_authoring_actions(): void
    {
        $post = Route::getRoutes()->match(Request::create('/api/admin/works', 'POST'));
        $patch = Route::getRoutes()->match(Request::create('/api/admin/works/19', 'PATCH'));

        $this->assertSame(WorksAuthoringController::class.'@store', $post->getActionName());
        $this->assertSame(WorksAuthoringController::class.'@update', $patch->getActionName());
    }

    public function test_no_general_put_or_delete_work_routes_exist(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create();

        $this->putJson($this->endpoint($work), ['title' => 'Updated'])
            ->assertMethodNotAllowed();
        $this->deleteJson($this->endpoint($work))
            ->assertMethodNotAllowed();
    }

    public function test_unauthenticated_create_and_update_return_401(): void
    {
        $this->postJson('/api/admin/works', ['title' => 'New draft'])
            ->assertUnauthorized();
        $this->patchJson('/api/admin/works/1', ['title' => 'Updated draft'])
            ->assertUnauthorized();
    }

    public function test_client_and_designer_are_forbidden_even_with_accidental_permissions(): void
    {
        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $this->allAuthoringPermissions());

            $this->postJson('/api/admin/works', ['title' => 'New draft'])
                ->assertForbidden();

            $work = Work::factory()->create();
            $this->patchJson($this->endpoint($work), ['title' => 'Updated draft'])
                ->assertForbidden();
        }
    }

    public function test_non_internal_role_is_forbidden(): void
    {
        Role::create(['name' => 'contractor', 'guard_name' => 'web']);
        $this->actingAsRole('contractor', $this->allAuthoringPermissions());
        $work = Work::factory()->create();

        $this->postJson('/api/admin/works', ['title' => 'New draft'])
            ->assertForbidden();
        $this->patchJson($this->endpoint($work), ['title' => 'Updated draft'])
            ->assertForbidden();
    }

    public function test_admin_and_staff_require_access_and_create_for_post(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $this->actingAsRole($role, ['admin.works.access']);
            $this->postJson('/api/admin/works', ['title' => 'Missing create'])
                ->assertForbidden();

            $this->actingAsRole($role, ['admin.works.create']);
            $this->postJson('/api/admin/works', ['title' => 'Missing access'])
                ->assertForbidden();

            $this->actingAsRole($role, [
                'admin.works.access',
                'admin.works.create',
            ]);
            $this->postJson('/api/admin/works', ['title' => 'Allowed draft'])
                ->assertCreated();
        }
    }

    public function test_super_admin_creates_safe_draft_from_title_only(): void
    {
        $this->actingAsRole('super-admin');

        $response = $this->postJson('/api/admin/works', [
            'title' => '  Motion Draft  ',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'create')
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.changed_keys', ['title'])
            ->assertJsonPath('data.work.title', 'Motion Draft')
            ->assertJsonPath('data.work.status', Work::STATUS_DRAFT)
            ->assertJsonPath('data.work.visibility_status', Work::VISIBILITY_HIDDEN)
            ->assertJsonPath('message', 'تم إنشاء مسودة العمل بنجاح')
            ->assertJsonPath('errors', null);

        $work = Work::query()->findOrFail($response->json('data.work.id'));

        $this->assertSame(Work::STATUS_DRAFT, $work->status);
        $this->assertSame(Work::VISIBILITY_HIDDEN, $work->visibility_status);
        $this->assertNull($work->reviewer_id);
        $this->assertNull($work->category_id);
        $this->assertNull($work->cover_media_id);
        $this->assertFalse($work->is_featured);
        $this->assertFalse($work->is_pinned);
        $this->assertFalse($work->is_trusted_direct_publish);
        $this->assertSame(0, $work->views_count);
        $this->assertSame(0, $work->likes_count);
        $this->assertSame(0, $work->reports_count);
        $this->assertNull($work->submitted_at);
        $this->assertNull($work->reviewed_at);
        $this->assertNull($work->approved_at);
        $this->assertNull($work->published_at);
        $this->assertNull($work->rejected_at);
        $this->assertNull($work->hidden_at);
        $this->assertNull($work->archived_at);
        $this->assertNull($work->rejection_reason);
        $this->assertNull($work->change_request_notes);
    }

    public function test_create_permission_allows_basic_authoring_fields(): void
    {
        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.create',
        ]);

        $this->postJson('/api/admin/works', [
            'title' => 'Basic authoring',
            'summary' => 'Summary',
            'description' => 'Description',
            'media_type' => Work::MEDIA_TYPE_GALLERY,
        ])->assertCreated();

        $this->assertDatabaseHas('works', [
            'title' => 'Basic authoring',
            'summary' => 'Summary',
            'description' => 'Description',
            'media_type' => Work::MEDIA_TYPE_GALLERY,
        ]);
    }

    public function test_advanced_create_fields_require_their_individual_permissions(): void
    {
        $designer = $this->designer();
        $cases = [
            'price_amount' => [15.25, 'admin.works.update.pricing'],
            'delivery_days' => [14, 'admin.works.update.delivery'],
            'designer_id' => [$designer->id, 'admin.works.update.designer'],
            'internal_notes' => ['Private note', 'admin.works.update.private_notes'],
        ];

        foreach ($cases as $field => [$value, $permission]) {
            $before = Work::query()->count();
            $this->actingAsRole('admin', [
                'admin.works.access',
                'admin.works.create',
            ]);

            $this->postJson('/api/admin/works', [
                'title' => 'Unauthorized '.$field,
                $field => $value,
            ])->assertForbidden();
            $this->assertSame($before, Work::query()->count());

            $this->actingAsRole('admin', [
                'admin.works.access',
                'admin.works.create',
                $permission,
            ]);
            $this->postJson('/api/admin/works', [
                'title' => 'Authorized '.$field,
                $field => $value,
            ])->assertCreated();
        }
    }

    public function test_missing_one_advanced_permission_rejects_entire_create(): void
    {
        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.create',
            'admin.works.update.pricing',
        ]);

        $this->postJson('/api/admin/works', [
            'title' => 'Atomic rejection',
            'price_amount' => 100,
            'delivery_days' => 7,
        ])->assertForbidden();

        $this->assertDatabaseCount('works', 0);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'works.authoring.created',
        ]);
        $this->assertDatabaseMissing('audit_events', [
            'event_type' => 'works.authoring.updated',
        ]);
    }

    public function test_slug_is_server_generated_stable_and_unique_for_duplicate_titles(): void
    {
        $this->actingAsRole('super-admin');

        $first = $this->postJson('/api/admin/works', ['title' => 'Motion Identity'])
            ->assertCreated()
            ->json('data.work');
        $second = $this->postJson('/api/admin/works', ['title' => 'Motion Identity'])
            ->assertCreated()
            ->json('data.work');

        $this->assertSame('motion-identity', $first['slug']);
        $this->assertNotSame($first['slug'], $second['slug']);
        $this->assertStringStartsWith('motion-identity-', $second['slug']);

        $this->patchJson('/api/admin/works/'.$first['id'], ['title' => 'Renamed Work'])
            ->assertOk()
            ->assertJsonPath('data.work.slug', $first['slug']);
    }

    public function test_slug_uses_safe_fallback_when_title_has_no_slug_characters(): void
    {
        $this->actingAsRole('super-admin');

        $first = $this->postJson('/api/admin/works', ['title' => '@@@'])
            ->assertCreated()
            ->json('data.work.slug');
        $second = $this->postJson('/api/admin/works', ['title' => '@@@'])
            ->assertCreated()
            ->json('data.work.slug');

        $this->assertSame('work', $first);
        $this->assertStringStartsWith('work-', $second);

        $arabic = $this->postJson('/api/admin/works', ['title' => 'مشروع عربي'])
            ->assertCreated()
            ->json('data.work.slug');

        $this->assertIsString($arabic);
        $this->assertNotSame('', $arabic);
    }

    public function test_slug_and_all_system_fields_are_rejected_from_request_body(): void
    {
        $this->actingAsRole('super-admin');
        $forbiddenFields = [
            'slug',
            'status',
            'visibility_status',
            'category_id',
            'tag_ids',
            'cover_media_id',
            'reviewer_id',
            'is_featured',
            'is_pinned',
            'is_trusted_direct_publish',
            'views_count',
            'likes_count',
            'reports_count',
            'submitted_at',
            'rejection_reason',
            'change_request_notes',
            'media',
            'files',
            'metadata',
            'payload',
            'updated_by',
        ];

        foreach ($forbiddenFields as $field) {
            $this->postJson('/api/admin/works', [
                'title' => 'Rejected system field',
                $field => 'blocked',
            ])->assertUnprocessable();
        }

        $this->assertDatabaseCount('works', 0);
    }

    public function test_title_is_required_trimmed_and_enforces_length_limits(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([
            [],
            ['title' => '   '],
            ['title' => 'x'],
            ['title' => str_repeat('x', 161)],
            ['title' => ['nested']],
        ] as $payload) {
            $this->postJson('/api/admin/works', $payload)->assertUnprocessable();
        }
    }

    public function test_summary_and_description_enforce_nullable_string_limits(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([
            ['summary' => str_repeat('s', 1001)],
            ['summary' => ['nested']],
            ['description' => str_repeat('d', 30001)],
            ['description' => ['nested']],
            ['internal_notes' => str_repeat('n', 10001)],
            ['internal_notes' => ['nested']],
        ] as $fields) {
            $this->postJson('/api/admin/works', [
                'title' => 'Invalid text',
                ...$fields,
            ])->assertUnprocessable();
        }

        $this->postJson('/api/admin/works', [
            'title' => 'Nullable text',
            'summary' => null,
            'description' => null,
        ])->assertCreated();
    }

    public function test_delivery_days_requires_strict_integer_and_range(): void
    {
        $this->actingAsRole('super-admin');

        foreach (['7', 1.5, 0, 366, ['days']] as $value) {
            $this->postJson('/api/admin/works', [
                'title' => 'Invalid delivery',
                'delivery_days' => $value,
            ])->assertUnprocessable();
        }

        $this->postJson('/api/admin/works', [
            'title' => 'Valid delivery',
            'delivery_days' => 365,
        ])->assertCreated();
    }

    public function test_price_requires_strict_numeric_value_range_and_precision(): void
    {
        $this->actingAsRole('super-admin');

        foreach (['12.50', -1, 10000000000000, 1.234, ['price']] as $value) {
            $this->postJson('/api/admin/works', [
                'title' => 'Invalid price',
                'price_amount' => $value,
            ])->assertUnprocessable();
        }

        $this->postJson('/api/admin/works', [
            'title' => 'Valid price',
            'price_amount' => 12.50,
        ])->assertCreated()
            ->assertJsonPath('data.work.price_amount', '12.50');
    }

    public function test_designer_id_requires_positive_integer_and_designer_role(): void
    {
        $this->actingAsRole('super-admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        foreach (['1', 0, 999999, $admin->id] as $value) {
            $this->postJson('/api/admin/works', [
                'title' => 'Invalid designer',
                'designer_id' => $value,
            ])->assertUnprocessable();
        }

        $designer = $this->designer();
        $this->postJson('/api/admin/works', [
            'title' => 'Valid designer',
            'designer_id' => $designer->id,
        ])->assertCreated()
            ->assertJsonPath('data.work.designer_id', $designer->id);
    }

    public function test_unknown_nested_and_query_input_are_rejected(): void
    {
        $this->actingAsRole('super-admin');

        $this->postJson('/api/admin/works', [
            'title' => 'Unknown body',
            'unknown' => true,
        ])->assertUnprocessable();
        $this->postJson('/api/admin/works', [
            'title' => ['nested'],
        ])->assertUnprocessable();
        $this->postJson('/api/admin/works?preview=true', [
            'title' => 'Query rejected',
        ])->assertUnprocessable();
    }

    public function test_empty_patch_is_rejected(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create();

        $this->patchJson($this->endpoint($work), [])
            ->assertUnprocessable();
        $this->patchJson($this->endpoint($work), ['title' => null])
            ->assertUnprocessable();
        $this->patchJson($this->endpoint($work), ['unknown' => true])
            ->assertUnprocessable();
        $this->patchJson($this->endpoint($work).'?preview=true', [
            'summary' => 'Query rejected',
        ])->assertUnprocessable();
    }

    public function test_null_allowed_types_allows_all_three_media_types(): void
    {
        $this->setMediaLimits(null, null, null, 3);
        $this->actingAsRole('super-admin');

        $this->postJson('/api/admin/works', [
            'title' => 'Invalid audio',
            'media_type' => 'audio',
        ])->assertUnprocessable();

        foreach (Work::MEDIA_TYPES as $mediaType) {
            $this->postJson('/api/admin/works', [
                'title' => 'Allowed '.$mediaType,
                'media_type' => $mediaType,
            ])->assertCreated()
                ->assertJsonPath('data.authoring_policy.allowed_media_types', Work::MEDIA_TYPES);
        }
    }

    public function test_restricted_media_policy_rejects_disallowed_and_accepts_allowed_type(): void
    {
        $this->setMediaLimits(8, 4096, [Work::MEDIA_TYPE_IMAGE], 7);
        $this->actingAsRole('super-admin');

        $this->postJson('/api/admin/works', [
            'title' => 'Disallowed video',
            'media_type' => Work::MEDIA_TYPE_VIDEO,
        ])->assertUnprocessable();

        $this->postJson('/api/admin/works', [
            'title' => 'Allowed image',
            'media_type' => Work::MEDIA_TYPE_IMAGE,
        ])->assertCreated()
            ->assertJsonPath('data.authoring_policy.settings_version', 7)
            ->assertJsonPath('data.authoring_policy.allowed_media_types', ['image'])
            ->assertJsonPath('data.authoring_policy.media_limits.max_items', 8)
            ->assertJsonPath('data.authoring_policy.media_limits.max_file_size_kb', 4096)
            ->assertJsonPath('data.authoring_policy.enforcement.media_type', true)
            ->assertJsonPath('data.authoring_policy.enforcement.max_items', false)
            ->assertJsonPath('data.authoring_policy.enforcement.max_file_size_kb', false);

        $work = Work::factory()->create(['media_type' => null]);
        $this->patchJson($this->endpoint($work), [
            'media_type' => Work::MEDIA_TYPE_VIDEO,
        ])->assertUnprocessable();
        $this->patchJson($this->endpoint($work), [
            'media_type' => Work::MEDIA_TYPE_IMAGE,
        ])->assertOk();
    }

    public function test_corrupt_allowed_types_normalizes_to_all_media_types(): void
    {
        $this->setMediaLimits(5, 2048, 'corrupt', 4);
        $this->actingAsRole('super-admin');

        $this->postJson('/api/admin/works', [
            'title' => 'Normalized policy',
            'media_type' => Work::MEDIA_TYPE_GALLERY,
        ])->assertCreated()
            ->assertJsonPath('data.authoring_policy.allowed_media_types', Work::MEDIA_TYPES);
    }

    public function test_authoring_policy_is_exact_and_does_not_expose_raw_settings(): void
    {
        $this->globalSetting()->forceFill([
            'values' => [
                'review_sla_hours' => 72,
                'direct_publish_trust_enabled' => true,
                'media_limits' => [
                    'max_items' => 9,
                    'max_file_size_kb' => 5120,
                    'allowed_types' => ['image', 'gallery'],
                ],
                'secret' => 'blocked',
            ],
            'version' => 11,
            'updated_by' => User::factory()->create()->id,
        ])->save();
        $this->actingAsRole('super-admin');

        $policy = $this->postJson('/api/admin/works', [
            'title' => 'Safe policy',
            'media_type' => 'gallery',
        ])->assertCreated()->json('data.authoring_policy');

        $this->assertSame([
            'source',
            'settings_version',
            'allowed_media_types',
            'media_limits',
            'enforcement',
        ], array_keys($policy));
        $this->assertSame(['max_items', 'max_file_size_kb'], array_keys($policy['media_limits']));
        $this->assertSame(
            ['media_type', 'max_items', 'max_file_size_kb'],
            array_keys($policy['enforcement']),
        );
        $this->assertStringNotContainsString(
            'review_sla_hours',
            json_encode($policy, JSON_THROW_ON_ERROR),
        );
        $this->assertStringNotContainsString(
            'direct_publish_trust_enabled',
            json_encode($policy, JSON_THROW_ON_ERROR),
        );
    }

    public function test_settings_are_read_once_for_each_authoring_request(): void
    {
        $store = new class extends WorksSettingsStore
        {
            public int $calls = 0;

            public function getGlobalSettings(): array
            {
                $this->calls++;

                return [
                    'scope' => 'global',
                    'version' => 5,
                    'values' => [
                        'review_sla_hours' => null,
                        'direct_publish_trust_enabled' => false,
                        'media_limits' => [
                            'max_items' => null,
                            'max_file_size_kb' => null,
                            'allowed_types' => null,
                        ],
                    ],
                    'storage_record_found' => true,
                    'updated_at' => null,
                ];
            }
        };
        $this->app->instance(WorksSettingsStore::class, $store);
        $this->actingAsRole('super-admin');

        $this->postJson('/api/admin/works', ['title' => 'Single settings read'])
            ->assertCreated();

        $this->assertSame(1, $store->calls);
    }

    public function test_partial_update_preserves_unsent_fields_and_slug(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create([
            'title' => 'Original title',
            'slug' => 'stable-slug',
            'summary' => 'Original summary',
            'description' => 'Original description',
        ]);

        $this->patchJson($this->endpoint($work), [
            'title' => 'Updated title',
        ])->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.changed_keys', ['title'])
            ->assertJsonPath('data.work.slug', 'stable-slug');

        $work->refresh();
        $this->assertSame('Original summary', $work->summary);
        $this->assertSame('Original description', $work->description);
        $this->assertSame('stable-slug', $work->slug);
    }

    public function test_nullable_update_fields_can_be_cleared(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create([
            'summary' => 'Summary',
            'description' => 'Description',
            'media_type' => 'image',
            'price_amount' => 10,
            'delivery_days' => 2,
            'designer_id' => $this->designer()->id,
            'internal_notes' => 'Secret',
        ]);

        $response = $this->patchJson($this->endpoint($work), [
            'summary' => null,
            'description' => null,
            'media_type' => null,
            'price_amount' => null,
            'delivery_days' => null,
            'designer_id' => null,
            'internal_notes' => null,
        ])->assertOk();

        $this->assertEqualsCanonicalizing([
            'summary',
            'description',
            'media_type',
            'price_amount',
            'delivery_days',
            'designer_id',
            'internal_notes',
        ], $response->json('data.changed_keys'));
    }

    public function test_draft_and_changes_requested_are_editable_without_status_transition(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([Work::STATUS_DRAFT, Work::STATUS_CHANGES_REQUESTED] as $status) {
            $work = Work::factory()->create([
                'status' => $status,
                'change_request_notes' => $status === Work::STATUS_CHANGES_REQUESTED
                    ? 'Keep this request.'
                    : null,
            ]);

            $this->patchJson($this->endpoint($work), [
                'summary' => 'Updated safely',
            ])->assertOk()
                ->assertJsonPath('data.work.status', $status);

            $work->refresh();
            $this->assertSame($status, $work->status);

            if ($status === Work::STATUS_CHANGES_REQUESTED) {
                $this->assertSame('Keep this request.', $work->change_request_notes);
            }
        }
    }

    public function test_non_editable_statuses_return_safe_409_without_changes(): void
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
            $work = Work::factory()->create([
                'title' => 'Locked '.$status,
                'status' => $status,
            ]);

            $response = $this->patchJson($this->endpoint($work), [
                'title' => 'Must not change',
            ])->assertStatus(409)
                ->assertJsonPath('success', false)
                ->assertJsonPath('data', ['current_status' => $status])
                ->assertJsonPath('errors', null);

            $this->assertSame(['current_status'], array_keys($response->json('data')));
            $this->assertSame('Locked '.$status, $work->fresh()->title);
        }
    }

    public function test_patch_applies_field_scoped_permissions(): void
    {
        $designer = $this->designer();
        $cases = [
            'title' => ['Updated title', 'admin.works.update.basic'],
            'summary' => ['Updated summary', 'admin.works.update.basic'],
            'description' => ['Updated description', 'admin.works.update.basic'],
            'media_type' => ['video', 'admin.works.update.media'],
            'price_amount' => [25.50, 'admin.works.update.pricing'],
            'delivery_days' => [9, 'admin.works.update.delivery'],
            'designer_id' => [$designer->id, 'admin.works.update.designer'],
            'internal_notes' => ['Updated secret', 'admin.works.update.private_notes'],
        ];

        foreach ($cases as $field => [$value, $permission]) {
            $work = Work::factory()->create();
            $original = $work->getAttribute($field);
            $this->actingAsRole('admin', ['admin.works.access']);

            $this->patchJson($this->endpoint($work), [$field => $value])
                ->assertForbidden();
            $this->assertSame($original, $work->fresh()->getAttribute($field));

            $this->actingAsRole('admin', [
                'admin.works.access',
                $permission,
            ]);
            $this->patchJson($this->endpoint($work), [$field => $value])
                ->assertOk();
        }
    }

    public function test_create_permission_does_not_grant_update_permission(): void
    {
        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.create',
        ]);
        $work = Work::factory()->create();

        $this->patchJson($this->endpoint($work), ['title' => 'Not allowed'])
            ->assertForbidden();
    }

    public function test_missing_one_patch_permission_rejects_all_fields_atomically(): void
    {
        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.update.basic',
        ]);
        $work = Work::factory()->create([
            'title' => 'Original',
            'media_type' => 'image',
        ]);

        $this->patchJson($this->endpoint($work), [
            'title' => 'Authorized field',
            'media_type' => 'video',
        ])->assertForbidden();

        $work->refresh();
        $this->assertSame('Original', $work->title);
        $this->assertSame('image', $work->media_type);
        $this->assertSame(0, $this->authoringAuditEvents()->count());
    }

    public function test_no_op_returns_unchanged_and_preserves_updated_at_without_audit(): void
    {
        Carbon::setTestNow('2026-07-19 10:00:00');
        $this->actingAsRole('super-admin');
        $work = Work::factory()->create([
            'title' => 'Same title',
            'price_amount' => 12.50,
        ]);
        $updatedAt = $work->updated_at->toJSON();
        Carbon::setTestNow('2026-07-20 10:00:00');

        try {
            $this->patchJson($this->endpoint($work), [
                'title' => 'Same title',
                'price_amount' => 12.5,
            ])->assertOk()
                ->assertJsonPath('data.changed', false)
                ->assertJsonPath('data.changed_keys', [])
                ->assertJsonPath('message', 'لم تتغير بيانات مسودة العمل');

            $this->assertSame($updatedAt, $work->fresh()->updated_at->toJSON());
            $this->assertSame(0, $this->authoringAuditEvents()->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_work_payload_is_allowlisted_and_private_notes_require_permission(): void
    {
        $work = Work::factory()->create([
            'internal_notes' => 'Hidden internal note',
            'rejection_reason' => 'Hidden rejection',
            'change_request_notes' => 'Hidden request',
        ]);
        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.update.basic',
        ]);

        $withoutPrivateNotes = $this->patchJson($this->endpoint($work), [
            'title' => 'First update',
        ])->assertOk()->json('data.work');

        $this->assertSame([
            'id',
            'title',
            'slug',
            'summary',
            'description',
            'status',
            'visibility_status',
            'media_type',
            'price_amount',
            'delivery_days',
            'designer_id',
            'created_at',
            'updated_at',
        ], array_keys($withoutPrivateNotes));

        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.update.basic',
            'admin.works.update.private_notes',
        ]);
        $withPrivateNotes = $this->patchJson($this->endpoint($work), [
            'title' => 'Second update',
        ])->assertOk()->json('data.work');

        $this->assertSame('Hidden internal note', $withPrivateNotes['internal_notes']);

        foreach ([
            'rejection_reason',
            'change_request_notes',
            'reviewer_id',
            'views_count',
            'likes_count',
            'reports_count',
            'media',
            'disk',
            'path',
        ] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $withPrivateNotes);
        }
    }

    public function test_field_access_reflects_actual_individual_permissions(): void
    {
        $this->actingAsRole('admin', [
            'admin.works.access',
            'admin.works.create',
            'admin.works.update.basic',
            'admin.works.update.category',
            'admin.works.taxonomy.view',
            'admin.works.taxonomy.categories.view',
        ]);

        $access = $this->postJson('/api/admin/works', [
            'title' => 'Field access',
        ])->assertCreated()->json('data.field_access');

        $this->assertSame([
            'can_update_basic' => true,
            'can_update_media' => false,
            'can_update_pricing' => false,
            'can_update_delivery' => false,
            'can_update_designer' => false,
            'can_update_private_notes' => false,
            'can_assign_category' => true,
            'can_assign_tags' => false,
        ], $access);
    }

    public function test_create_and_update_record_safe_audit_events_once(): void
    {
        $this->setMediaLimits(6, 2048, ['image'], 9);
        $this->actingAsRole('super-admin');

        $workId = $this->postJson('/api/admin/works', [
            'title' => 'Audit title must stay private',
            'media_type' => 'image',
        ])->assertCreated()->json('data.work.id');
        $this->patchJson('/api/admin/works/'.$workId, [
            'summary' => 'Audit summary must stay private',
        ])->assertOk();

        $created = $this->authoringAuditEvents()
            ->where('event_type', 'works.authoring.created')
            ->sole();
        $updated = $this->authoringAuditEvents()
            ->where('event_type', 'works.authoring.updated')
            ->sole();

        $this->assertSame('works', $created->category);
        $this->assertSame('work', $created->target_type);
        $this->assertSame($workId, $created->target_id);
        $this->assertSame('create', $created->action);
        $this->assertSame('success', $created->outcome);
        $this->assertSame(
            ['status', 'changed_keys', 'settings_version', 'initial_status'],
            array_keys($created->metadata),
        );
        $this->assertSame(
            ['status', 'changed_keys', 'settings_version'],
            array_keys($updated->metadata),
        );
        $this->assertSame(9, $created->metadata['settings_version']);
        $this->assertSame(9, $updated->metadata['settings_version']);
        $this->assertSame(['title', 'media_type'], $created->metadata['changed_keys']);
        $this->assertSame(['summary'], $updated->metadata['changed_keys']);

        $serializedEvents = $created->toJson().$updated->toJson();
        $this->assertStringNotContainsString('Audit title must stay private', $serializedEvents);
        $this->assertStringNotContainsString('Audit summary must stay private', $serializedEvents);
    }

    public function test_no_op_and_403_409_422_failures_record_no_audit(): void
    {
        $this->actingAsRole('super-admin');
        $draft = Work::factory()->create(['title' => 'No-op']);
        $locked = Work::factory()->create(['status' => Work::STATUS_SUBMITTED]);

        $this->patchJson($this->endpoint($draft), ['title' => 'No-op'])
            ->assertOk();
        $this->patchJson($this->endpoint($locked), ['title' => 'Conflict'])
            ->assertStatus(409);
        $this->postJson('/api/admin/works', ['title' => 'x'])
            ->assertUnprocessable();

        $this->actingAsRole('admin', ['admin.works.access']);
        $this->patchJson($this->endpoint($draft), ['title' => 'Forbidden'])
            ->assertForbidden();

        $this->assertSame(0, $this->authoringAuditEvents()->count());
    }

    public function test_audit_failure_rolls_back_created_work_and_audit_row(): void
    {
        $this->actingAsRole('super-admin');
        $this->app->instance(AuditEventLogger::class, new class extends AuditEventLogger
        {
            public function record(array $event): AuditEvent
            {
                parent::record($event);

                throw new LogicException('Forced authoring audit failure.');
            }
        });
        $this->withoutExceptionHandling();

        try {
            $this->postJson('/api/admin/works', ['title' => 'Must roll back']);
            $this->fail('The forced audit failure was not thrown.');
        } catch (LogicException $exception) {
            $this->assertSame('Forced authoring audit failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('works', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    /** @return list<string> */
    private function allAuthoringPermissions(): array
    {
        return [
            'admin.works.access',
            'admin.works.create',
            'admin.works.update.basic',
            'admin.works.update.media',
            'admin.works.update.pricing',
            'admin.works.update.delivery',
            'admin.works.update.designer',
            'admin.works.update.private_notes',
        ];
    }

    /**
     * @param list<string> $permissions
     */
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

    private function designer(): User
    {
        $designer = User::factory()->create();
        $designer->assignRole('designer');

        return $designer;
    }

    private function endpoint(Work $work): string
    {
        return '/api/admin/works/'.$work->id;
    }

    private function globalSetting(): WorkSetting
    {
        return WorkSetting::query()->where('scope', WorkSetting::SCOPE_GLOBAL)->firstOrFail();
    }

    private function setMediaLimits(
        mixed $maxItems,
        mixed $maxFileSizeKb,
        mixed $allowedTypes,
        int $version,
    ): void {
        $this->globalSetting()->forceFill([
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

    private function authoringAuditEvents(): Builder
    {
        return AuditEvent::query()->whereIn('event_type', [
            'works.authoring.created',
            'works.authoring.updated',
        ]);
    }
}
