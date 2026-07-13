<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorksShowRequest;
use App\Models\User;
use App\Models\Work;
use Illuminate\Http\JsonResponse;

class WorksShowController extends Controller
{
    public function show(WorksShowRequest $request, Work $work): JsonResponse
    {
        $viewer = $request->user();
        $isSuperAdmin = (bool) $viewer?->hasRole('super-admin');
        $fieldAccess = [
            'can_view_designer' => $isSuperAdmin || (bool) $viewer?->can('admin.works.designer.view'),
            'can_view_media' => $isSuperAdmin || (bool) $viewer?->can('admin.works.media.view'),
            'can_view_metadata' => $isSuperAdmin || (bool) $viewer?->can('admin.works.metadata.view'),
            'can_view_private_notes' => $isSuperAdmin || (bool) $viewer?->can('admin.works.private_notes.view'),
        ];

        // لا نحمّل مراجع المستخدمين إلا إذا سمحت الصلاحية بعرضها.
        if ($fieldAccess['can_view_designer']) {
            $work->load([
                'designer:id,name',
                'reviewer:id,name',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'work' => $this->workPayload($work),
                'relations' => [
                    'designer' => $fieldAccess['can_view_designer']
                        ? $this->userReference($work->designer)
                        : null,
                    'reviewer' => $fieldAccess['can_view_designer']
                        ? $this->userReference($work->reviewer)
                        : null,
                ],
                'media' => $fieldAccess['can_view_media']
                    ? [
                        'media_type' => $work->media_type,
                        'has_media' => filled($work->media_type),
                    ]
                    : null,
                'private_notes' => $fieldAccess['can_view_private_notes']
                    ? [
                        'internal_notes' => $work->internal_notes,
                        'rejection_reason' => $work->rejection_reason,
                        'change_request_notes' => $work->change_request_notes,
                    ]
                    : null,
                'field_access' => $fieldAccess,
            ],
            'message' => 'تم جلب تفاصيل العمل بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function workPayload(Work $work): array
    {
        return [
            'id' => $work->id,
            'title' => $work->title,
            'slug' => $work->slug,
            'summary' => $work->summary,
            'status' => $work->status,
            'visibility_status' => $work->visibility_status,
            'media_type' => $work->media_type,
            'price_amount' => $work->price_amount,
            'delivery_days' => $work->delivery_days,
            'category_id' => $work->category_id,
            'is_featured' => $work->is_featured,
            'is_pinned' => $work->is_pinned,
            'reports_count' => $work->reports_count,
            'views_count' => $work->views_count,
            'likes_count' => $work->likes_count,
            'submitted_at' => $work->submitted_at?->toJSON(),
            'reviewed_at' => $work->reviewed_at?->toJSON(),
            'approved_at' => $work->approved_at?->toJSON(),
            'published_at' => $work->published_at?->toJSON(),
            'rejected_at' => $work->rejected_at?->toJSON(),
            'hidden_at' => $work->hidden_at?->toJSON(),
            'archived_at' => $work->archived_at?->toJSON(),
            'updated_at' => $work->updated_at?->toJSON(),
            'created_at' => $work->created_at?->toJSON(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userReference(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
}
