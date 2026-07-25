<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\WorksReviewReadinessException;
use App\Exceptions\WorksReviewSubmissionConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksReviewReadinessRequest;
use App\Http\Requests\Admin\WorksReviewSubmitRequest;
use App\Models\User;
use App\Models\Work;
use App\Services\Works\WorksReviewReadinessService;
use App\Services\Works\WorksReviewSubmissionService;
use App\Services\Works\WorksSettingsStore;
use Illuminate\Http\JsonResponse;

class WorksReviewSubmissionController extends Controller
{
    public function __construct(
        private readonly WorksReviewReadinessService $readinessService,
        private readonly WorksReviewSubmissionService $submissionService,
        private readonly WorksSettingsStore $settingsStore,
    ) {}

    public function readiness(WorksReviewReadinessRequest $request, string $work): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $currentWork = Work::query()->findOrFail((int) $work);
        $readiness = $this->readinessService->evaluate(
            $currentWork,
            $this->settingsStore->getGlobalSettings(),
            $actor->hasRole('super-admin') || $actor->can('admin.works.update.private_notes'),
        );

        return response()->json([
            'success' => true,
            'data' => ['readiness' => $readiness],
            'message' => 'تم فحص جاهزية العمل للمراجعة بنجاح',
            'errors' => null,
        ]);
    }

    public function submit(WorksReviewSubmitRequest $request, string $work): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->submissionService->submit(
                (int) $work,
                (string) $request->validated('expected_updated_at'),
                $actor,
                ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()],
            );
        } catch (WorksReviewSubmissionConflictException $exception) {
            return response()->json([
                'success' => false,
                'data' => [
                    'current_status' => $exception->currentStatus,
                    'current_updated_at' => $exception->currentUpdatedAt,
                    'readiness' => $exception->readiness,
                ],
                'message' => 'تغيرت نسخة العمل في الخادم. حمّل النسخة الأحدث قبل الإرسال.',
                'errors' => null,
            ], 409);
        } catch (WorksReviewReadinessException $exception) {
            return response()->json([
                'success' => false,
                'data' => ['readiness' => $exception->readiness],
                'message' => 'لا يزال العمل يحتوي على متطلبات مانعة من الإرسال.',
                'errors' => ['readiness' => ['أكمل متطلبات الجاهزية ثم حاول مجددًا.']],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'تم إرسال العمل للمراجعة بنجاح',
            'errors' => null,
        ]);
    }
}
