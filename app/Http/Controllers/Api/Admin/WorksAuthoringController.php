<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\WorksAuthoringStateConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksDraftStoreRequest;
use App\Http\Requests\Admin\WorksDraftUpdateRequest;
use App\Models\User;
use App\Models\Work;
use App\Services\Works\WorksAuthoringService;
use Illuminate\Http\JsonResponse;

class WorksAuthoringController extends Controller
{
    public function __construct(private readonly WorksAuthoringService $authoringService) {}

    public function store(WorksDraftStoreRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $settings = $request->authoringSettings();
        $result = $this->authoringService->createDraft(
            $request->validated(),
            $settings,
            $actor,
            $this->requestContext($request),
        );

        return response()->json([
            'success' => true,
            'data' => $this->responseData(
                $result,
                'create',
                $actor,
                $settings,
            ),
            'message' => 'تم إنشاء مسودة العمل بنجاح',
            'errors' => null,
        ], 201);
    }

    public function update(WorksDraftUpdateRequest $request, string $work): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $settings = $request->authoringSettings();

        try {
            $result = $this->authoringService->updateDraft(
                (int) $work,
                $request->validated(),
                $settings,
                $actor,
                $this->requestContext($request),
            );
        } catch (WorksAuthoringStateConflictException $exception) {
            return response()->json([
                'success' => false,
                'data' => [
                    'current_status' => $exception->currentStatus,
                ],
                'message' => 'لا يمكن تعديل العمل في حالته الحالية.',
                'errors' => null,
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data' => $this->responseData(
                $result,
                'update',
                $actor,
                $settings,
            ),
            'message' => $result['changed']
                ? 'تم تحديث مسودة العمل بنجاح'
                : 'لم تتغير بيانات مسودة العمل',
            'errors' => null,
        ]);
    }

    /**
     * @param array{work: Work, changed: bool, changed_keys: list<string>} $result
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function responseData(
        array $result,
        string $action,
        User $actor,
        array $settings,
    ): array {
        return [
            'action' => $action,
            'changed' => $result['changed'],
            'changed_keys' => $result['changed_keys'],
            'work' => $this->workPayload($result['work'], $actor),
            'field_access' => $this->fieldAccess($actor),
            'authoring_policy' => $this->authoringPolicy($settings),
        ];
    }

    /** @return array<string, mixed> */
    private function workPayload(Work $work, User $actor): array
    {
        $payload = [
            'id' => $work->id,
            'title' => $work->title,
            'slug' => $work->slug,
            'summary' => $work->summary,
            'description' => $work->description,
            'status' => $work->status,
            'visibility_status' => $work->visibility_status,
            'media_type' => $work->media_type,
            'price_amount' => $work->price_amount,
            'delivery_days' => $work->delivery_days,
            'designer_id' => $work->designer_id,
            'created_at' => $work->created_at?->toJSON(),
            'updated_at' => $work->updated_at?->toJSON(),
        ];

        if ($this->hasPermission($actor, 'admin.works.update.private_notes')) {
            $payload['internal_notes'] = $work->internal_notes;
        }

        return $payload;
    }

    /** @return array<string, bool> */
    private function fieldAccess(User $actor): array
    {
        return [
            'can_update_basic' => $this->hasPermission($actor, 'admin.works.update.basic'),
            'can_update_media' => $this->hasPermission($actor, 'admin.works.update.media'),
            'can_update_pricing' => $this->hasPermission($actor, 'admin.works.update.pricing'),
            'can_update_delivery' => $this->hasPermission($actor, 'admin.works.update.delivery'),
            'can_update_designer' => $this->hasPermission($actor, 'admin.works.update.designer'),
            'can_update_private_notes' => $this->hasPermission($actor, 'admin.works.update.private_notes'),
            'can_assign_category' => $this->hasPermissions($actor, [
                'admin.works.taxonomy.view',
                'admin.works.taxonomy.categories.view',
                'admin.works.update.category',
            ]),
            'can_assign_tags' => $this->hasPermissions($actor, [
                'admin.works.taxonomy.view',
                'admin.works.taxonomy.tags.view',
                'admin.works.update.tags',
            ]),
        ];
    }

    /** @param array<string, mixed> $settings */
    private function authoringPolicy(array $settings): array
    {
        $mediaLimits = $settings['values']['media_limits'] ?? [];

        return [
            'source' => 'work_settings',
            'settings_version' => (int) ($settings['version'] ?? 1),
            'allowed_media_types' => $mediaLimits['allowed_types'] ?? Work::MEDIA_TYPES,
            'media_limits' => [
                'max_items' => $mediaLimits['max_items'] ?? null,
                'max_file_size_kb' => $mediaLimits['max_file_size_kb'] ?? null,
            ],
            'enforcement' => [
                'media_type' => true,
                'max_items' => true,
                'max_file_size_kb' => true,
            ],
        ];
    }

    private function hasPermission(User $actor, string $permission): bool
    {
        return $actor->hasRole('super-admin') || $actor->can($permission);
    }

    /** @param list<string> $permissions */
    private function hasPermissions(User $actor, array $permissions): bool
    {
        return $actor->hasRole('super-admin')
            || collect($permissions)->every(
                fn (string $permission): bool => $actor->can($permission),
            );
    }

    /** @return array{ip_address: string|null, user_agent: string|null} */
    private function requestContext(WorksDraftStoreRequest|WorksDraftUpdateRequest $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }
}
