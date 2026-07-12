<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Models\User;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class StaffController extends Controller
{
    public function __construct(
        private readonly AuditEventLogger $auditEventLogger,
    ) {}

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $user->assignRole($validated['role']);

            return $user->load('roles:id,name');
        });

        $assignedRole = $user->roles->first()?->name ?? $validated['role'];

        // نسجل الحدث بعد اكتمال إنشاء الحساب وإسناد الدور، دون تمرير بيانات الطلب الحساسة.
        $this->recordStaffCreatedAuditEvent($request, $user, $assignedRole);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الموظف بنجاح.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $assignedRole,
                    'roles' => $user->roles->pluck('name')->values(),
                    'created_at' => $user->created_at?->toJSON(),
                ],
            ],
            'errors' => null,
        ], 201);
    }

    /**
     * يسجل إنشاء الموظف بسياق محدود، ولا يستقبل password أو email أو payload كامل.
     */
    private function recordStaffCreatedAuditEvent(
        StoreStaffRequest $request,
        User $createdUser,
        string $assignedRole,
    ): void {
        $actor = $request->user();

        try {
            $this->auditEventLogger->record([
                'event_type' => 'staff.created',
                'category' => 'staff',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $actor?->id,
                'actor_role' => $actor?->roles->first()?->name,
                'target_type' => 'user',
                'target_id' => $createdUser->id,
                'action' => 'create',
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-ID'),
                'correlation_id' => $request->header('X-Correlation-ID'),
                'metadata' => [
                    'assigned_role' => $assignedRole,
                    'created_user_role' => $assignedRole,
                    'source' => 'admin_staff_create',
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
