<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer || ! $viewer->can('admin.permissions.view')) {
            abort(403, 'غير مصرح لك بعرض الصلاحيات.');
        }

        $registry = collect(config('yemen-motion-permissions.permissions', []))
            ->keyBy('name');

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->map(function (Permission $permission) use ($registry) {
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
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'message' => 'تم جلب الصلاحيات بنجاح',
            'errors' => null,
        ]);
    }
}
