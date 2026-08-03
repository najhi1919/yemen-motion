<?php

declare(strict_types=1);

namespace App\Services\Designer;

use App\Models\DesignerProfile;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkMedia;
use App\Support\UsernamePolicy;
use Illuminate\Http\Exceptions\HttpResponseException;

class PublicDesignerProfileService
{
    private const PROFILE_RELATIONS = [
        'user.roles',
        'specialties',
        'skills',
        'tools',
        'languages',
    ];

    public function __construct(
        private readonly DesignerProfilePublicationService $publicationService,
    ) {}

    /** @return array{profile: DesignerProfile, works: \Illuminate\Database\Eloquent\Collection<int, Work>} */
    public function show(string $username): array
    {
        $profile = $this->publicProfile($username);
        $works = Work::query()
            ->where('designer_id', $profile->user_id)
            ->publiclyVisible()
            ->with(['category', 'tags', 'coverMedia'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        return compact('profile', 'works');
    }

    public function publicProfile(string $username): DesignerProfile
    {
        $normalized = UsernamePolicy::normalize($username);
        if ($normalized === null) {
            $this->notFound();
        }

        $profile = DesignerProfile::query()
            ->where('publication_status', DesignerProfile::PUBLICATION_PUBLISHED)
            ->whereHas('user', static fn ($query) => $query
                ->where('username', $normalized)
                ->whereNull('disabled_at'))
            ->with(self::PROFILE_RELATIONS)
            ->first();

        if (! $profile instanceof DesignerProfile
            || ! $profile->user instanceof User
            || ! $profile->user->hasRole('designer')
            || ! $profile->user->isActive()
            || ! $this->publicationService->readiness($profile)['ready']) {
            $this->notFound();
        }

        return $profile;
    }

    /** @return array{work: Work, media: WorkMedia} */
    public function publicCoverMedia(
        string $username,
        string $workCode,
        int $mediaId,
        bool $requirePoster = false,
    ): array {
        $profile = $this->publicProfile($username);
        $work = Work::query()
            ->where('designer_id', $profile->user_id)
            ->where('public_code', $workCode)
            ->publiclyVisible()
            ->first();

        if (! $work instanceof Work || (int) $work->cover_media_id !== $mediaId) {
            $this->notFound();
        }

        $media = WorkMedia::query()
            ->where('work_id', $work->getKey())
            ->whereKey($mediaId)
            ->where('processing_status', WorkMedia::PROCESSING_READY)
            ->first();

        if (! $media instanceof WorkMedia
            || ($requirePoster && ($media->kind !== WorkMedia::KIND_VIDEO || $media->poster_path === null))) {
            $this->notFound();
        }

        return compact('work', 'media');
    }

    private function notFound(): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'ملف المصمم غير متاح.',
            'errors' => null,
        ], 404));
    }
}
