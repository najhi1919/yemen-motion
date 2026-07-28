<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designer\UpsertDesignerProfileRequest;
use App\Http\Requests\Designer\UsernameAvailabilityRequest;
use App\Http\Resources\DesignerProfileResource;
use App\Models\DesignerProfile;
use App\Models\User;
use App\Support\UsernamePolicy;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DesignerProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $this->designer($request);
        $profile = $user->designerProfile;

        return $this->profileResponse($user, $profile, 'تم جلب بيانات الملف بنجاح.');
    }

    public function usernameAvailability(UsernameAvailabilityRequest $request): JsonResponse
    {
        $user = $this->designer($request);
        $normalized = UsernamePolicy::normalize($request->validated('username'));

        if ($normalized === null || ! UsernamePolicy::isValid($normalized)) {
            $reason = $normalized !== null && UsernamePolicy::isReserved($normalized)
                ? 'reserved'
                : 'invalid';

            return $this->availabilityResponse(false, $normalized, $reason);
        }

        $exists = User::query()
            ->where('username', $normalized)
            ->where('id', '!=', $user->id)
            ->exists();

        return $this->availabilityResponse(
            ! $exists,
            $normalized,
            $exists ? 'taken' : null,
        );
    }

    public function upsert(UpsertDesignerProfileRequest $request): JsonResponse
    {
        $this->designer($request);
        $validated = $request->validated();

        try {
            [$user, $profile] = DB::transaction(function () use ($request, $validated): array {
                $user = User::query()
                    ->whereKey($request->user()->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($user->username === null) {
                    $user->username = $validated['username'];
                    $user->save();
                } elseif (
                    array_key_exists('username', $validated)
                    && $validated['username'] !== null
                    && $validated['username'] !== $user->username
                ) {
                    throw ValidationException::withMessages([
                        'username' => ['لا يمكن تغيير اسم المستخدم من هذه الشاشة.'],
                    ]);
                }

                $profileData = [
                    'display_name' => $validated['display_name'],
                    'professional_title' => $validated['professional_title'] ?? null,
                    'primary_specialty' => $validated['primary_specialty'] ?? null,
                    'bio' => $validated['bio'] ?? null,
                    'availability' => $validated['availability'],
                ];

                $profile = $user->designerProfile()->updateOrCreate([], $profileData);

                return [$user->fresh(), $profile->fresh()];
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'username' => ['اسم المستخدم غير صالح أوغير متاح.'],
            ]);
        }

        return $this->profileResponse($user, $profile, 'تم حفظ بيانات الملف بنجاح.');
    }

    private function designer(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->hasRole('designer'), 403);

        return $user;
    }

    private function profileResponse(
        User $user,
        ?DesignerProfile $profile,
        string $message,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'profile' => $profile === null ? null : new DesignerProfileResource($profile),
                'username' => $user->username,
                'can_claim_username' => $user->username === null,
                'basic_completion' => $this->basicCompletion($user, $profile),
            ],
            'errors' => null,
        ]);
    }

    private function availabilityResponse(
        bool $available,
        ?string $normalized,
        ?string $reason,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $available
                ? 'اسم المستخدم متاح.'
                : 'اسم المستخدم غير متاح.',
            'data' => [
                'available' => $available,
                'normalized' => $normalized,
                'reason' => $reason,
            ],
            'errors' => null,
        ]);
    }

    /**
     * @return array{completed: int, total: int, percentage: int, missing: array<int, string>}
     */
    private function basicCompletion(User $user, ?DesignerProfile $profile): array
    {
        $fields = [
            'username' => $user->username,
            'display_name' => $profile?->display_name,
            'professional_title' => $profile?->professional_title,
            'primary_specialty' => $profile?->primary_specialty,
            'bio' => $profile?->bio,
        ];

        $missing = array_keys(array_filter(
            $fields,
            static fn (mixed $value): bool => ! is_string($value) || trim($value) === '',
        ));
        $total = count($fields);
        $completed = $total - count($missing);

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => (int) round(($completed / $total) * 100),
            'missing' => $missing,
        ];
    }
}
