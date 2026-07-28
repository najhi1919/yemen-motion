<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListStaffRequest;
use App\Http\Requests\Admin\StaffActivityRequest;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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
}
