<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WorksAuthoringStateConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Designer\DesignerWorkAuthoringShowRequest;
use App\Http\Requests\Designer\DesignerWorkStoreRequest;
use App\Http\Requests\Designer\DesignerWorkUpdateRequest;
use App\Http\Resources\DesignerWorkAuthoringResource;
use App\Models\Work;
use App\Services\Works\WorksAuthoringService;
use App\Services\Works\WorksSettingsStore;
use Illuminate\Http\JsonResponse;

class DesignerWorksAuthoringController extends Controller
{
    private const AUTHORING_FIELDS = [
        'title',
        'summary',
        'description',
        'media_type',
        'price_amount',
        'delivery_days',
    ];

    private const EDITABLE_STATUSES = [
        Work::STATUS_DRAFT,
        Work::STATUS_CHANGES_REQUESTED,
    ];

    public function store(
        DesignerWorkStoreRequest $request,
        WorksAuthoringService $service,
    ): JsonResponse {
        $attributes = $request->validated();
        $attributes['designer_id'] = $request->user()->getKey();
        $context = $this->context($request);

        /** @var array{work: Work, changed: bool, changed_keys: list<string>} $result */
        $result = app()->call([$service, 'createDraft'], [
            'actor' => $request->user(),
            'user' => $request->user(),
            'attributes' => $attributes,
            'payload' => $attributes,
            'data' => $attributes,
            'validated' => $attributes,
            'settings' => $request->authoringSettings(),
            'settingsSnapshot' => $request->authoringSettings(),
            'context' => $context,
            'requestContext' => $context,
            'requestMetadata' => $context,
            'metadata' => $context,
        ]);

        return response()->json([
            'data' => [
                'work' => (new DesignerWorkAuthoringResource($result['work']))->resolve($request),
                'changed' => true,
                'changed_keys' => $this->safeChangedKeys($result['changed_keys']),
            ],
            'message' => 'تم إنشاء مسودة العمل بنجاح.',
        ], 201);
    }

    public function show(
        DesignerWorkAuthoringShowRequest $request,
        int $work,
        WorksSettingsStore $settingsStore,
    ): JsonResponse {
        $owned = $this->ownedWork($request->user()->getKey(), $work);
        $settings = $settingsStore->getGlobalSettings();
        $allowedTypes = $settings['values']['media_limits']['allowed_types'] ?? Work::MEDIA_TYPES;

        return response()->json([
            'data' => [
                'work' => (new DesignerWorkAuthoringResource($owned))->resolve($request),
                'authoring_state' => [
                    'editable' => in_array($owned->status, self::EDITABLE_STATUSES, true),
                    'allowed_statuses' => self::EDITABLE_STATUSES,
                ],
                'authoring_policy' => [
                    'allowed_media_types' => $allowedTypes,
                ],
            ],
        ]);
    }

    public function update(
        DesignerWorkUpdateRequest $request,
        int $work,
        WorksAuthoringService $service,
    ): JsonResponse {
        $owned = $this->ownedWork($request->user()->getKey(), $work);

        if (! in_array($owned->status, self::EDITABLE_STATUSES, true)) {
            return $this->conflict($owned);
        }

        $attributes = $request->validated();
        $context = $this->context($request);

        /** @var array{work: Work, changed: bool, changed_keys: list<string>} $result */
        try {
            $result = $service->updateDraft(
                $owned->getKey(),
                $attributes,
                $request->authoringSettings(),
                $request->user(),
                $context,
            );
        } catch (WorksAuthoringStateConflictException) {
            return $this->conflict($owned->refresh());
        }

        return response()->json([
            'data' => [
                'work' => (new DesignerWorkAuthoringResource($result['work']))->resolve($request),
                'changed' => $result['changed'],
                'changed_keys' => $this->safeChangedKeys($result['changed_keys']),
            ],
            'message' => 'تم حفظ بيانات العمل بنجاح.',
        ]);
    }

    private function ownedWork(int $userId, int $workId): Work
    {
        return Work::query()
            ->where('designer_id', $userId)
            ->whereKey($workId)
            ->firstOrFail();
    }

    private function conflict(Work $work): JsonResponse
    {
        return response()->json([
            'data' => ['current_status' => $work->status],
            'message' => 'لا يمكن تعديل العمل في حالته الحالية.',
        ], 409);
    }

    private function safeChangedKeys(array $keys): array
    {
        return array_values(array_intersect(self::AUTHORING_FIELDS, $keys));
    }

    private function context(DesignerWorkStoreRequest|DesignerWorkUpdateRequest $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }
}
