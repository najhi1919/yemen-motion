<?php

declare(strict_types=1);

namespace App\Services\Designer;

use App\Models\DesignerProfile;
use App\Models\DesignerProfileFeaturedWork;
use App\Models\User;
use App\Models\Work;
use App\Services\Audit\AuditEventLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DesignerProfileFeaturedWorksService
{
    public const LIMIT = 6;

    private const WORK_RELATIONS = ['category', 'tags', 'coverMedia'];

    public function __construct(
        private readonly AuditEventLogger $auditEventLogger,
    ) {}

    /** @return array<string, mixed> */
    public function show(User $actor): array
    {
        $profile = DesignerProfile::query()
            ->where('user_id', $actor->getKey())
            ->first();

        if (! $profile instanceof DesignerProfile) {
            $this->missingProfile();
        }

        return $this->result($profile, false);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, string|null>  $requestContext
     * @return array<string, mixed>
     */
    public function update(
        User $actor,
        array $validated,
        array $requestContext,
    ): array {
        return DB::transaction(function () use (
            $actor,
            $validated,
            $requestContext,
        ): array {
            $profile = DesignerProfile::query()
                ->where('user_id', $actor->getKey())
                ->lockForUpdate()
                ->first();

            if (! $profile instanceof DesignerProfile) {
                $this->missingProfile();
            }

            $this->assertVersion(
                $profile,
                (string) $validated['expected_updated_at'],
            );

            $requestedWorkIds = array_map(
                static fn (mixed $id): int => (int) $id,
                array_values($validated['work_ids']),
            );

            $this->assertEligibleWorkIds($actor, $requestedWorkIds);

            $currentWorkIds = DesignerProfileFeaturedWork::query()
                ->where('designer_profile_id', $profile->getKey())
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('work_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            if ($currentWorkIds === $requestedWorkIds) {
                return $this->result($profile, false);
            }

            DesignerProfileFeaturedWork::query()
                ->where('designer_profile_id', $profile->getKey())
                ->delete();

            foreach ($requestedWorkIds as $position => $workId) {
                DesignerProfileFeaturedWork::query()->create([
                    'designer_profile_id' => $profile->getKey(),
                    'work_id' => $workId,
                    'position' => $position,
                ]);
            }

            $profile->touch();
            $profile->refresh();

            $previousSet = $currentWorkIds;
            $currentSet = $requestedWorkIds;
            sort($previousSet);
            sort($currentSet);

            $this->auditEventLogger->record([
                'event_type' => 'designer.profile.featured_works.updated',
                'category' => 'designer_profiles',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $actor->getKey(),
                'actor_role' => 'designer',
                'target_type' => 'designer_profile',
                'target_id' => $profile->getKey(),
                'action' => 'update_featured_works',
                'outcome' => 'success',
                'ip_address' => $requestContext['ip_address'] ?? null,
                'user_agent' => $requestContext['user_agent'] ?? null,
                'metadata' => [
                    'profile_id' => $profile->getKey(),
                    'previous_count' => count($currentWorkIds),
                    'current_count' => count($requestedWorkIds),
                    'previous_work_ids' => $currentWorkIds,
                    'current_work_ids' => $requestedWorkIds,
                    'reordered_only' => $previousSet === $currentSet,
                ],
            ]);

            return $this->result($profile, true);
        });
    }

    /** @param list<int> $workIds */
    private function assertEligibleWorkIds(User $actor, array $workIds): void
    {
        if ($workIds === []) {
            return;
        }

        $eligibleWorkIds = Work::query()
            ->where('designer_id', $actor->getKey())
            ->publiclyVisible()
            ->whereKey($workIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $requestedSet = $workIds;
        sort($requestedSet);
        sort($eligibleWorkIds);

        if ($requestedSet !== $eligibleWorkIds) {
            throw ValidationException::withMessages([
                'work_ids' => [
                    'يجب أن تكون جميع الأعمال المختارة مملوكة لك ومنشورة وعامة.',
                ],
            ]);
        }
    }

    private function assertVersion(
        DesignerProfile $profile,
        string $expectedUpdatedAt,
    ): void {
        $expected = CarbonImmutable::parse($expectedUpdatedAt);

        if (
            $profile->updated_at !== null
            && $expected->equalTo($profile->updated_at)
        ) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => 'تغيرت بيانات الملف في الخادم. حمّل النسخة الأحدث ثم حاول مجددًا.',
            'data' => [
                'code' => 'designer_profile_version_conflict',
                'current_updated_at' => $profile->updated_at?->toJSON(),
            ],
        ], 409));
    }

    /** @return Collection<int, Work> */
    private function eligibleWorks(DesignerProfile $profile): Collection
    {
        return Work::query()
            ->where('designer_id', $profile->user_id)
            ->publiclyVisible()
            ->with(self::WORK_RELATIONS)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    /** @return list<int> */
    private function selectedWorkIds(DesignerProfile $profile): array
    {
        return DesignerProfileFeaturedWork::query()
            ->where('designer_profile_id', $profile->getKey())
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('work_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @return array<string, mixed> */
    private function result(
        DesignerProfile $profile,
        bool $changed,
    ): array {
        $eligible = $this->eligibleWorks($profile);
        $eligibleById = $eligible->keyBy(
            static fn (Work $work): int => (int) $work->getKey(),
        );

        /** @var Collection<int, Work> $selected */
        $selected = new Collection;

        foreach ($this->selectedWorkIds($profile) as $workId) {
            $work = $eligibleById->get($workId);

            if ($work instanceof Work) {
                $selected->push($work);
            }

            if ($selected->count() >= self::LIMIT) {
                break;
            }
        }

        return [
            'profile' => $profile,
            'changed' => $changed,
            'limit' => self::LIMIT,
            'selected' => $selected,
            'eligible' => $eligible,
        ];
    }

    private function missingProfile(): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'أنشئ ملف المصمم الأساسي أولًا.',
            'data' => ['code' => 'designer_profile_required'],
        ], 404));
    }
}
