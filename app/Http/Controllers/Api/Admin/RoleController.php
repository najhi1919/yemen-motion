<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\SyncRolePermissionsRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
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

        return response()->json([
            'success' => true,
            'data' => $this->rolePayload($role->load('permissions:id,name')),
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

        $role->update([
            'name' => $validated['name'],
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'data' => $this->rolePayload($role->fresh('permissions:id,name')),
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

        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

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

        return response()->json([
            'success' => true,
            'data' => $this->rolePayload($role->fresh('permissions:id,name')),
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
}
