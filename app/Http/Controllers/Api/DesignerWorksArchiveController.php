<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designer\DesignerWorkArchiveRequest;
use App\Http\Requests\Designer\DesignerWorkRestoreRequest;
use App\Http\Resources\DesignerWorkIndexResource;
use App\Models\User;
use App\Services\Works\DesignerWorksArchiveService;
use Illuminate\Http\JsonResponse;

class DesignerWorksArchiveController extends Controller
{
    public function __construct(private readonly DesignerWorksArchiveService $service) {}

    public function archive(DesignerWorkArchiveRequest $request, int $work): JsonResponse
    {
        return $this->respond($request, $work, 'archive');
    }

    public function restore(DesignerWorkRestoreRequest $request, int $work): JsonResponse
    {
        return $this->respond($request, $work, 'restore');
    }

    private function respond(DesignerWorkArchiveRequest $request, int $work, string $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $result = $this->service->{$action}(
            $work,
            $actor,
            (string) $request->validated('expected_updated_at'),
            ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()],
        );
        $result['work'] = (new DesignerWorkIndexResource($result['work']))->resolve($request);

        return response()->json(['data' => $result]);
    }
}
