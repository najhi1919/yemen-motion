<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designer\DesignerWorksIndexRequest;
use App\Http\Resources\DesignerWorkIndexResource;
use App\Models\Work;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class DesignerWorksIndexController extends Controller
{
    private const GROUPS = [
        'draft' => [Work::STATUS_DRAFT],
        'review' => [Work::STATUS_SUBMITTED, Work::STATUS_IN_REVIEW, Work::STATUS_APPROVED],
        'changes' => [Work::STATUS_CHANGES_REQUESTED],
        'published' => [Work::STATUS_PUBLISHED],
        'closed' => [Work::STATUS_REJECTED, Work::STATUS_HIDDEN, Work::STATUS_ARCHIVED],
    ];

    public function index(DesignerWorksIndexRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $owned = Work::query()->where('designer_id', $request->user()->getKey());
        $summary = ['total' => (clone $owned)->count()];

        foreach (self::GROUPS as $group => $statuses) {
            $summary[$group] = (clone $owned)->whereIn('status', $statuses)->count();
        }

        $query = (clone $owned)->with('coverMedia');
        $escapedSearch = $request->escapedSearch();

        if ($escapedSearch !== null) {
            $query->where(function (Builder $search) use ($escapedSearch): void {
                $pattern = '%'.$escapedSearch.'%';
                $search->where('title', 'like', $pattern)
                    ->orWhere('summary', 'like', $pattern)
                    ->orWhere('slug', 'like', $pattern);
            });
        }

        if ($filters['group'] !== 'all') {
            $query->whereIn('status', self::GROUPS[$filters['group']]);
        }

        $query->orderBy($filters['sort'], $filters['direction']);
        if ($filters['sort'] !== 'updated_at') {
            $query->orderByDesc('updated_at');
        }
        $query->orderByDesc('id');

        $paginator = $query->paginate($filters['per_page'])->withQueryString();
        $data = collect($paginator->items())
            ->map(fn (Work $work): array => (new DesignerWorkIndexResource($work))->resolve($request))
            ->all();

        return response()->json([
            'data' => $data,
            'summary' => $summary,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'applied_filters' => [
                'q' => $filters['q'],
                'group' => $filters['group'],
                'sort' => $filters['sort'],
                'direction' => $filters['direction'],
            ],
        ]);
    }
}
