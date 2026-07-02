<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePermissionRequest;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
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

        return response()->json([
            'success' => true,
            'data' => $this->permissionPayload($permission->fresh()),
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

        $permission->update([
            'name' => $validated['name'],
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'data' => $this->permissionPayload($permission->fresh()),
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

        $permission->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

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
}
