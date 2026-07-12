<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePermissionRequest;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class PermissionController extends Controller
{
    public function __construct(
        private readonly AuditEventLogger $auditEventLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer || ! $viewer->can('admin.permissions.view')) {
            abort(403, 'غير مصرح لك بعرض الصلاحيات.');
        }

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $permission) => $this->permissionPayload($permission))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'message' => 'تم جلب الصلاحيات بنجاح',
            'errors' => null,
        ]);
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if ($this->isSystemPermissionName($validated['name'])) {
            abort(422, 'لا يمكن إنشاء صلاحية نظام بهذا الاسم.');
        }

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        $superAdmin = Role::query()
            ->where('name', 'super-admin')
            ->where('guard_name', 'web')
            ->first();

        if ($superAdmin) {
            $superAdmin->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission->refresh();

        // نسجل إنشاء الصلاحية بعد اكتمال الحفظ والإسناد، دون تمرير payload الطلب.
        $this->recordPermissionAuditEvent($request, [
            'event_type' => 'permission.created',
            'category' => 'permissions',
            'severity' => 'notice',
            'target_type' => 'permission',
            'target_id' => $permission->id,
            'action' => 'create',
            'outcome' => 'success',
            'metadata' => [
                'permission_name' => $permission->name,
                'source' => 'access_management_permission_create',
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->permissionPayload($permission),
            'message' => 'تم إنشاء الصلاحية بنجاح',
            'errors' => null,
        ], 201);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $validated = $request->validated();

        if ($this->isSystemPermissionName($permission->name)) {
            abort(422, 'لا يمكن تعديل صلاحية نظام.');
        }

        if ($this->hasExternalAssignments($permission)) {
            abort(422, 'لا يمكن تعديل صلاحية مرتبطة بأدوار أو مستخدمين.');
        }

        if ($this->isSystemPermissionName($validated['name'])) {
            abort(422, 'لا يمكن تحويل صلاحية مخصصة إلى صلاحية نظام.');
        }

        $oldPermissionName = $permission->name;

        $permission->update([
            'name' => $validated['name'],
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission->refresh();

        // نسجل تعديل الاسم بعد نجاح التحديث مع الاسمين النظاميين الآمنين فقط.
        $this->recordPermissionAuditEvent($request, [
            'event_type' => 'permission.updated',
            'category' => 'permissions',
            'severity' => 'notice',
            'target_type' => 'permission',
            'target_id' => $permission->id,
            'action' => 'update',
            'outcome' => 'success',
            'metadata' => [
                'changed_fields' => ['name'],
                'old_permission_name' => $oldPermissionName,
                'new_permission_name' => $permission->name,
                'source' => 'access_management_permission_update',
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->permissionPayload($permission),
            'message' => 'تم تحديث الصلاحية بنجاح',
            'errors' => null,
        ]);
    }

    public function destroy(Request $request, Permission $permission): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer || ! $viewer->can('admin.permissions.delete')) {
            abort(403, 'غير مصرح لك بحذف الصلاحيات.');
        }

        if ($this->isSystemPermissionName($permission->name)) {
            abort(422, 'لا يمكن حذف صلاحية نظام.');
        }

        if ($this->hasExternalAssignments($permission)) {
            abort(422, 'لا يمكن حذف صلاحية مرتبطة بأدوار أو مستخدمين.');
        }

        $deletedPermissionId = $permission->id;
        $deletedPermissionName = $permission->name;

        $permission->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // نسجل الحذف بعد نجاحه باستخدام الهوية والاسم المحفوظين قبل إزالة الصلاحية.
        $this->recordPermissionAuditEvent($request, [
            'event_type' => 'permission.deleted',
            'category' => 'permissions',
            'severity' => 'notice',
            'target_type' => 'permission',
            'target_id' => $deletedPermissionId,
            'action' => 'delete',
            'outcome' => 'success',
            'metadata' => [
                'permission_name' => $deletedPermissionName,
                'source' => 'access_management_permission_delete',
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'تم حذف الصلاحية بنجاح',
            'errors' => null,
        ]);
    }

    private function permissionPayload(Permission $permission): array
    {
        $registry = collect(config('yemen-motion-permissions.permissions', []))
            ->keyBy('name');

        $registered = $registry->get($permission->name);

        return [
            'id' => $permission->id,
            'name' => $permission->name,
            'guard_name' => $permission->guard_name,
            'group' => $registered['group'] ?? str($permission->name)->beforeLast('.')->toString(),
            'label_ar' => $registered['label_ar'] ?? $permission->name,
            'is_system' => $registered !== null,
            'created_at' => $permission->created_at?->toJSON(),
        ];
    }

    private function isSystemPermissionName(string $permissionName): bool
    {
        return collect(config('yemen-motion-permissions.permissions', []))
            ->contains(fn (array $permission) => $permission['name'] === $permissionName);
    }

    private function hasExternalAssignments(Permission $permission): bool
    {
        $assignedToNonSuperAdminRole = $permission->roles()
            ->where('name', '!=', 'super-admin')
            ->exists();

        if ($assignedToNonSuperAdminRole) {
            return true;
        }

        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $modelHasPermissionsTable = $tableNames['model_has_permissions'] ?? 'model_has_permissions';
        $permissionPivotKey = $columnNames['permission_pivot_key'] ?? 'permission_id';

        return DB::table($modelHasPermissionsTable)
            ->where($permissionPivotKey, $permission->id)
            ->exists();
    }

    /**
     * يسجل حدث الصلاحية بسياق طلب محدود دون تمرير request أو payload كامل.
     *
     * @param array<string, mixed> $event
     */
    private function recordPermissionAuditEvent(Request $request, array $event): void
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
