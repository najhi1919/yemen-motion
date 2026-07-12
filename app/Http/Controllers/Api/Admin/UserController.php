<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditEventLogger $auditEventLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer || ! $viewer->can('admin.users.view')) {
            abort(403, 'غير مصرح لك بعرض المستخدمين.');
        }

        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 50));

        $search = trim((string) $request->query('search', ''));
        $role = trim((string) $request->query('role', ''));
        $request->validate([
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
        ]);

        $allowedSortColumns = ['id', 'name', 'email', 'created_at'];
        $allowedSortDirections = ['asc', 'desc'];
        $sortBy = (string) $request->query('sort_by', 'id');
        $sortDirection = strtolower((string) $request->query('sort_direction', 'asc'));

        if (! in_array($sortBy, $allowedSortColumns, true)) {
            $sortBy = 'id';
        }

        if (! in_array($sortDirection, $allowedSortDirections, true)) {
            $sortDirection = 'asc';
        }

        $users = User::query()
            ->with('roles:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', function ($query) use ($role) {
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', $role));
            })
            ->when($request->filled('created_from'), function ($query) use ($request) {
                $query->whereDate('created_at', '>=', $request->date('created_from'));
            })
            ->when($request->filled('created_to'), function ($query) use ($request) {
                $query->whereDate('created_at', '<=', $request->date('created_to'));
            })
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values(),
                'created_at' => $user->created_at?->toJSON(),
            ]),
            'message' => 'تم جلب المستخدمين بنجاح',
            'errors' => null,
            'meta' => [
                'available_roles' => ['super-admin', 'admin', 'staff', 'client', 'designer'],
            ],
        ]);
    }

    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer || ! $viewer->can('admin.users.assign_roles')) {
            abort(403, 'غير مصرح لك بتعديل أدوار المستخدمين.');
        }

        $targetIsSuperAdmin = $user->hasRole('super-admin');
        $viewerIsSuperAdmin = $viewer->hasRole('super-admin');

        if ($targetIsSuperAdmin && ! $viewerIsSuperAdmin) {
            abort(403, 'لا يمكن تعديل أدوار حساب المدير الأعلى.');
        }

        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'web')),
            ],
        ]);

        $roles = collect($validated['roles'])
            ->unique()
            ->values()
            ->all();

        if (in_array('super-admin', $roles, true) && ! $viewerIsSuperAdmin) {
            abort(403, 'لا يمكن إسناد دور المدير الأعلى.');
        }

        if ($targetIsSuperAdmin && ! in_array('super-admin', $roles, true)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'لا يمكن إزالة دور المدير الأعلى من حساب المدير الأعلى.',
                'errors' => [
                    'roles' => ['يجب أن يبقى دور المدير الأعلى مرتبطًا بهذا المستخدم.'],
                ],
            ], 422);
        }

        $previousRoles = $user->getRoleNames()
            ->sort()
            ->values()
            ->all();

        $user->syncRoles($roles);
        $user->load('roles:id,name');
        $newRoles = $user->roles
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        // نسجل المزامنة بعد نجاحها باستخدام أسماء الأدوار النظامية فقط.
        // لا نمرر اسم المستخدم أو بريده أو نموذج الطلب أو payload كامل.
        $this->recordUserRolesSyncedAuditEvent(
            $request,
            $user->id,
            $previousRoles,
            $newRoles,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values(),
                'created_at' => $user->created_at?->toJSON(),
            ],
            'message' => 'تم تحديث أدوار المستخدم بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * يسجل مزامنة الأدوار بسياق محدود دون بيانات المستخدم الشخصية.
     *
     * @param list<string> $previousRoles
     * @param list<string> $newRoles
     */
    private function recordUserRolesSyncedAuditEvent(
        Request $request,
        int $targetUserId,
        array $previousRoles,
        array $newRoles,
    ): void {
        $actor = $request->user();
        $addedRoles = array_values(array_diff($newRoles, $previousRoles));
        $removedRoles = array_values(array_diff($previousRoles, $newRoles));

        try {
            $this->auditEventLogger->record([
                'event_type' => 'user.roles.synced',
                'category' => 'users',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $actor?->id,
                'actor_role' => $actor?->roles->first()?->name,
                'target_type' => 'user',
                'target_id' => $targetUserId,
                'action' => 'sync_roles',
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-ID'),
                'correlation_id' => $request->header('X-Correlation-ID'),
                'metadata' => [
                    'previous_roles' => $previousRoles,
                    'new_roles' => $newRoles,
                    'added_roles' => $addedRoles,
                    'removed_roles' => $removedRoles,
                    'role_count' => count($newRoles),
                    'source' => 'admin_users_role_sync',
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);

            // في الاختبارات نعيد الخطأ حتى لا تمر عيوب audit أو مخطط البيانات بصمت.
            if (app()->environment('testing')) {
                throw $exception;
            }
        }
    }
}
