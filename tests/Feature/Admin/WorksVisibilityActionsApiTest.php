<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\WorksShowController;
use App\Http\Controllers\Api\Admin\WorksVisibilityActionController;
use App\Http\Controllers\Api\Admin\WorksVisibilityController;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Work;
use Database\Seeders\AuthRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorksVisibilityActionsApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private const ACTION_PERMISSIONS = [
        'publish' => 'admin.works.publish',
        'unpublish' => 'admin.works.unpublish',
        'hide' => 'admin.works.hide',
        'restore' => 'admin.works.restore_visibility',
        'feature' => 'admin.works.feature',
        'unfeature' => 'admin.works.unfeature',
        'pin' => 'admin.works.pin',
        'unpin' => 'admin.works.unpin',
    ];

    /** @var array<string, string> */
    private const AUDIT_EVENT_TYPES = [
        'publish' => 'works.visibility.published',
        'unpublish' => 'works.visibility.unpublished',
        'hide' => 'works.visibility.hidden',
        'restore' => 'works.visibility.restored',
        'feature' => 'works.visibility.featured',
        'unfeature' => 'works.visibility.unfeatured',
        'pin' => 'works.visibility.pinned',
        'unpin' => 'works.visibility.unpinned',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthRolesSeeder::class);
    }

    public function test_unauthenticated_requests_get_401(): void
    {
        foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
            $work = $this->workForAction($action);

            $this->patchJson($this->actionUrl($work, $action))->assertUnauthorized();
        }
    }

    public function test_super_admin_can_execute_every_visibility_action(): void
    {
        $this->actingAsRole('super-admin');

        foreach (array_keys(self::ACTION_PERMISSIONS) as $action) {
            $work = $this->workForAction($action);

            $this->patchJson($this->actionUrl($work, $action))
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.action', $action)
                ->assertJsonPath('data.changed', true);
        }

        $this->assertSame(
            collect(self::AUDIT_EVENT_TYPES)->values()->sort()->values()->all(),
            AuditEvent::query()->pluck('event_type')->sort()->values()->all(),
        );
    }

    public function test_admin_and_staff_without_permissions_or_with_access_only_get_403(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $work = Work::factory()->approved()->create();
            $this->actingAsRole($role);
            $this->patchJson($this->actionUrl($work, 'publish'))->assertForbidden();

            $this->actingAsRole($role, ['admin.works.access']);
            $this->patchJson($this->actionUrl($work, 'publish'))->assertForbidden();
        }
    }

    public function test_admin_and_staff_require_the_exact_action_permission(): void
    {
        foreach (['admin', 'staff'] as $role) {
            foreach (self::ACTION_PERMISSIONS as $action => $permission) {
                $work = $this->workForAction($action);
                $this->actingAsRole($role, ['admin.works.access', $permission]);

                $this->patchJson($this->actionUrl($work, $action))->assertOk();

                $work = $this->workForAction($action);
                $wrongPermission = $permission === 'admin.works.publish'
                    ? 'admin.works.hide'
                    : 'admin.works.publish';
                $this->actingAsRole($role, ['admin.works.access', $wrongPermission]);

                $this->patchJson($this->actionUrl($work, $action))->assertForbidden();
            }
        }
    }

    public function test_client_designer_and_non_internal_roles_are_always_forbidden(): void
    {
        $permissions = ['admin.works.access', ...array_values(self::ACTION_PERMISSIONS)];

        foreach (['client', 'designer'] as $role) {
            $this->actingAsRole($role, $permissions);
            $work = Work::factory()->approved()->create();

            $this->patchJson($this->actionUrl($work, 'publish'))->assertForbidden();
        }

        Role::create(['name' => 'contractor', 'guard_name' => 'web']);
        $this->actingAsRole('contractor', $permissions);
        $work = Work::factory()->approved()->create();

        $this->patchJson($this->actionUrl($work, 'publish'))->assertForbidden();
    }

    public function test_publish_changes_approved_and_hidden_works_to_published_public(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([Work::STATUS_APPROVED, Work::STATUS_HIDDEN] as $status) {
            $publishedAt = now()->subDay()->startOfSecond();
            $work = Work::factory()->create([
                'status' => $status,
                'visibility_status' => Work::VISIBILITY_HIDDEN,
                'published_at' => $publishedAt,
                'is_featured' => true,
                'is_pinned' => true,
            ]);

            $this->patchJson($this->actionUrl($work, 'publish'))
                ->assertOk()
                ->assertJsonPath('data.changed', true)
                ->assertJsonPath('data.work.status', Work::STATUS_PUBLISHED)
                ->assertJsonPath('data.work.visibility_status', Work::VISIBILITY_PUBLIC)
                ->assertJsonPath('data.work.is_featured', true)
                ->assertJsonPath('data.work.is_pinned', true);

            $work->refresh();
            $this->assertTrue($work->published_at->equalTo($publishedAt));
        }
    }

    public function test_publish_is_idempotent_and_rejects_invalid_statuses_without_changes(): void
    {
        $this->actingAsRole('super-admin');
        $published = Work::factory()->published()->create();
        $updatedAt = $published->updated_at;

        $this->patchJson($this->actionUrl($published, 'publish'))
            ->assertOk()
            ->assertJsonPath('data.changed', false);
        $this->assertTrue($published->fresh()->updated_at->equalTo($updatedAt));

        foreach ($this->publishRejectedStatuses() as $status) {
            $work = Work::factory()->create(['status' => $status]);
            $original = $work->only(['status', 'visibility_status', 'is_featured', 'is_pinned']);

            $this->patchJson($this->actionUrl($work, 'publish'))->assertUnprocessable();

            $this->assertSame($original, $work->fresh()->only(array_keys($original)));
        }
    }

    public function test_unpublish_changes_published_to_approved_hidden_and_preserves_promotion_and_publish_time(): void
    {
        $this->actingAsRole('super-admin');
        $publishedAt = now()->subDays(2)->startOfSecond();
        $work = Work::factory()->published()->create([
            'published_at' => $publishedAt,
            'is_featured' => true,
            'is_pinned' => true,
        ]);

        $this->patchJson($this->actionUrl($work, 'unpublish'))
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.work.status', Work::STATUS_APPROVED)
            ->assertJsonPath('data.work.visibility_status', Work::VISIBILITY_HIDDEN)
            ->assertJsonPath('data.work.is_featured', true)
            ->assertJsonPath('data.work.is_pinned', true);

        $work->refresh();
        $this->assertNotNull($work->hidden_at);
        $this->assertTrue($work->published_at->equalTo($publishedAt));
    }

    public function test_unpublish_is_idempotent_and_rejects_invalid_statuses(): void
    {
        $this->actingAsRole('super-admin');
        $approved = Work::factory()->approved()->create();

        $this->patchJson($this->actionUrl($approved, 'unpublish'))
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        foreach ([
            Work::STATUS_DRAFT,
            Work::STATUS_SUBMITTED,
            Work::STATUS_IN_REVIEW,
            Work::STATUS_CHANGES_REQUESTED,
            Work::STATUS_REJECTED,
            Work::STATUS_HIDDEN,
            Work::STATUS_ARCHIVED,
        ] as $status) {
            $work = Work::factory()->create(['status' => $status]);
            $this->patchJson($this->actionUrl($work, 'unpublish'))->assertUnprocessable();
        }
    }

    public function test_hide_changes_non_archived_work_is_idempotent_and_rejects_archived(): void
    {
        $this->actingAsRole('super-admin');

        foreach (array_diff($this->allStatuses(), [Work::STATUS_HIDDEN, Work::STATUS_ARCHIVED]) as $status) {
            $work = Work::factory()->create([
                'status' => $status,
                'is_featured' => true,
                'is_pinned' => true,
            ]);

            $this->patchJson($this->actionUrl($work, 'hide'))
                ->assertOk()
                ->assertJsonPath('data.changed', true)
                ->assertJsonPath('data.work.status', Work::STATUS_HIDDEN)
                ->assertJsonPath('data.work.visibility_status', Work::VISIBILITY_HIDDEN)
                ->assertJsonPath('data.work.is_featured', true)
                ->assertJsonPath('data.work.is_pinned', true);
            $this->assertNotNull($work->fresh()->hidden_at);
        }

        $work = Work::factory()->hidden()->create();
        $this->patchJson($this->actionUrl($work, 'hide'))
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $archived = Work::factory()->archived()->create();
        $this->patchJson($this->actionUrl($archived, 'hide'))->assertUnprocessable();
    }

    public function test_restore_changes_supported_hidden_states_preserves_history_and_is_idempotent(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([Work::STATUS_HIDDEN, Work::STATUS_APPROVED, Work::STATUS_PUBLISHED] as $status) {
            $hiddenAt = now()->subHour()->startOfSecond();
            $work = Work::factory()->create([
                'status' => $status,
                'visibility_status' => Work::VISIBILITY_HIDDEN,
                'published_at' => null,
                'hidden_at' => $hiddenAt,
            ]);

            $this->patchJson($this->actionUrl($work, 'restore'))
                ->assertOk()
                ->assertJsonPath('data.changed', true)
                ->assertJsonPath('data.work.status', Work::STATUS_PUBLISHED)
                ->assertJsonPath('data.work.visibility_status', Work::VISIBILITY_PUBLIC);

            $work->refresh();
            $this->assertNotNull($work->published_at);
            $this->assertTrue($work->hidden_at->equalTo($hiddenAt));
        }

        $published = Work::factory()->published()->create();
        $this->patchJson($this->actionUrl($published, 'restore'))
            ->assertOk()
            ->assertJsonPath('data.changed', false);
    }

    public function test_restore_rejects_unsupported_and_archived_statuses(): void
    {
        $this->actingAsRole('super-admin');

        foreach ([
            Work::STATUS_DRAFT,
            Work::STATUS_SUBMITTED,
            Work::STATUS_IN_REVIEW,
            Work::STATUS_CHANGES_REQUESTED,
            Work::STATUS_REJECTED,
            Work::STATUS_ARCHIVED,
        ] as $status) {
            $work = Work::factory()->create([
                'status' => $status,
                'visibility_status' => Work::VISIBILITY_HIDDEN,
            ]);

            $this->patchJson($this->actionUrl($work, 'restore'))->assertUnprocessable();
        }
    }

    public function test_feature_and_pin_require_published_public_and_are_idempotent(): void
    {
        $this->actingAsRole('super-admin');

        foreach (['feature' => 'is_featured', 'pin' => 'is_pinned'] as $action => $field) {
            $work = Work::factory()->published()->create([$field => false]);
            $this->patchJson($this->actionUrl($work, $action))
                ->assertOk()
                ->assertJsonPath('data.changed', true)
                ->assertJsonPath("data.work.{$field}", true);

            $this->patchJson($this->actionUrl($work, $action))
                ->assertOk()
                ->assertJsonPath('data.changed', false);

            foreach ([
                ['status' => Work::STATUS_APPROVED, 'visibility_status' => Work::VISIBILITY_HIDDEN],
                ['status' => Work::STATUS_PUBLISHED, 'visibility_status' => Work::VISIBILITY_HIDDEN],
                ['status' => Work::STATUS_ARCHIVED, 'visibility_status' => Work::VISIBILITY_HIDDEN],
            ] as $state) {
                $invalid = Work::factory()->create($state);
                $this->patchJson($this->actionUrl($invalid, $action))->assertUnprocessable();
            }
        }
    }

    public function test_unfeature_and_unpin_work_on_any_status_including_archived_and_are_idempotent(): void
    {
        $this->actingAsRole('super-admin');

        foreach (['unfeature' => 'is_featured', 'unpin' => 'is_pinned'] as $action => $field) {
            foreach ($this->allStatuses() as $status) {
                $work = Work::factory()->create(['status' => $status, $field => true]);

                $this->patchJson($this->actionUrl($work, $action))
                    ->assertOk()
                    ->assertJsonPath('data.changed', true)
                    ->assertJsonPath("data.work.{$field}", false);
                $this->assertDatabaseHas('works', ['id' => $work->id]);

                $this->patchJson($this->actionUrl($work, $action))
                    ->assertOk()
                    ->assertJsonPath('data.changed', false);
            }
        }
    }

    public function test_response_shape_is_safe_and_visibility_flags_are_correct(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->approved()->create([
            'title' => 'Safe work',
            'slug' => 'safe-work',
            'summary' => 'Safe summary',
            'description' => 'private-description-marker',
            'internal_notes' => 'private-notes-marker',
            'rejection_reason' => 'private-rejection-marker',
            'change_request_notes' => 'private-change-marker',
            'is_featured' => true,
            'reports_count' => 3,
        ]);

        $response = $this->patchJson($this->actionUrl($work, 'publish'))
            ->assertOk()
            ->assertJsonPath('message', 'تم تنفيذ إجراء الظهور بنجاح')
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.work.visibility_flags', [
                'is_public' => true,
                'is_hidden' => false,
                'is_promoted' => true,
                'has_reports' => true,
            ]);

        $this->assertSame(
            [
                'category_id', 'created_at', 'hidden_at', 'id', 'is_featured', 'is_pinned',
                'likes_count', 'media_type', 'published_at', 'reports_count', 'slug', 'status',
                'summary', 'title', 'updated_at', 'views_count', 'visibility_flags', 'visibility_status',
            ],
            collect(array_keys($response->json('data.work')))->sort()->values()->all(),
        );

        $keys = $this->recursiveKeys($response->json());
        foreach ([
            'description', 'internal_notes', 'rejection_reason', 'change_request_notes',
            'email', 'password', 'token', 'cookie', 'metadata', 'payload', 'user', 'users',
            'designer', 'reviewer', 'model', 'rows',
        ] as $forbiddenKey) {
            $this->assertNotContains($forbiddenKey, $keys);
        }

        foreach (['private-description-marker', 'private-notes-marker', 'private-rejection-marker', 'private-change-marker'] as $marker) {
            $this->assertStringNotContainsString($marker, $response->getContent());
        }
    }

    public function test_query_body_and_sensitive_parameters_return_422(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->approved()->create();

        $this->patchJson($this->actionUrl($work, 'publish').'?unexpected=value')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unexpected');

        $this->patchJson($this->actionUrl($work, 'publish').'?metadata=blocked')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metadata');

        $this->patchJson($this->actionUrl($work, 'publish'), ['unexpected' => 'value'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unexpected');

        foreach ([
            'email', 'password', 'token', 'cookie', 'internal_notes', 'rejection_reason',
            'change_request_notes', 'payload', 'metadata', 'description', 'summary', 'delete',
            'force_delete', 'hard_delete', 'approve', 'reject', 'request_changes', 'archive',
            'restore_archive', 'bulk', 'order',
        ] as $parameter) {
            $this->patchJson($this->actionUrl($work, 'publish'), [$parameter => 'blocked'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors($parameter);
        }
    }

    public function test_missing_work_returns_404_and_read_routes_remain_intact(): void
    {
        $this->actingAsRole('super-admin');

        $this->patchJson('/api/admin/works/999999/visibility/publish')->assertNotFound();
        $this->patchJson('/api/admin/works/not-a-number/visibility/publish')->assertNotFound();

        $visibilityRoute = Route::getRoutes()->match(Request::create('/api/admin/works/visibility', 'GET'));
        $showRoute = Route::getRoutes()->match(Request::create('/api/admin/works/1', 'GET'));
        $actionRoute = Route::getRoutes()->match(Request::create('/api/admin/works/1/visibility/publish', 'PATCH'));

        $this->assertSame(WorksVisibilityController::class.'@index', $visibilityRoute->getActionName());
        $this->assertSame(WorksShowController::class.'@show', $showRoute->getActionName());
        $this->assertSame(WorksVisibilityActionController::class.'@publish', $actionRoute->getActionName());
    }

    public function test_only_patch_visibility_action_routes_exist(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/admin/works/{work}/visibility/'));

        $this->assertCount(8, $routes);
        $this->assertSame(
            collect(array_keys(self::ACTION_PERMISSIONS))->sort()->values()->all(),
            $routes->map(fn ($route): string => str($route->uri())->afterLast('/')->toString())->sort()->values()->all(),
        );

        foreach ($routes as $route) {
            $this->assertSame(['PATCH'], $route->methods());
            $this->assertSame('[0-9]+', $route->wheres['work'] ?? null);
        }
    }

    public function test_audit_is_recorded_only_for_changed_actions_with_safe_metadata(): void
    {
        $this->actingAsRole('super-admin');
        $work = Work::factory()->approved()->create([
            'title' => 'private-title-marker',
            'slug' => 'private-slug-marker',
            'summary' => 'private-summary-marker',
            'description' => 'private-description-marker',
        ]);

        $this->patchJson($this->actionUrl($work, 'publish'))->assertOk();
        $this->patchJson($this->actionUrl($work, 'publish'))
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $events = AuditEvent::query()
            ->where('target_type', 'work')
            ->where('target_id', $work->id)
            ->where('event_type', 'works.visibility.published')
            ->get();

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame([
            'work_id',
            'action',
            'old_status',
            'new_status',
            'old_visibility_status',
            'new_visibility_status',
            'old_is_featured',
            'new_is_featured',
            'old_is_pinned',
            'new_is_pinned',
        ], array_keys($event->metadata));
        $this->assertSame('publish', $event->metadata['action']);
        $this->assertSame(Work::STATUS_APPROVED, $event->metadata['old_status']);
        $this->assertSame(Work::STATUS_PUBLISHED, $event->metadata['new_status']);

        $auditJson = json_encode($event->metadata, JSON_THROW_ON_ERROR);
        foreach (['private-title-marker', 'private-slug-marker', 'private-summary-marker', 'private-description-marker'] as $marker) {
            $this->assertStringNotContainsString($marker, $auditJson);
        }
    }

    /** @return list<string> */
    private function publishRejectedStatuses(): array
    {
        return [
            Work::STATUS_DRAFT,
            Work::STATUS_SUBMITTED,
            Work::STATUS_IN_REVIEW,
            Work::STATUS_CHANGES_REQUESTED,
            Work::STATUS_REJECTED,
            Work::STATUS_ARCHIVED,
        ];
    }

    /** @return list<string> */
    private function allStatuses(): array
    {
        return [
            Work::STATUS_DRAFT,
            Work::STATUS_SUBMITTED,
            Work::STATUS_IN_REVIEW,
            Work::STATUS_CHANGES_REQUESTED,
            Work::STATUS_APPROVED,
            Work::STATUS_PUBLISHED,
            Work::STATUS_REJECTED,
            Work::STATUS_HIDDEN,
            Work::STATUS_ARCHIVED,
        ];
    }

    private function workForAction(string $action): Work
    {
        return match ($action) {
            'publish' => Work::factory()->approved()->create(),
            'unpublish' => Work::factory()->published()->create(),
            'hide' => Work::factory()->create(),
            'restore' => Work::factory()->hidden()->create(),
            'feature' => Work::factory()->published()->create(['is_featured' => false]),
            'unfeature' => Work::factory()->archived()->create(['is_featured' => true]),
            'pin' => Work::factory()->published()->create(['is_pinned' => false]),
            'unpin' => Work::factory()->archived()->create(['is_pinned' => true]),
        };
    }

    private function actionUrl(Work $work, string $action): string
    {
        return "/api/admin/works/{$work->id}/visibility/{$action}";
    }

    /** @param list<string> $permissions */
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

    /** @return list<string> */
    private function recursiveKeys(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $keys = [];

        foreach ($value as $key => $nestedValue) {
            if (is_string($key)) {
                $keys[] = strtolower($key);
            }

            $keys = [...$keys, ...$this->recursiveKeys($nestedValue)];
        }

        return array_values(array_unique($keys));
    }
}
