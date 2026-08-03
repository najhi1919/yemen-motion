<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicDesignerProfileResource;
use App\Services\Designer\PublicDesignerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicDesignerProfileController extends Controller
{
    public function __invoke(
        Request $request,
        string $username,
        PublicDesignerProfileService $service,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => [
                'profile' => (new PublicDesignerProfileResource(
                    $service->show($username),
                ))->resolve($request),
            ],
        ]);
    }
}
