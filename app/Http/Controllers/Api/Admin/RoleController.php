<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\SyncRolePermissionsRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\User;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class RoleController extends Controller
{
    public function __construct(
        private readonly AuditEventLogger $auditEventLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer || ! $viewer->can('admin.roles.view')) {
            abort(403, 'غير مصرح لك بعرض الأدوار.');
        }

        $roles = Role::query()
            ->with('permissions:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role) => $this->rolePayload($role))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $roles,
            'message' => 'تم جلب الأدوار بنجاح',
            'errors' => null,
        ]);
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer || ! $viewer->can('admin.roles.view')) {
            abort(403, 'غير مصرح لك بعرض الدور.');
        }

        return response()->json([
            'success' => true,
            'data' => $this->rolePayload($role->load('permissions:id,name')),
            'message' => 'تم جلب الدور بنجاح',
            'errors' => null,
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $protectedRoles = config('yemen-motion-permissions.protected_roles', []);

        if (in_array($validated['name'], $protectedRoles, true)) {
            abort(422, 'لا يمكن إنشاء دور محمي بهذا الاسم.');
        }

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role->load('permissions:id,name');

        // نسجل إنشاء الدور بعد اكتمال الحفظ، ونمرر الاسم النظامي فقط دون payload الطلب.
        $this->recordRoleAuditEvent($request, [
            'event_type' => 'role.created',
            'category' => 'roles',
            'severity' => 'notice',
            'target_type' => 'role',
            'target_id' => $role->id,
            'action' => 'create',
            'outcome' => 'success',
            'metadata' => [
                'role_name' => $role->name,
                'source' => 'access_management_role_create',
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->rolePayload($role),
            'message' => 'تم إنشاء الدور بنجاح',
            'errors' => null,
        ], 201);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $validated = $request->validated();
        $protectedRoles = config('yemen-motion-permissions.protected_roles', []);

        if (in_array($role->name, $protectedRoles, true)) {
            abort(422, 'لا يمكن تعديل دور محمي.');
        }

        if (in_array($validated['name'], $protectedRoles, true)) {
            abort(422, 'لا يمكن تحويل دور مخصص إلى دور محمي.');
        }

        $oldRoleName = $role->name;

        $role->update([
            'name' => $validated['name'],
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role->load('permissions:id,name');

        // نسجل تعديل الاسم بعد نجاح التحديث مع القيمتين الآمنتين فقط.
        $this->recordRoleAuditEvent($request, [
            'event_type' => 'role.updated',
            'category' => 'roles',
            'severity' => 'notice',
            'target_type' => 'role',
            'target_id' => $role->id,
            'action' => 'update',
            'outcome' => 'success',
            'metadata' => [
                'changed_fields' => ['name'],
                'old_role_name' => $oldRoleName,
                'new_role_name' => $role->name,
                'source' => 'access_management_role_update',
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->rolePayload($role),
            'message' => 'تم تحديث الدور بنجاح',
            'errors' => null,
        ]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer || ! $viewer->can('admin.roles.delete')) {
            abort(403, 'غير مصرح لك بحذف الأدوار.');
        }

        $protectedRoles = config('yemen-motion-permissions.protected_roles', []);

        if (in_array($role->name, $protectedRoles, true)) {
            abort(422, 'لا يمكن حذف دور محمي.');
        }

        if ($this->usersCount($role) > 0) {
            abort(422, 'لا يمكن حذف دور مرتبط بمستخدمين.');
        }

        $deletedRoleId = $role->id;
        $deletedRoleName = $role->name;

        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // نسجل الحذف بعد نجاحه باستخدام الهوية والاسم اللذين حُفظا قبل إزالة الدور.
        $this->recordRoleAuditEvent($request, [
            'event_type' => 'role.deleted',
            'category' => 'roles',
            'severity' => 'notice',
            'target_type' => 'role',
            'target_id' => $deletedRoleId,
            'action' => 'delete',
            'outcome' => 'success',
            'metadata' => [
                'role_name' => $deletedRoleName,
                'source' => 'access_management_role_delete',
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'تم حذف الدور بنجاح',
            'errors' => null,
        ]);
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $validated = $request->validated();

        if ($role->name === 'super-admin') {
            abort(422, 'لا يمكن تعديل صلاحيات دور super-admin من هذه الواجهة.');
        }

        $role->syncPermissions($validated['permissions']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role->load('permissions:id,name');
        $permissionNames = $role->permissions
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        // نسجل المزامنة بعد اكتمالها من الصلاحيات الناتجة، دون تمرير الطلب أو payload كامل.
        $this->recordRoleAuditEvent($request, [
            'event_type' => 'role.permissions.synced',
            'category' => 'roles',
            'severity' => 'notice',
            'target_type' => 'role',
            'target_id' => $role->id,
            'action' => 'sync_permissions',
            'outcome' => 'success',
            'metadata' => [
                'role_name' => $role->name,
                'permission_count' => count($permissionNames),
                'permission_names' => $permissionNames,
                'source' => 'access_management_role_permissions_sync',
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->rolePayload($role),
            'message' => 'تم تحديث صلاحيات الدور بنجاح',
            'errors' => null,
        ]);
    }

    private function rolePayload(Role $role): array
    {
        $protectedRoles = config('yemen-motion-permissions.protected_roles', []);

        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'is_protected' => in_array($role->name, $protectedRoles, true),
            'users_count' => $this->usersCount($role),
            'permissions_count' => $role->permissions->count(),
            'permissions' => $role->permissions
                ->pluck('name')
                ->sort()
                ->values(),
            'created_at' => $role->created_at?->toJSON(),
        ];
    }

    private function usersCount(Role $role): int
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $rolePivotKey = $columnNames['role_pivot_key'] ?? 'role_id';

        return DB::table($modelHasRolesTable)
            ->where($rolePivotKey, $role->id)
            ->where('model_type', User::class)
            ->count();
    }

    /**
     * يسجل حدث الدور بسياق طلب محدود دون تمرير request أو payload كامل.
     *
     * @param array<string, mixed> $event
     */
    private function recordRoleAuditEvent(Request $request, array $event): void
    {
        $actor = $request->user();

        try {
            $this->auditEventLogger->record([
                ...$event,
                'actor_type' => 'user',
                'actor_id' => $actor?->id,
                'actor_role' => $actor?->roles->first()?->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-ID'),
                'correlation_id' => $request->header('X-Correlation-ID'),
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
