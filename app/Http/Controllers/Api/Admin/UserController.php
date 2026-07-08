<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
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

        $user->syncRoles($roles);
        $user->load('roles:id,name');

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
}
