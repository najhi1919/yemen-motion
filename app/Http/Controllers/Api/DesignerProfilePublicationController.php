<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designer\DesignerProfilePublicationActionRequest;
use App\Http\Requests\Designer\DesignerProfilePublicationShowRequest;
use App\Http\Resources\DesignerProfilePreviewResource;
use App\Http\Resources\DesignerProfilePublicationResource;
use App\Models\User;
use App\Services\Designer\DesignerProfilePublicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignerProfilePublicationController extends Controller
{
    public function __construct(private readonly DesignerProfilePublicationService $service) {}

    public function show(DesignerProfilePublicationShowRequest $request): JsonResponse
    {
        return $this->publicationResponse(
            $request,
            $this->service->status($this->designer($request)),
            'تم جلب حالة نشر الملف بنجاح.',
        );
    }

    public function preview(DesignerProfilePublicationShowRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'تم جلب معاينة الملف بنجاح.',
            'data' => [
                'preview' => (new DesignerProfilePreviewResource(
                    $this->service->preview($this->designer($request)),
                ))->resolve($request),
            ],
            'errors' => null,
        ]);
    }

    public function publish(DesignerProfilePublicationActionRequest $request): JsonResponse
    {
        return $this->publicationResponse(
            $request,
            $this->service->publish(
                $this->designer($request),
                (string) $request->validated('expected_updated_at'),
            ),
            'تم نشر ملف المصمم بنجاح.',
        );
    }

    public function hide(DesignerProfilePublicationActionRequest $request): JsonResponse
    {
        return $this->publicationResponse(
            $request,
            $this->service->hide(
                $this->designer($request),
                (string) $request->validated('expected_updated_at'),
            ),
            'تم إخفاء ملف المصمم بنجاح.',
        );
    }

    private function designer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('designer') && $user->isActive(), 403);

        return $user;
    }

    /** @param array<string, mixed> $result */
    private function publicationResponse(Request $request, array $result, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => (new DesignerProfilePublicationResource($result))->resolve($request),
            'errors' => null,
        ]);
    }
}
