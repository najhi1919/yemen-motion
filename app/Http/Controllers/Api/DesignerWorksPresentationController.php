<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designer\DesignerWorkPresentationShowRequest;
use App\Http\Requests\Designer\DesignerWorkPresentationUpdateRequest;
use App\Models\Work;
use App\Services\Audit\AuditEventLogger;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DesignerWorksPresentationController extends Controller
{
    private const EDITABLE_STATUSES = [
        Work::STATUS_DRAFT,
        Work::STATUS_CHANGES_REQUESTED,
    ];

    public function __construct(
        private readonly AuditEventLogger $auditEventLogger,
    ) {
    }

    public function show(DesignerWorkPresentationShowRequest $request, int $work): JsonResponse
    {
        $ownedWork = $this->ownedWork($work, (int) $request->user()->getKey());

        return response()->json(['data' => $this->payload($ownedWork)]);
    }

    public function update(DesignerWorkPresentationUpdateRequest $request, int $work): JsonResponse
    {
        $validated = $request->validated();
        $actor = $request->user();
        $context = [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        $result = DB::transaction(function () use ($validated, $work, $actor, $context): array {
            $locked = Work::query()
                ->whereKey($work)
                ->where('designer_id', $actor->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, self::EDITABLE_STATUSES, true)) {
                throw new HttpResponseException(response()->json([
                    'message' => 'لا يمكن تعديل طريقة عرض الغلاف في حالة العمل الحالية.',
                    'data' => [
                        'code' => 'work_state_not_editable',
                        'current_status' => $locked->status,
                    ],
                ], 409));
            }

            $mode = $validated['cover_display_mode'];
            $x = (int) $validated['cover_focal_point']['x'];
            $y = (int) $validated['cover_focal_point']['y'];
            $previousMode = $locked->cover_display_mode;
            $previousX = (int) $locked->cover_focal_x;
            $previousY = (int) $locked->cover_focal_y;
            $changedKeys = [];

            if ($mode !== $previousMode) {
                $changedKeys[] = 'cover_display_mode';
            }
            if ($x !== $previousX || $y !== $previousY) {
                $changedKeys[] = 'cover_focal_point';
            }

            if ($changedKeys === []) {
                return ['work' => $locked, 'changed' => false, 'changed_keys' => []];
            }

            $locked->forceFill([
                'cover_display_mode' => $mode,
                'cover_focal_x' => $x,
                'cover_focal_y' => $y,
            ])->save();

            $this->auditEventLogger->record([
                'event_type' => 'works.designer.cover_presentation.updated',
                'category' => 'works',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $actor->getKey(),
                'actor_role' => $actor->roles->first()?->name,
                'target_type' => 'work',
                'target_id' => $locked->getKey(),
                'action' => 'update_cover_presentation',
                'outcome' => 'success',
                'ip_address' => $context['ip_address'],
                'user_agent' => $context['user_agent'],
                'metadata' => [
                    'work_id' => $locked->getKey(),
                    'changed_keys' => $changedKeys,
                    'previous_display_mode' => $previousMode,
                    'display_mode' => $mode,
                    'previous_focal_point' => ['x' => $previousX, 'y' => $previousY],
                    'focal_point' => ['x' => $x, 'y' => $y],
                ],
            ]);

            return ['work' => $locked, 'changed' => true, 'changed_keys' => $changedKeys];
        });

        return response()->json([
            'data' => [
                ...$this->payload($result['work']),
                'changed' => $result['changed'],
                'changed_keys' => $result['changed_keys'],
            ],
            'message' => $result['changed']
                ? 'تم حفظ طريقة عرض الغلاف.'
                : 'لا توجد تغييرات لحفظها.',
        ]);
    }

    private function ownedWork(int $workId, int $designerId): Work
    {
        return Work::query()
            ->whereKey($workId)
            ->where('designer_id', $designerId)
            ->firstOrFail();
    }

    private function payload(Work $work): array
    {
        return [
            'work' => [
                'id' => (int) $work->id,
                'public_code' => $work->public_code,
                'title' => $work->title,
                'status' => $work->status,
                'media_type' => $work->media_type,
                'cover_display_mode' => in_array(
                    $work->cover_display_mode,
                    Work::COVER_DISPLAY_MODES,
                    true,
                ) ? $work->cover_display_mode : Work::COVER_DISPLAY_MODE_FILL,
                'cover_focal_point' => [
                    'x' => max(0, min(100, (int) ($work->cover_focal_x ?? 50))),
                    'y' => max(0, min(100, (int) ($work->cover_focal_y ?? 50))),
                ],
            ],
            'presentation_state' => [
                'editable' => in_array($work->status, self::EDITABLE_STATUSES, true),
                'available_modes' => Work::COVER_DISPLAY_MODES,
            ],
        ];
    }
}
