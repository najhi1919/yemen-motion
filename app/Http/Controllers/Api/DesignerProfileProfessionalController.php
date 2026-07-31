<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designer\DesignerProfileProfessionalShowRequest;
use App\Http\Requests\Designer\DesignerProfileProfessionalUpdateRequest;
use App\Http\Resources\DesignerProfileProfessionalResource;
use App\Models\User;
use App\Services\Designer\DesignerProfileProfessionalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignerProfileProfessionalController extends Controller
{
    public function __construct(private readonly DesignerProfileProfessionalService $service) {}

    public function show(DesignerProfileProfessionalShowRequest $request): JsonResponse
    {
        return $this->response($request, $this->service->show($this->designer($request)), 'تم جلب البيانات المهنية بنجاح.');
    }

    public function update(DesignerProfileProfessionalUpdateRequest $request): JsonResponse
    {
        $result = $this->service->update(
            $this->designer($request),
            $request->validated(),
            ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()],
        );

        return $this->response($request, $result, 'تم حفظ البيانات المهنية بنجاح.');
    }

    private function designer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('designer'), 403);

        return $user;
    }

    /** @param array<string, mixed> $result */
    private function response(Request $request, array $result, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'changed' => $result['changed'],
                'professional' => (new DesignerProfileProfessionalResource($result['profile']))->resolve($request),
                'completion' => $result['completion'],
                'options' => $result['options'],
            ],
            'errors' => null,
        ]);
    }
}
