<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WorksMediaConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Designer\DesignerWorkMediaContentRequest;
use App\Http\Requests\Designer\DesignerWorkMediaCoverRequest;
use App\Http\Requests\Designer\DesignerWorkMediaDeleteRequest;
use App\Http\Requests\Designer\DesignerWorkMediaIndexRequest;
use App\Http\Requests\Designer\DesignerWorkMediaReorderRequest;
use App\Http\Requests\Designer\DesignerWorkMediaRetryRequest;
use App\Http\Requests\Designer\DesignerWorkMediaUploadRequest;
use App\Http\Requests\Designer\DesignerWorkVideoCoverCurrentRequest;
use App\Http\Requests\Designer\DesignerWorkVideoCoverFrameRequest;
use App\Http\Requests\Designer\DesignerWorkVideoCoverUploadRequest;
use App\Models\Work;
use App\Models\WorkMedia;
use App\Services\Works\WorksMediaService;
use App\Services\Works\WorksVideoCoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DesignerWorksMediaController extends Controller
{
    private const EDITABLE_STATUSES = [
        Work::STATUS_DRAFT,
        Work::STATUS_CHANGES_REQUESTED,
    ];

    public function index(
        DesignerWorkMediaIndexRequest $request,
        int $work,
        WorksMediaService $service,
    ): JsonResponse {
        $owned = $this->ownedWork($request->user()->getKey(), $work);
        $result = $service->index($owned->getKey());
        $media = $this->presentMediaList($owned->getKey(), $result['media']);

        return response()->json([
            'data' => [
                'work' => $this->presentWork($result['work']),
                'media' => $media,
                'media_policy' => $result['media_policy'],
                'counts' => $this->counts($media, $result['media_policy']),
                'media_state' => [
                    'editable' => in_array($owned->status, self::EDITABLE_STATUSES, true),
                    'allowed_statuses' => self::EDITABLE_STATUSES,
                ],
            ],
        ]);
    }

    public function store(
        DesignerWorkMediaUploadRequest $request,
        int $work,
        WorksMediaService $service,
    ): JsonResponse {
        $owned = $this->ownedWork($request->user()->getKey(), $work);

        try {
            $result = $service->upload(
                $owned->getKey(),
                $request->file('file'),
                $request->mediaSettings(),
                $request->user(),
                $this->context($request),
            );
        } catch (WorksMediaConflictException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'data' => [
                'action' => $result['action'],
                'media' => $this->presentMedia($owned->getKey(), $result['media']),
                'media_policy' => $result['media_policy'],
                'counts' => $result['counts'],
            ],
            'message' => 'تم رفع وسيط العمل بنجاح.',
        ], 201);
    }

    public function destroy(
        DesignerWorkMediaDeleteRequest $request,
        int $work,
        int $media,
        WorksMediaService $service,
    ): JsonResponse {
        $owned = $this->ownedWork($request->user()->getKey(), $work);
        $this->ownedMedia($owned->getKey(), $media);

        try {
            $result = $service->delete(
                $owned->getKey(),
                $media,
                $request->user(),
                $this->context($request),
            );
        } catch (WorksMediaConflictException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'data' => [
                'action' => $result['action'],
                'deleted_media_id' => $result['deleted_media_id'],
                'cover_cleared' => $result['cover_cleared'],
                'physical_file_retained' => $result['physical_file_retained'],
                'counts' => $result['counts'],
            ],
            'message' => 'تم حذف وسيط العمل بنجاح.',
        ]);
    }

    public function reorder(
        DesignerWorkMediaReorderRequest $request,
        int $work,
        WorksMediaService $service,
    ): JsonResponse {
        $owned = $this->ownedWork($request->user()->getKey(), $work);
        $mediaIds = $request->validated('media_ids');
        foreach ($mediaIds as $mediaId) {
            $this->ownedMedia($owned->getKey(), (int) $mediaId);
        }

        try {
            $result = $service->reorder(
                $owned->getKey(),
                $mediaIds,
                $request->mediaSettings(),
                $request->user(),
                $this->context($request),
            );
        } catch (WorksMediaConflictException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'data' => $this->presentOrganization($owned->getKey(), $result),
            'message' => $result['changed']
                ? 'تم تحديث ترتيب وسائط العمل بنجاح.'
                : 'ترتيب وسائط العمل محدث بالفعل.',
        ]);
    }

    public function updateCover(
        DesignerWorkMediaCoverRequest $request,
        int $work,
        WorksMediaService $service,
    ): JsonResponse {
        $owned = $this->ownedWork($request->user()->getKey(), $work);
        $coverId = $request->validated('cover_media_id');
        if ($coverId !== null) {
            $this->ownedMedia($owned->getKey(), (int) $coverId);
        }

        try {
            $result = $service->updateCover(
                $owned->getKey(),
                $coverId === null ? null : (int) $coverId,
                $request->mediaSettings(),
                $request->user(),
                $this->context($request),
            );
        } catch (WorksMediaConflictException $exception) {
            return $this->conflict($exception);
        }

        $message = ! $result['changed']
            ? 'غلاف العمل محدث بالفعل.'
            : ($result['current_cover_media_id'] === null
                ? 'تمت إزالة غلاف العمل بنجاح.'
                : 'تم تحديث غلاف العمل بنجاح.');

        return response()->json([
            'data' => $this->presentOrganization($owned->getKey(), $result),
            'message' => $message,
        ]);
    }

    public function retryProcessing(
        DesignerWorkMediaRetryRequest $request,
        int $work,
        int $media,
        WorksMediaService $service,
    ): JsonResponse {
        $owned = $this->ownedWork($request->user()->getKey(), $work);
        $this->ownedMedia($owned->getKey(), $media);

        try {
            $result = $service->retryProcessing(
                $owned->getKey(),
                $media,
                $request->user(),
                $this->context($request),
            );
        } catch (WorksMediaConflictException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'data' => [
                'action' => $result['action'],
                'changed' => $result['changed'],
                'media' => $this->presentMedia($owned->getKey(), $result['media']),
            ],
            'message' => $result['changed']
                ? 'تمت إعادة إرسال الفيديو للمعالجة.'
                : 'معالجة الفيديو جارية بالفعل.',
        ]);
    }

    public function content(
        DesignerWorkMediaContentRequest $request,
        int $work,
        int $media,
        WorksMediaService $service,
    ): StreamedResponse|Response {
        $owned = $this->ownedWork($request->user()->getKey(), $work);
        $this->ownedMedia($owned->getKey(), $media);

        return $service->content($owned->getKey(), $media);
    }

    public function poster(
        DesignerWorkMediaContentRequest $request,
        int $work,
        int $media,
        WorksMediaService $service,
    ): StreamedResponse|Response {
        $owned = $this->ownedWork($request->user()->getKey(), $work);
        $this->ownedMedia($owned->getKey(), $media);

        return $service->poster($owned->getKey(), $media);
    }

    public function useCurrentVideoCover(
        DesignerWorkVideoCoverCurrentRequest $request,
        int $work,
        int $media,
        WorksVideoCoverService $service,
    ): JsonResponse {
        $owned = $this->ownedWork($request->user()->getKey(), $work);
        $this->ownedMedia($owned->getKey(), $media);

        try {
            $result = $service->useCurrentPoster(
                $owned->getKey(),
                $media,
                $request->user(),
                $this->context($request),
            );
        } catch (WorksMediaConflictException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'data' => $result,
            'message' => $result['changed']
                ? 'تم استخدام صورة معاينة الفيديو كغلاف للعمل.'
                : 'صورة معاينة الفيديو مستخدمة كغلاف بالفعل.',
        ]);
    }

    public function selectVideoCoverFrame(
        DesignerWorkVideoCoverFrameRequest $request,
        int $work,
        int $media,
        WorksVideoCoverService $service,
    ): JsonResponse {
        $owned = $this->ownedWork($request->user()->getKey(), $work);
        $this->ownedMedia($owned->getKey(), $media);

        try {
            $result = $service->selectFrame(
                $owned->getKey(),
                $media,
                (int) $request->validated('time_ms'),
                $request->user(),
                $this->context($request),
            );
        } catch (WorksMediaConflictException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'data' => $result,
            'message' => 'تم استخدام اللقطة المحددة من الفيديو كغلاف للعمل.',
        ]);
    }

    public function uploadVideoCover(
        DesignerWorkVideoCoverUploadRequest $request,
        int $work,
        int $media,
        WorksVideoCoverService $service,
    ): JsonResponse {
        $owned = $this->ownedWork($request->user()->getKey(), $work);
        $this->ownedMedia($owned->getKey(), $media);

        try {
            $result = $service->uploadCover(
                $owned->getKey(),
                $media,
                $request->file('file'),
                $request->mediaSettings(),
                $request->user(),
                $this->context($request),
            );
        } catch (WorksMediaConflictException $exception) {
            return $this->conflict($exception);
        }

        return response()->json([
            'data' => $result,
            'message' => 'تم رفع غلاف الفيديو وتعيينه بنجاح.',
        ]);
    }

    private function ownedWork(int $userId, int $workId): Work
    {
        return Work::query()
            ->whereKey($workId)
            ->where('designer_id', $userId)
            ->firstOrFail();
    }

    private function ownedMedia(int $workId, int $mediaId): WorkMedia
    {
        return WorkMedia::query()
            ->whereKey($mediaId)
            ->where('work_id', $workId)
            ->firstOrFail();
    }

    private function presentOrganization(int $workId, array $result): array
    {
        $data = [
            'action' => $result['action'],
            'changed' => $result['changed'],
            'changed_keys' => $result['changed_keys'],
        ];

        foreach (['previous_cover_media_id', 'current_cover_media_id'] as $key) {
            if (array_key_exists($key, $result)) {
                $data[$key] = $result[$key];
            }
        }

        return [
            ...$data,
            'work' => $this->presentWork($result['work']),
            'media' => $this->presentMediaList($workId, $result['media']),
            'media_policy' => $result['media_policy'],
            'counts' => $result['counts'],
        ];
    }

    private function presentWork(array $work): array
    {
        return array_intersect_key($work, array_flip([
            'id', 'status', 'media_type', 'cover_media_id',
        ]));
    }

    private function presentMediaList(int $workId, array $media): array
    {
        return array_map(
            fn (array $item): array => $this->presentMedia($workId, $item),
            $media,
        );
    }

    private function presentMedia(int $workId, array $media): array
    {
        $safe = array_intersect_key($media, array_flip([
            'id',
            'kind',
            'original_name',
            'mime_type',
            'extension',
            'size_bytes',
            'position',
            'width',
            'height',
            'duration_ms',
            'processing_status',
            'processing_stage',
            'processing_progress',
            'processing_started_at',
            'processing_completed_at',
            'processing_attempts',
            'processing_message',
            'can_retry_processing',
            'is_cover',
            'created_at',
            'updated_at',
        ]));
        $mediaId = (int) $media['id'];

        return [
            ...$safe,
            'content_url' => "/designer/works/{$workId}/media/{$mediaId}/content",
            'poster_url' => ($media['poster_endpoint'] ?? null) === null
                ? null
                : "/designer/works/{$workId}/media/{$mediaId}/poster",
        ];
    }

    private function counts(array $media, array $policy): array
    {
        $active = count($media);
        $maximum = $policy['effective_limits']['max_items'] ?? null;

        return [
            'active' => $active,
            'remaining' => is_int($maximum) ? max(0, $maximum - $active) : null,
        ];
    }

    private function context(
        DesignerWorkMediaUploadRequest
        |DesignerWorkMediaDeleteRequest
        |DesignerWorkMediaReorderRequest
        |DesignerWorkMediaCoverRequest
        |DesignerWorkMediaRetryRequest
        |DesignerWorkVideoCoverCurrentRequest
        |DesignerWorkVideoCoverFrameRequest
        |DesignerWorkVideoCoverUploadRequest $request,
    ): array {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }

    private function conflict(WorksMediaConflictException $exception): JsonResponse
    {
        return response()->json([
            'data' => [
                'reason' => $exception->reason,
                ...$exception->data,
            ],
            'message' => $exception->reason === 'work_state_not_editable'
                ? 'لا يمكن تعديل وسائط العمل في حالته الحالية.'
                : $exception->getMessage(),
        ], 409);
    }
}
