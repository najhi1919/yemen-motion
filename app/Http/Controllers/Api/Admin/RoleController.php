<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer || ! $viewer->can('admin.roles.view')) {
            abort(403, 'غير مصرح لك بعرض الأدوار.');
        }

        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';
        $rolePivotKey = $columnNames['role_pivot_key'] ?? 'role_id';

        $roles = Role::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'users_count' => DB::table($modelHasRolesTable)
                    ->where($rolePivotKey, $role->id)
                    ->where('model_type', User::class)
                    ->count(),
                'permissions_count' => DB::table($roleHasPermissionsTable)
                    ->where($rolePivotKey, $role->id)
                    ->count(),
                'created_at' => $role->created_at?->toJSON(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $roles,
            'message' => 'تم جلب الأدوار بنجاح',
            'errors' => null,
        ]);
    }
}
