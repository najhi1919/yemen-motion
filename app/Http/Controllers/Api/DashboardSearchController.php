<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardSearchRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardSearchController extends Controller
{
    public function __invoke(DashboardSearchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = trim($validated['q']);
        $limit = (int) ($validated['limit'] ?? 5);
        $viewer = $request->user();

        $grouped = [
            'users' => [],
            'staff' => [],
            'roles' => [],
            'permissions' => [],
        ];

        if ($this->canSearch($viewer, 'admin.users.view')) {
            $grouped['users'] = $this->searchUsers($query, $limit);
        }

        if ($this->isSuperAdmin($viewer)) {
            $grouped['staff'] = $this->searchStaff($query, $limit);
        }

        if ($this->canSearch($viewer, 'admin.roles.view')) {
            $grouped['roles'] = $this->searchRoles($query, $limit);
        }

        if ($this->canSearch($viewer, 'admin.permissions.view')) {
            $grouped['permissions'] = $this->searchPermissions($query, $limit);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'query' => $query,
                'results' => collect($grouped)->flatten(1)->values()->all(),
                'grouped' => $grouped,
            ],
            'message' => 'تم جلب نتائج البحث بنجاح',
            'errors' => null,
        ]);
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    private function canSearch(User $user, string $permission): bool
    {
        return $this->isSuperAdmin($user) || $user->can($permission);
    }

    private function searchUsers(string $query, int $limit): array
    {
        return User::query()
            ->with('roles:id,name')
            ->where(function ($userQuery) use ($query) {
                $userQuery
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $user) => [
                'type' => 'user',
                'key' => "user:{$user->id}",
                'title' => $user->name,
                'subtitle' => $user->email,
                'route' => '/admin/users?search=' . rawurlencode($query),
                'permission' => 'admin.users.view',
                'meta' => [
                    'id' => $user->id,
                    'roles' => $user->roles->pluck('name')->values(),
                ],
            ])
            ->values()
            ->all();
    }

    private function searchStaff(string $query, int $limit): array
    {
        return User::query()
            ->with('roles:id,name')
            ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'staff'))
            ->where(function ($userQuery) use ($query) {
                $userQuery
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $user) => [
                'type' => 'staff',
                'key' => "staff:{$user->id}",
                'title' => $user->name,
                'subtitle' => $user->email,
                'route' => '/admin/staff',
                'permission' => 'super-admin',
                'meta' => [
                    'id' => $user->id,
                    'roles' => $user->roles->pluck('name')->values(),
                ],
            ])
            ->values()
            ->all();
    }

    private function searchRoles(string $query, int $limit): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Role $role) => [
                'type' => 'role',
                'key' => "role:{$role->id}",
                'title' => $role->name,
                'subtitle' => 'Role',
                'route' => '/admin/roles',
                'permission' => 'admin.roles.view',
                'meta' => [
                    'id' => $role->id,
                ],
            ])
            ->values()
            ->all();
    }

    private function searchPermissions(string $query, int $limit): array
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Permission $permission) => [
                'type' => 'permission',
                'key' => "permission:{$permission->id}",
                'title' => $permission->name,
                'subtitle' => 'Permission',
                'route' => '/admin/permissions',
                'permission' => 'admin.permissions.view',
                'meta' => [
                    'id' => $permission->id,
                ],
            ])
            ->values()
            ->all();
    }
}
