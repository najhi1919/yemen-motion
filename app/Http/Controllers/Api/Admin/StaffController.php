<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListStaffRequest;
use App\Http\Requests\Admin\StaffActivityRequest;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\SyncStaffPermissionsRequest;
use App\Http\Requests\Admin\SyncStaffRolesRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class StaffController extends Controller
{
    public function __construct(
        private readonly AuditEventLogger $auditEventLogger,
    ) {}

    public function index(ListStaffRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $search = trim((string) ($validated['search'] ?? ''));
        $role = trim((string) ($validated['role'] ?? ''));
        $sortBy = (string) ($validated['sort_by'] ?? 'id');
        $sortDirection = (string) ($validated['sort_direction'] ?? 'asc');

        $query = $this->internalTeamQuery()
            ->with('roles:id,name')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', function (Builder $query) use ($role): void {
                $query->whereHas(
                    'roles',
                    fn (Builder $roleQuery) => $roleQuery->where('name', $role),
                );
            })
            ->when(
                filled($validated['created_from'] ?? null),
                fn (Builder $query) => $query->whereDate('created_at', '>=', $validated['created_from']),
            )
            ->when(
                filled($validated['created_to'] ?? null),
                fn (Builder $query) => $query->whereDate('created_at', '<=', $validated['created_to']),
            )
            ->orderBy($sortBy, $sortDirection);

        $users = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user): array => $this->staffPayload($user));

        return response()->json([
            'success' => true,
            'data' => $users,
            'message' => 'تم جلب فريق العمل بنجاح.',
            'errors' => null,
            'meta' => [
                'summary' => [
                    'total' => $this->internalTeamQuery()->count(),
                    'staff_role' => $this->internalTeamQuery()
                        ->whereHas('roles', fn (Builder $query) => $query->where('name', 'staff'))
                        ->count(),
                    'admin_role' => $this->internalTeamQuery()
                        ->whereHas('roles', fn (Builder $query) => $query->where('name', 'admin'))
                        ->count(),
                ],
                'available_roles' => ['staff', 'admin'],
            ],
        ]);
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $user->assignRole($validated['role']);

            return $user->load('roles:id,name');
        });

        $assignedRole = $user->roles->first()?->name ?? $validated['role'];

        $this->recordStaffCreatedAuditEvent($request, $user, $assignedRole);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الموظف بنجاح.',
            'data' => [
                'user' => [
                    ...$this->staffPayload($user),
                    'role' => $assignedRole,
                ],
            ],
            'errors' => null,
        ], 201);
    }

    public function update(UpdateStaffRequest $request, User $staff): JsonResponse
    {
        if (
            $staff->isSuperAdmin()
            || ! $staff->hasAnyRole(['staff', 'admin'])
        ) {
            abort(404);
        }

        $validated = $request->validated();
        $changedFields = [];

        foreach (['name', 'email'] as $field) {
            if ((string) $staff->{$field} !== (string) $validated[$field]) {
                $changedFields[] = $field;
            }
        }

        if ($changedFields !== []) {
            DB::transaction(function () use ($staff, $validated): void {
                $staff->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                ]);
            });

            $staff->refresh()->load('roles:id,name');
            $this->recordStaffUpdatedAuditEvent($request, $staff, $changedFields);
        } else {
            $staff->loadMissing('roles:id,name');
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات الموظف بنجاح.',
            'data' => [
                'user' => $this->staffPayload($staff),
            ],
            'errors' => null,
        ]);
    }

    public function syncRoles(SyncStaffRolesRequest $request, User $staff): JsonResponse
    {
        if (
            $staff->isSuperAdmin()
            || ! $staff->hasAnyRole(['staff', 'admin'])
        ) {
            abort(404);
        }

        $roles = array_values(array_unique(array_filter(
            $request->validated('roles'),
            fn (string $role): bool => in_array($role, ['staff', 'admin'], true),
        )));
        sort($roles);

        $previousRoles = $staff->roles()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        DB::transaction(function () use ($staff, $roles): void {
            $staff->syncRoles($roles);
        });

        $staff->load('roles:id,name');
        $newRoles = $staff->roles
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->recordStaffRolesSyncedAuditEvent(
            $request,
            $staff,
            $previousRoles,
            $newRoles,
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث أدوار الموظف بنجاح.',
            'data' => [
                'user' => $this->staffPayload($staff),
            ],
            'errors' => null,
        ]);
    }

    public function permissions(Request $request, User $staff): JsonResponse
    {
        $actor = $request->user();

        abort_unless(
            $actor instanceof User
            && ! $actor->hasAnyRole(['client', 'designer'])
            && $actor->hasAnyRole(['super-admin', 'admin', 'staff'])
            && $actor->can('admin.staff.assign_permissions'),
            403,
        );

        $this->ensureManageableStaffTarget($staff);
        $staff->loadMissing(['roles:id,name', 'permissions:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب صلاحيات الموظف بنجاح.',
            'data' => [
                'user' => $this->staffPayload($staff),
                'permissions' => [
                    'available' => $this->availablePermissionCatalog($actor),
                    ...$this->staffPermissionPayload($staff),
                ],
            ],
            'errors' => null,
        ]);
    }

    public function syncPermissions(
        SyncStaffPermissionsRequest $request,
        User $staff,
    ): JsonResponse {
        $this->ensureManageableStaffTarget($staff);

        $requestedPermissions = array_values(array_unique($request->validated('permissions')));
        sort($requestedPermissions);

        $actor = $request->user();
        $manageablePermissions = $this->manageablePermissionNames($actor);
        $previousDirectPermissions = $this->directPermissionNames($staff);
        $preservedPermissions = array_values(array_diff(
            $previousDirectPermissions,
            $manageablePermissions,
        ));
        $finalPermissions = array_values(array_unique([
            ...$preservedPermissions,
            ...$requestedPermissions,
        ]));
        sort($finalPermissions);

        DB::transaction(function () use ($staff, $finalPermissions): void {
            $staff->syncPermissions($finalPermissions);
        });

        $staff->load(['roles:id,name', 'permissions:id,name']);
        $newDirectPermissions = $this->directPermissionNames($staff);

        if ($previousDirectPermissions !== $newDirectPermissions) {
            $this->recordStaffPermissionsSyncedAuditEvent(
                $request,
                $staff,
                $previousDirectPermissions,
                $newDirectPermissions,
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الصلاحيات المباشرة للموظف بنجاح.',
            'data' => [
                'user' => $this->staffPayload($staff),
                'permissions' => $this->staffPermissionPayload($staff),
            ],
            'errors' => null,
        ]);
    }

    public function activity(StaffActivityRequest $request, User $staff): JsonResponse
    {
        if (
            $staff->isSuperAdmin()
            || ! $staff->hasAnyRole(['staff', 'admin'])
        ) {
            abort(404);
        }

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 10);

        $events = AuditEvent::query()
            ->where(function (Builder $query) use ($staff): void {
                $query
                    ->where(function (Builder $targetQuery) use ($staff): void {
                        $targetQuery
                            ->where('target_type', 'user')
                            ->where('target_id', $staff->id);
                    })
                    ->orWhere(function (Builder $actorQuery) use ($staff): void {
                        $actorQuery
                            ->where('actor_type', 'user')
                            ->where('actor_id', $staff->id);
                    });
            })
            ->when(
                filled($validated['event_type'] ?? null),
                fn (Builder $query) => $query->where('event_type', $validated['event_type']),
            )
            ->when(
                filled($validated['category'] ?? null),
                fn (Builder $query) => $query->where('category', $validated['category']),
            )
            ->when(
                filled($validated['severity'] ?? null),
                fn (Builder $query) => $query->where('severity', $validated['severity']),
            )
            ->when(
                filled($validated['outcome'] ?? null),
                fn (Builder $query) => $query->where('outcome', $validated['outcome']),
            )
            ->when(
                filled($validated['from'] ?? null),
                fn (Builder $query) => $query->whereDate('occurred_at', '>=', $validated['from']),
            )
            ->when(
                filled($validated['to'] ?? null),
                fn (Builder $query) => $query->whereDate('occurred_at', '<=', $validated['to']),
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (AuditEvent $event): array => $this->activityPayload($event));

        return response()->json([
            'success' => true,
            'data' => $events,
            'message' => 'تم جلب سجل عمليات الحساب بنجاح.',
            'errors' => null,
            'meta' => [
                'staff' => $this->staffPayload($staff->loadMissing('roles:id,name')),
            ],
        ]);
    }

    private function internalTeamQuery(): Builder
    {
        return User::query()
            ->whereHas(
                'roles',
                fn (Builder $query) => $query->whereIn('name', ['staff', 'admin']),
            )
            ->whereDoesntHave(
                'roles',
                fn (Builder $query) => $query->where('name', User::superAdminRoleName()),
            );
    }

    private function ensureManageableStaffTarget(User $staff): void
    {
        if (
            $staff->isSuperAdmin()
            || ! $staff->hasAnyRole(['staff', 'admin'])
        ) {
            abort(404);
        }
    }

    /**
     * @return list<array{name: string, group: string, label_ar: string}>
     */
    private function availablePermissionCatalog(User $actor): array
    {
        $manageableNames = $this->manageablePermissionNames($actor);

        return collect(config('yemen-motion-permissions.permissions', []))
            ->filter(fn (array $permission): bool => in_array(
                $permission['name'] ?? null,
                $manageableNames,
                true,
            ))
            ->sortBy([
                ['group', 'asc'],
                ['name', 'asc'],
            ])
            ->map(fn (array $permission): array => [
                'name' => $permission['name'],
                'group' => $permission['group'],
                'label_ar' => $permission['label_ar'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function manageablePermissionNames(User $actor): array
    {
        $registeredNames = collect(config('yemen-motion-permissions.permissions', []))
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name))
            ->unique()
            ->sort()
            ->values();

        if ($actor->isSuperAdmin()) {
            return $registeredNames->all();
        }

        return $registeredNames
            ->intersect($actor->getAllPermissions()->pluck('name'))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function directPermissionNames(User $staff): array
    {
        return $staff->getDirectPermissions()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array{direct: list<string>, inherited: list<string>, effective: list<string>}
     */
    private function staffPermissionPayload(User $staff): array
    {
        $direct = $this->directPermissionNames($staff);
        $inherited = $staff->getPermissionsViaRoles()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();
        $effective = array_values(array_unique([...$direct, ...$inherited]));
        sort($effective);

        return [
            'direct' => $direct,
            'inherited' => $inherited,
            'effective' => $effective,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->sort()->values(),
            'created_at' => $user->created_at?->toJSON(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activityPayload(AuditEvent $event): array
    {
        return [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'category' => $event->category,
            'severity' => $event->severity,
            'actor_id' => $event->actor_id,
            'actor_role' => $event->actor_role,
            'target_id' => $event->target_id,
            'action' => $event->action,
            'outcome' => $event->outcome,
            'request_id' => $event->request_id,
            'correlation_id' => $event->correlation_id,
            'metadata' => $event->metadata,
            'occurred_at' => $event->occurred_at?->toJSON(),
        ];
    }

    /**
     * @param list<string> $changedFields
     */
    private function recordStaffUpdatedAuditEvent(
        UpdateStaffRequest $request,
        User $updatedUser,
        array $changedFields,
    ): void {
        $actor = $request->user();

        try {
            $this->auditEventLogger->record([
                'event_type' => 'staff.updated',
                'category' => 'staff',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $actor?->id,
                'actor_role' => $actor?->roles->first()?->name,
                'target_type' => 'user',
                'target_id' => $updatedUser->id,
                'action' => 'update',
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-ID'),
                'correlation_id' => $request->header('X-Correlation-ID'),
                'metadata' => [
                    'changed_fields' => array_values($changedFields),
                    'source' => 'admin_staff_update',
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            if (app()->environment('testing')) {
                throw $exception;
            }
        }
    }

    private function recordStaffCreatedAuditEvent(
        StoreStaffRequest $request,
        User $createdUser,
        string $assignedRole,
    ): void {
        $actor = $request->user();

        try {
            $this->auditEventLogger->record([
                'event_type' => 'staff.created',
                'category' => 'staff',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $actor?->id,
                'actor_role' => $actor?->roles->first()?->name,
                'target_type' => 'user',
                'target_id' => $createdUser->id,
                'action' => 'create',
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-ID'),
                'correlation_id' => $request->header('X-Correlation-ID'),
                'metadata' => [
                    'assigned_role' => $assignedRole,
                    'created_user_role' => $assignedRole,
                    'source' => 'admin_staff_create',
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            if (app()->environment('testing')) {
                throw $exception;
            }
        }
    }

    /**
     * @param list<string> $previousRoles
     * @param list<string> $newRoles
     */
    private function recordStaffRolesSyncedAuditEvent(
        SyncStaffRolesRequest $request,
        User $updatedUser,
        array $previousRoles,
        array $newRoles,
    ): void {
        $actor = $request->user();

        try {
            $this->auditEventLogger->record([
                'event_type' => 'staff.roles.synced',
                'category' => 'staff',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $actor?->id,
                'actor_role' => $actor?->roles->first()?->name,
                'target_type' => 'user',
                'target_id' => $updatedUser->id,
                'action' => 'sync_roles',
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-ID'),
                'correlation_id' => $request->header('X-Correlation-ID'),
                'metadata' => [
                    'previous_roles' => $previousRoles,
                    'new_roles' => $newRoles,
                    'added_roles' => array_values(array_diff($newRoles, $previousRoles)),
                    'removed_roles' => array_values(array_diff($previousRoles, $newRoles)),
                    'role_count' => count($newRoles),
                    'source' => 'admin_staff_role_sync',
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            if (app()->environment('testing')) {
                throw $exception;
            }
        }
    }

    /**
     * @param list<string> $previousDirectPermissions
     * @param list<string> $newDirectPermissions
     */
    private function recordStaffPermissionsSyncedAuditEvent(
        SyncStaffPermissionsRequest $request,
        User $updatedUser,
        array $previousDirectPermissions,
        array $newDirectPermissions,
    ): void {
        $actor = $request->user();

        try {
            $this->auditEventLogger->record([
                'event_type' => 'staff.permissions.synced',
                'category' => 'staff',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $actor?->id,
                'actor_role' => $actor?->roles->first()?->name,
                'target_type' => 'user',
                'target_id' => $updatedUser->id,
                'action' => 'sync_permissions',
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-ID'),
                'correlation_id' => $request->header('X-Correlation-ID'),
                'metadata' => [
                    'previous_direct_permissions' => $previousDirectPermissions,
                    'new_direct_permissions' => $newDirectPermissions,
                    'added_permissions' => array_values(array_diff(
                        $newDirectPermissions,
                        $previousDirectPermissions,
                    )),
                    'removed_permissions' => array_values(array_diff(
                        $previousDirectPermissions,
                        $newDirectPermissions,
                    )),
                    'direct_permission_count' => count($newDirectPermissions),
                    'source' => 'admin_staff_permission_sync',
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            if (app()->environment('testing')) {
                throw $exception;
            }
        }
    }
}
