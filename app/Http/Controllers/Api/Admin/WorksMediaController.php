<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\WorksMediaConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksMediaContentRequest;
use App\Http\Requests\Admin\WorksMediaDeleteRequest;
use App\Http\Requests\Admin\WorksMediaIndexRequest;
use App\Http\Requests\Admin\WorksMediaUploadRequest;
use App\Models\User;
use App\Services\Works\WorksMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorksMediaController extends Controller
{
    public function __construct(private readonly WorksMediaService $mediaService) {}

    public function index(WorksMediaIndexRequest $request, string $work): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $result = $this->mediaService->index((int) $work);
        $result['field_access'] = [
            'can_view_media' => $this->canViewMedia($actor),
            'can_update_media' => $this->canUpdateMedia($actor),
        ];

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'تم جلب وسائط العمل بنجاح',
            'errors' => null,
        ]);
    }

    public function store(WorksMediaUploadRequest $request, string $work): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            $result = $this->mediaService->upload(
                (int) $work,
                $file,
                $request->mediaSettings(),
                $actor,
                $this->requestContext($request),
            );
        } catch (WorksMediaConflictException $exception) {
            return $this->conflictResponse($exception);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'تم رفع وسيط العمل بنجاح',
            'errors' => null,
        ], 201);
    }

    public function content(
        WorksMediaContentRequest $request,
        string $work,
        string $media,
    ): StreamedResponse {
        return $this->mediaService->content((int) $work, (int) $media);
    }

    public function destroy(
        WorksMediaDeleteRequest $request,
        string $work,
        string $media,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->mediaService->delete(
                (int) $work,
                (int) $media,
                $actor,
                $this->requestContext($request),
            );
        } catch (WorksMediaConflictException $exception) {
            return $this->conflictResponse($exception);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'تم حذف وسيط العمل منطقيًا',
            'errors' => null,
        ]);
    }

    private function conflictResponse(WorksMediaConflictException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => [
                'reason' => $exception->reason,
                ...$exception->data,
            ],
            'message' => $exception->getMessage(),
            'errors' => null,
        ], 409);
    }

    private function canViewMedia(User $actor): bool
    {
        return $actor->hasRole('super-admin')
            || $actor->can('admin.works.media.view')
            || $actor->can('admin.works.update.media');
    }

    private function canUpdateMedia(User $actor): bool
    {
        return $actor->hasRole('super-admin')
            || $actor->can('admin.works.update.media');
    }

    /** @return array{ip_address: string|null, user_agent: string|null} */
    private function requestContext(
        WorksMediaUploadRequest|WorksMediaDeleteRequest $request,
    ): array {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }
}
