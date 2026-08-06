<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designer\DesignerProfileFeaturedWorksShowRequest;
use App\Http\Requests\Designer\DesignerProfileFeaturedWorksUpdateRequest;
use App\Http\Resources\DesignerProfileFeaturedWorksResource;
use App\Models\User;
use App\Services\Designer\DesignerProfileFeaturedWorksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignerProfileFeaturedWorksController extends Controller
{
    public function __construct(
        private readonly DesignerProfileFeaturedWorksService $service,
    ) {}

    public function show(
        DesignerProfileFeaturedWorksShowRequest $request,
    ): JsonResponse {
        return $this->response(
            $request,
            $this->service->show($this->designer($request)),
            'تم جلب الأعمال المميزة بنجاح.',
        );
    }

    public function update(
        DesignerProfileFeaturedWorksUpdateRequest $request,
    ): JsonResponse {
        return $this->response(
            $request,
            $this->service->update(
                $this->designer($request),
                $request->validated(),
                [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ),
            'تم حفظ الأعمال المميزة بنجاح.',
        );
    }

    private function designer(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User
                && $user->hasRole('designer')
                && $user->isActive(),
            403,
        );

        return $user;
    }

    /** @param array<string, mixed> $result */
    private function response(
        Request $request,
        array $result,
        string $message,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => (new DesignerProfileFeaturedWorksResource(
                $result,
            ))->resolve($request),
            'errors' => null,
        ]);
    }
}
