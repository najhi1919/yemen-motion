<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\WorksAuthoringStateConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksAuthoringOptionsRequest;
use App\Http\Requests\Admin\WorksAuthoringShowRequest;
use App\Http\Requests\Admin\WorksDraftStoreRequest;
use App\Http\Requests\Admin\WorksDraftUpdateRequest;
use App\Models\User;
use App\Models\Work;
use App\Services\Works\WorksAuthoringService;
use Illuminate\Http\JsonResponse;

class WorksAuthoringController extends Controller
{
    public function __construct(private readonly WorksAuthoringService $authoringService) {}

    public function options(WorksAuthoringOptionsRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $fieldAccess = $this->authoringFieldAccess($actor);
        $designerOptions = [];

        if ($fieldAccess['can_update_designer']) {
            $search = trim((string) ($validated['q'] ?? ''));
            $limit = (int) ($validated['limit'] ?? 20);

            $designerOptions = User::query()
                ->select(['id', 'name'])
                ->whereHas('roles', fn ($query) => $query->where('name', 'designer'))
                ->when(
                    $search !== '',
                    fn ($query) => $query->where('name', 'like', "%{$search}%"),
                )
                ->orderBy('name')
                ->orderBy('id')
                ->limit($limit)
                ->get()
                ->map(fn (User $designer): array => [
                    'id' => $designer->id,
                    'name' => $designer->name,
                ])
                ->values()
                ->all();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'field_access' => $fieldAccess,
                'authoring_policy' => $this->authoringPolicy($request->authoringSettings()),
                'designer_options' => $designerOptions,
            ],
            'message' => 'تم جلب خيارات تأليف الأعمال بنجاح',
            'errors' => null,
        ]);
    }

    public function show(WorksAuthoringShowRequest $request, string $work): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $currentWork = Work::query()
            ->with('designer:id,name')
            ->findOrFail((int) $work);
        $fieldAccess = $this->authoringFieldAccess($actor);
        $workPayload = [
            'id' => $currentWork->id,
            'title' => $currentWork->title,
            'slug' => $currentWork->slug,
            'summary' => $currentWork->summary,
            'description' => $currentWork->description,
            'status' => $currentWork->status,
            'visibility_status' => $currentWork->visibility_status,
            'media_type' => $currentWork->media_type,
            'price_amount' => $currentWork->price_amount,
            'delivery_days' => $currentWork->delivery_days,
            'designer_id' => $currentWork->designer_id,
            'category_id' => $currentWork->category_id,
            'tag_ids' => $this->hasPermissions($actor, [
                'admin.works.taxonomy.view',
                'admin.works.taxonomy.tags.view',
            ])
                ? $currentWork->tags()->orderBy('work_tags.id')->pluck('work_tags.id')->all()
                : [],
            'cover_media_id' => $currentWork->cover_media_id,
            'change_request_notes' => $currentWork->change_request_notes,
            'created_at' => $currentWork->created_at?->toJSON(),
            'updated_at' => $currentWork->updated_at?->toJSON(),
        ];

        if ($fieldAccess['can_update_private_notes']) {
            $workPayload['internal_notes'] = $currentWork->internal_notes;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'work' => $workPayload,
                'designer' => $currentWork->designer ? [
                    'id' => $currentWork->designer->id,
                    'name' => $currentWork->designer->name,
                ] : null,
                'field_access' => $fieldAccess,
                'authoring_policy' => $this->authoringPolicy($request->authoringSettings()),
                'authoring_state' => [
                    'editable' => in_array($currentWork->status, [
                        Work::STATUS_DRAFT,
                        Work::STATUS_CHANGES_REQUESTED,
                    ], true),
                    'allowed_statuses' => [
                        Work::STATUS_DRAFT,
                        Work::STATUS_CHANGES_REQUESTED,
                    ],
                ],
            ],
            'message' => 'تم جلب مساحة تأليف العمل بنجاح',
            'errors' => null,
        ]);
    }

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
        return array_diff_key(
            $this->authoringFieldAccess($actor),
            ['can_create' => true],
        );
    }

    /** @return array<string, bool> */
    private function authoringFieldAccess(User $actor): array
    {
        return [
            'can_create' => $this->hasPermission($actor, 'admin.works.create'),
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
