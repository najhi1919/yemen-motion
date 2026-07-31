<?php

declare(strict_types=1);

namespace App\Services\Designer;

use App\Models\DesignerProfile;
use App\Models\DesignerProfileSpecialty;
use App\Models\User;
use App\Services\Audit\AuditEventLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class DesignerProfilePublicationService
{
    private const READINESS_TOTAL = 11;

    private const RELATIONS = ['user', 'specialties', 'skills', 'tools', 'languages'];

    public function __construct(private readonly AuditEventLogger $auditEventLogger) {}

    /** @return array<string, mixed> */
    public function status(User $designer): array
    {
        $profile = $this->profile($designer);

        return $this->result($profile);
    }

    public function preview(User $designer): DesignerProfile
    {
        return $this->profile($designer);
    }

    /** @return array<string, mixed> */
    public function publish(User $designer, string $expectedUpdatedAt): array
    {
        return DB::transaction(function () use ($designer, $expectedUpdatedAt): array {
            $profile = $this->lockedProfile($designer);
            $this->assertVersion($profile, $expectedUpdatedAt);

            if ($profile->publication_status === DesignerProfile::PUBLICATION_PUBLISHED) {
                return $this->result($profile, false);
            }

            $readiness = $this->readiness($profile);
            if (! $readiness['ready']) {
                throw new HttpResponseException(response()->json([
                    'message' => 'أكمل متطلبات جاهزية الملف قبل نشره.',
                    'data' => [
                        'code' => 'designer_profile_not_ready',
                        'readiness' => $readiness,
                    ],
                ], 409));
            }

            $previousStatus = (string) $profile->publication_status;
            $profile->forceFill([
                'publication_status' => DesignerProfile::PUBLICATION_PUBLISHED,
                'published_at' => now(),
                'hidden_at' => null,
            ])->save();
            $profile->refresh()->load(self::RELATIONS);

            $this->recordAudit(
                $designer,
                $profile,
                'designer.profile.publication.published',
                'publish_profile',
                $previousStatus,
                $readiness,
            );

            return $this->result($profile, true, $readiness);
        });
    }

    /** @return array<string, mixed> */
    public function hide(User $designer, string $expectedUpdatedAt): array
    {
        return DB::transaction(function () use ($designer, $expectedUpdatedAt): array {
            $profile = $this->lockedProfile($designer);
            $this->assertVersion($profile, $expectedUpdatedAt);

            if ($profile->publication_status === DesignerProfile::PUBLICATION_HIDDEN) {
                return $this->result($profile, false);
            }

            if ($profile->publication_status !== DesignerProfile::PUBLICATION_PUBLISHED) {
                throw new HttpResponseException(response()->json([
                    'message' => 'لا يمكن إخفاء ملف لم يُنشر بعد.',
                    'data' => [
                        'code' => 'designer_profile_not_published',
                        'current_status' => $profile->publication_status,
                    ],
                ], 409));
            }

            $readiness = $this->readiness($profile);
            $previousStatus = (string) $profile->publication_status;
            $profile->forceFill([
                'publication_status' => DesignerProfile::PUBLICATION_HIDDEN,
                'hidden_at' => now(),
            ])->save();
            $profile->refresh()->load(self::RELATIONS);

            $this->recordAudit(
                $designer,
                $profile,
                'designer.profile.publication.hidden',
                'hide_profile',
                $previousStatus,
                $readiness,
            );

            return $this->result($profile, true, $readiness);
        });
    }

    /** @return array{ready: bool, completed: int, total: int, blockers: list<array{code: string, section: string, message: string, action: string}>} */
    public function readiness(DesignerProfile $profile): array
    {
        $profile->loadMissing(self::RELATIONS);
        $blockers = [];

        if ($this->blank($profile->user?->username)) {
            $blockers[] = $this->blocker('username_missing', 'basic', 'أضف اسم مستخدم للملف.', 'edit_basic');
        }
        if ($this->blank($profile->display_name)) {
            $blockers[] = $this->blocker('display_name_missing', 'basic', 'أضف الاسم المهني.', 'edit_basic');
        }
        if ($this->blank($profile->professional_title)) {
            $blockers[] = $this->blocker('professional_title_missing', 'basic', 'أضف المسمى المهني.', 'edit_basic');
        }
        if ($this->blank($profile->primary_specialty)) {
            $blockers[] = $this->blocker('primary_specialty_missing', 'basic', 'أضف التخصص الرئيسي.', 'edit_basic');
        }
        if ($this->blank($profile->bio)) {
            $blockers[] = $this->blocker('bio_missing', 'basic', 'أضف النبذة الأساسية.', 'edit_basic');
        }
        if ($this->blank($profile->avatar_path)) {
            $blockers[] = $this->blocker('avatar_missing', 'media', 'أضف الصورة الشخصية.', 'edit_avatar');
        }
        if ($profile->years_of_experience === null) {
            $blockers[] = $this->blocker('experience_missing', 'professional', 'حدد سنوات الخبرة.', 'edit_professional');
        }
        if (! $profile->specialties->contains(
            static fn ($specialty): bool => in_array($specialty->kind, [
                DesignerProfileSpecialty::KIND_SERVICE,
                DesignerProfileSpecialty::KIND_STYLE,
            ], true),
        )) {
            $blockers[] = $this->blocker('specialties_missing', 'professional', 'أضف خدمة أوأسلوبًا مهنيًا واحدًا على الأقل.', 'edit_professional');
        }
        if ($profile->skills->isEmpty()) {
            $blockers[] = $this->blocker('skills_missing', 'professional', 'أضف مهارة واحدة على الأقل.', 'edit_professional');
        }
        if ($profile->tools->isEmpty()) {
            $blockers[] = $this->blocker('tools_missing', 'professional', 'أضف برنامجًا أوأداة واحدة على الأقل.', 'edit_professional');
        }
        if ($profile->languages->isEmpty()) {
            $blockers[] = $this->blocker('languages_missing', 'professional', 'أضف لغة واحدة على الأقل.', 'edit_professional');
        }

        $requiredBlockersCount = count($blockers);
        if ($profile->user?->isDisabled()) {
            $blockers[] = $this->blocker('account_disabled', 'account', 'الحساب معطل. تواصل مع الدعم.', 'contact_support');
        }

        return [
            'ready' => $blockers === [],
            'completed' => self::READINESS_TOTAL - $requiredBlockersCount,
            'total' => self::READINESS_TOTAL,
            'blockers' => $blockers,
        ];
    }

    private function profile(User $designer): DesignerProfile
    {
        $profile = DesignerProfile::query()
            ->where('user_id', $designer->getKey())
            ->with(self::RELATIONS)
            ->first();

        if (! $profile) {
            $this->missingProfile();
        }

        return $profile;
    }

    private function lockedProfile(User $designer): DesignerProfile
    {
        $profile = DesignerProfile::query()
            ->where('user_id', $designer->getKey())
            ->lockForUpdate()
            ->first();

        if (! $profile) {
            $this->missingProfile();
        }

        return $profile->load(self::RELATIONS);
    }

    private function assertVersion(DesignerProfile $profile, string $expectedUpdatedAt): void
    {
        $expected = CarbonImmutable::parse($expectedUpdatedAt);
        if ($profile->updated_at !== null && $expected->equalTo($profile->updated_at)) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => 'تغيرت بيانات الملف في الخادم. حمّل النسخة الأحدث ثم حاول مجددًا.',
            'data' => [
                'code' => 'designer_profile_publication_version_conflict',
                'current_updated_at' => $profile->updated_at?->toJSON(),
            ],
        ], 409));
    }

    /** @param array{ready: bool, completed: int, total: int, blockers: array<int, array<string, string>>}|null $readiness */
    private function result(DesignerProfile $profile, ?bool $changed = null, ?array $readiness = null): array
    {
        $result = [
            'profile' => $profile,
            'readiness' => $readiness ?? $this->readiness($profile),
        ];

        if ($changed !== null) {
            $result['changed'] = $changed;
        }

        return $result;
    }

    /** @param array{ready: bool, completed: int, total: int, blockers: array<int, array<string, string>>} $readiness */
    private function recordAudit(
        User $designer,
        DesignerProfile $profile,
        string $eventType,
        string $action,
        string $previousStatus,
        array $readiness,
    ): void {
        $this->auditEventLogger->record([
            'event_type' => $eventType,
            'category' => 'designer_profiles',
            'severity' => 'notice',
            'actor_type' => 'user',
            'actor_id' => $designer->getKey(),
            'actor_role' => 'designer',
            'target_type' => 'designer_profile',
            'target_id' => $profile->getKey(),
            'action' => $action,
            'outcome' => 'success',
            'metadata' => [
                'profile_id' => $profile->getKey(),
                'previous_status' => $previousStatus,
                'current_status' => $profile->publication_status,
                'readiness_completed' => $readiness['completed'],
                'readiness_total' => $readiness['total'],
            ],
        ]);
    }

    /** @return array{code: string, section: string, message: string, action: string} */
    private function blocker(string $code, string $section, string $message, string $action): array
    {
        return compact('code', 'section', 'message', 'action');
    }

    private function blank(?string $value): bool
    {
        return trim((string) $value) === '';
    }

    private function missingProfile(): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'أنشئ ملف المصمم الأساسي أولًا.',
            'data' => ['code' => 'designer_profile_required'],
        ], 404));
    }
}
