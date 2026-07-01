<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();

        if (! $viewer || ! $viewer->hasRole('admin')) {
            abort(403, 'غير مصرح لك بعرض المستخدمين.');
        }

        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 50));

        $search = trim((string) $request->query('search', ''));
        $role = trim((string) $request->query('role', ''));

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
            ->latest('id')
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
                'available_roles' => ['admin', 'staff', 'client', 'designer'],
            ],
        ]);
    }
}
