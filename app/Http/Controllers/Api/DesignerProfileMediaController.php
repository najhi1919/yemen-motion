<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designer\UpdateDesignerCoverFocalPointRequest;
use App\Http\Requests\Designer\UploadDesignerAvatarRequest;
use App\Http\Requests\Designer\UploadDesignerCoverRequest;
use App\Http\Resources\DesignerProfileResource;
use App\Models\DesignerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DesignerProfileMediaController extends Controller
{
    private const DISK = 'works_private';

    public function storeAvatar(UploadDesignerAvatarRequest $request): JsonResponse
    {
        $profile = $this->profile($request);
        $newPath = $this->store($request->file('avatar'), $profile, 'avatar');
        $oldPath = $profile->avatar_path;

        $this->replacePath($profile, 'avatar_path', $newPath);
        $this->deletePath($oldPath);

        return $this->profileResponse($profile->refresh(), 'تم حفظ الصورة الشخصية.');
    }

    public function destroyAvatar(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $oldPath = $profile->avatar_path;

        DB::transaction(fn () => $profile->update(['avatar_path' => null]));
        $this->deletePath($oldPath);

        return $this->profileResponse($profile->refresh(), 'تم حذف الصورة الشخصية.');
    }

    public function storeCover(UploadDesignerCoverRequest $request): JsonResponse
    {
        $profile = $this->profile($request);
        $newPath = $this->store($request->file('cover'), $profile, 'cover');
        $oldPath = $profile->cover_path;

        $this->replacePath($profile, 'cover_path', $newPath);
        $this->deletePath($oldPath);

        return $this->profileResponse($profile->refresh(), 'تم حفظ غلاف المصمم.');
    }

    public function updateCoverFocalPoint(
        UpdateDesignerCoverFocalPointRequest $request
    ): JsonResponse {
        $profile = $this->profile($request);

        abort_if($profile->cover_path === null, 422, 'أضف غلافًا قبل تحديد موضعه.');

        $profile->update([
            'cover_focal_x' => $request->integer('x'),
            'cover_focal_y' => $request->integer('y'),
        ]);

        return $this->profileResponse($profile->refresh(), 'تم حفظ موضع الغلاف.');
    }

    public function destroyCover(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $oldPath = $profile->cover_path;

        DB::transaction(fn () => $profile->update([
            'cover_path' => null,
            'cover_focal_x' => 50,
            'cover_focal_y' => 50,
        ]));
        $this->deletePath($oldPath);

        return $this->profileResponse($profile->refresh(), 'تم حذف غلاف المصمم.');
    }

    public function avatarContent(Request $request): StreamedResponse
    {
        return $this->contentResponse($this->profile($request)->avatar_path);
    }

    public function coverContent(Request $request): StreamedResponse
    {
        return $this->contentResponse($this->profile($request)->cover_path);
    }

    private function profile(Request $request): DesignerProfile
    {
        $user = $request->user();

        abort_unless($user?->hasRole('designer'), 403, 'هذا المسار مخصص للمصممين.');
        abort_if($user->designerProfile === null, 404, 'أنشئ ملف المصمم الأساسي أولًا.');

        return $user->designerProfile;
    }

    private function store(
        UploadedFile $file,
        DesignerProfile $profile,
        string $kind
    ): string {
        $directory = "designer-profiles/{$profile->user_id}/{$kind}";
        $filename = Str::uuid()->toString().'.'.strtolower($file->extension());

        return $file->storeAs($directory, $filename, self::DISK);
    }

    private function replacePath(
        DesignerProfile $profile,
        string $column,
        string $newPath
    ): void {
        try {
            DB::transaction(fn () => $profile->update([$column => $newPath]));
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($newPath);
            throw $exception;
        }
    }

    private function deletePath(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    private function contentResponse(?string $path): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);

        abort_if($path === null || ! $disk->exists($path), 404);

        $mime = $disk->mimeType($path) ?: 'application/octet-stream';

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);

            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function profileResponse(
        DesignerProfile $profile,
        string $message
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'profile' => new DesignerProfileResource($profile),
            ],
        ]);
    }
}
