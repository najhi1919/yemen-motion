<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designer\UpsertDesignerProfileOrganizationRequest;
use App\Http\Requests\Designer\UploadDesignerProfileOrganizationLogoRequest;
use App\Http\Requests\Designer\DesignerProfileOrganizationVersionRequest;
use App\Models\DesignerProfileOrganization;
use App\Models\DesignerProfile;
use App\Services\Audit\AuditEventLogger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DesignerProfileOrganizationController extends Controller
{
    private const DISK = 'works_private';

    public function __construct(private readonly AuditEventLogger $auditLogger)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $profile = $this->profile($request);
        $org = $profile->organization;

        if (!$org) {
            return response()->json([
                'success' => true,
                'data' => [
                    'organization' => null,
                    'updated_at' => null,
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'organization' => [
                    'name' => $org->organization_name,
                    'type' => $org->organization_type,
                    'description' => $org->description,
                    'has_logo' => $org->logo_path !== null,
                    'website_url' => $org->website_url,
                    'show_publicly' => $org->show_publicly,
                ],
                'updated_at' => $org->updated_at,
            ]
        ]);
    }

    public function upsert(UpsertDesignerProfileOrganizationRequest $request): JsonResponse
    {
        $profile = $this->profile($request);
        $expectedUpdatedAt = $request->input('expected_updated_at');
        $validated = $request->validated();

        $canonical = [
            'organization_name' => $validated['organization_name'],
            'organization_type' => $validated['organization_type'],
            'description' => $validated['description'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
            'show_publicly' => (bool) $validated['show_publicly'],
        ];

        return DB::transaction(function () use ($profile, $expectedUpdatedAt, $canonical, $request) {
            $lockedProfile = DesignerProfile::whereKey($profile->id)->lockForUpdate()->first();
            $org = DesignerProfileOrganization::query()
                ->where('designer_profile_id', $lockedProfile->id)
                ->lockForUpdate()
                ->first();

            if ($expectedUpdatedAt === null) {
                if ($org !== null) {
                    $this->conflictResponse($org->updated_at);
                }
            } else {
                if ($org === null) {
                    $this->conflictResponse(null);
                }
                if (!$org->updated_at->equalTo(Carbon::parse($expectedUpdatedAt))) {
                    $this->conflictResponse($org->updated_at);
                }
            }

            if ($org === null) {
                $org = new DesignerProfileOrganization(array_merge([
                    'designer_profile_id' => $lockedProfile->id,
                ], $canonical));

                $org->save();
                $org->refresh();

                $this->auditLogger->record([
                    'event_type' => 'designer.profile.organization.created',
                    'category' => 'designer_profiles',
                    'severity' => 'notice',
                    'actor_type' => 'user',
                    'actor_id' => $request->user()->id,
                    'actor_role' => 'designer',
                    'target_type' => 'designer_profile_organization',
                    'target_id' => $org->id,
                    'action' => 'create',
                    'outcome' => 'success',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'profile_id' => $lockedProfile->id,
                        'organization_id' => $org->id,
                        'operation' => 'create',
                    ]
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تم حفظ بيانات المنشأة.',
                    'data' => [
                        'changed' => true,
                        'updated_at' => $org->updated_at,
                    ]
                ]);
            }

            $fieldsToUpdate = [];
            $metadata = [
                'profile_id' => $lockedProfile->id,
                'organization_id' => $org->id,
                'operation' => 'update',
                'changed_fields' => [],
            ];

            if ($org->organization_name !== $canonical['organization_name']) {
                $fieldsToUpdate['organization_name'] = $canonical['organization_name'];
                $metadata['changed_fields'][] = 'organization_name';
                $metadata['name_changed'] = true;
            }
            if ($org->organization_type !== $canonical['organization_type']) {
                $fieldsToUpdate['organization_type'] = $canonical['organization_type'];
                $metadata['changed_fields'][] = 'organization_type';
                $metadata['previous_type'] = $org->organization_type;
                $metadata['current_type'] = $canonical['organization_type'];
            }
            if ($org->description !== $canonical['description']) {
                $fieldsToUpdate['description'] = $canonical['description'];
                $metadata['changed_fields'][] = 'description';
            }
            if ($org->website_url !== $canonical['website_url']) {
                $fieldsToUpdate['website_url'] = $canonical['website_url'];
                $metadata['changed_fields'][] = 'website_url';
            }
            if ((bool) $org->show_publicly !== $canonical['show_publicly']) {
                $fieldsToUpdate['show_publicly'] = $canonical['show_publicly'];
                $metadata['changed_fields'][] = 'show_publicly';
                $metadata['visibility_changed'] = true;
                $metadata['previous_show_publicly'] = (bool) $org->show_publicly;
                $metadata['current_show_publicly'] = $canonical['show_publicly'];
            }

            if (empty($fieldsToUpdate)) {
                return response()->json([
                    'success' => true,
                    'message' => 'لم يتم تعديل أي بيانات.',
                    'data' => [
                        'changed' => false,
                        'updated_at' => $org->updated_at,
                    ]
                ]);
            }

            $org->update($fieldsToUpdate);
            $org->refresh();

            $this->auditLogger->record([
                'event_type' => 'designer.profile.organization.updated',
                'category' => 'designer_profiles',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $request->user()->id,
                'actor_role' => 'designer',
                'target_type' => 'designer_profile_organization',
                'target_id' => $org->id,
                'action' => 'update',
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => $metadata
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات المنشأة.',
                'data' => [
                    'changed' => true,
                    'updated_at' => $org->updated_at,
                ]
            ]);
        });
    }

    public function destroy(DesignerProfileOrganizationVersionRequest $request): JsonResponse
    {
        $profile = $this->profile($request);
        $expectedUpdatedAt = $request->input('expected_updated_at');

        $oldLogoPath = null;
        $orgId = null;

        DB::transaction(function () use ($profile, $expectedUpdatedAt, &$oldLogoPath, &$orgId, $request) {
            $org = DesignerProfileOrganization::where('designer_profile_id', $profile->id)->lockForUpdate()->first();

            if ($org === null) {
                abort(404);
            }

            if (!$org->updated_at->equalTo(Carbon::parse($expectedUpdatedAt))) {
                $this->conflictResponse($org->updated_at);
            }

            $oldLogoPath = $org->logo_path;
            $orgId = $org->id;

            $org->delete();

            $this->auditLogger->record([
                'event_type' => 'designer.profile.organization.deleted',
                'category' => 'designer_profiles',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $request->user()->id,
                'actor_role' => 'designer',
                'target_type' => 'designer_profile_organization',
                'target_id' => $orgId,
                'action' => 'delete',
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'profile_id' => $profile->id,
                    'organization_id' => $orgId,
                    'operation' => 'delete',
                ]
            ]);
        });

        if ($oldLogoPath) {
            try {
                Storage::disk(self::DISK)->delete($oldLogoPath);
            } catch (Throwable $e) {
                logger()->error('Failed to cleanup organization logo', ['path' => $oldLogoPath, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المنشأة بنجاح.'
        ]);
    }

    public function storeLogo(UploadDesignerProfileOrganizationLogoRequest $request): JsonResponse
    {
        $profile = $this->profile($request);
        $expectedUpdatedAt = $request->input('expected_updated_at');

        $file = $request->file('logo');
        $directory = "designer-profiles/{$profile->user_id}/organization-logo";
        $filename = Str::uuid()->toString() . '.' . strtolower($file->extension());

        $newPath = $file->storeAs($directory, $filename, self::DISK);

        $oldPath = null;
        $updatedAt = null;

        try {
            DB::transaction(function () use ($profile, $expectedUpdatedAt, $newPath, &$oldPath, &$updatedAt, $request) {
                $org = DesignerProfileOrganization::where('designer_profile_id', $profile->id)->lockForUpdate()->first();

                if ($org === null) {
                    abort(404, 'يجب إنشاء المنشأة أولاً.');
                }

                if (!$org->updated_at->equalTo(Carbon::parse($expectedUpdatedAt))) {
                    $this->conflictResponse($org->updated_at);
                }

                $oldPath = $org->logo_path;
                $org->update(['logo_path' => $newPath]);
                $org->refresh();
                $updatedAt = $org->updated_at;

                $this->auditLogger->record([
                    'event_type' => 'designer.profile.organization.logo_uploaded',
                    'category' => 'designer_profiles',
                    'severity' => 'notice',
                    'actor_type' => 'user',
                    'actor_id' => $request->user()->id,
                    'actor_role' => 'designer',
                    'target_type' => 'designer_profile_organization',
                    'target_id' => $org->id,
                    'action' => 'upload_logo',
                    'outcome' => 'success',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'metadata' => [
                        'profile_id' => $profile->id,
                        'organization_id' => $org->id,
                        'logo_changed' => true,
                        'operation' => 'logo_upload',
                    ]
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($newPath);
            throw $exception;
        }

        if ($oldPath !== null) {
            try {
                Storage::disk(self::DISK)->delete($oldPath);
            } catch (Throwable $exception) {
                logger()->error('Failed to cleanup old organization logo', ['path' => $oldPath, 'error' => $exception->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ شعار المنشأة.',
            'data' => [
                'updated_at' => $updatedAt,
            ]
        ]);
    }

    public function destroyLogo(DesignerProfileOrganizationVersionRequest $request): JsonResponse
    {
        $profile = $this->profile($request);
        $expectedUpdatedAt = $request->input('expected_updated_at');

        $oldPath = null;
        $updatedAt = null;
        $changed = false;

        DB::transaction(function () use ($profile, $expectedUpdatedAt, &$oldPath, &$updatedAt, &$changed, $request) {
            $org = DesignerProfileOrganization::where('designer_profile_id', $profile->id)->lockForUpdate()->first();

            if ($org === null) {
                abort(404, 'المنشأة غير موجودة.');
            }

            if (!$org->updated_at->equalTo(Carbon::parse($expectedUpdatedAt))) {
                $this->conflictResponse($org->updated_at);
            }

            if ($org->logo_path === null) {
                $changed = false;
                $updatedAt = $org->updated_at;
                return;
            }

            $oldPath = $org->logo_path;
            $org->update(['logo_path' => null]);
            $org->refresh();
            $updatedAt = $org->updated_at;
            $changed = true;

            $this->auditLogger->record([
                'event_type' => 'designer.profile.organization.logo_removed',
                'category' => 'designer_profiles',
                'severity' => 'notice',
                'actor_type' => 'user',
                'actor_id' => $request->user()->id,
                'actor_role' => 'designer',
                'target_type' => 'designer_profile_organization',
                'target_id' => $org->id,
                'action' => 'remove_logo',
                'outcome' => 'success',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'profile_id' => $profile->id,
                    'organization_id' => $org->id,
                    'logo_changed' => true,
                    'operation' => 'logo_remove',
                ]
            ]);
        });

        if (!$changed) {
            return response()->json([
                'success' => true,
                'message' => 'لا يوجد شعار لحذفه.',
                'data' => [
                    'changed' => false,
                ]
            ]);
        }

        try {
            Storage::disk(self::DISK)->delete($oldPath);
        } catch (Throwable $exception) {
            logger()->error('Failed to cleanup old organization logo', ['path' => $oldPath, 'error' => $exception->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الشعار بنجاح.',
            'data' => [
                'changed' => true,
                'updated_at' => $updatedAt,
            ]
        ]);
    }

    public function logoContent(Request $request): StreamedResponse
    {
        $profile = $this->profile($request);
        $org = $profile->organization;

        abort_if($org === null || $org->logo_path === null, 404);

        $disk = Storage::disk(self::DISK);
        abort_if(!$disk->exists($org->logo_path), 404);

        $mime = $disk->mimeType($org->logo_path) ?: 'application/octet-stream';

        return response()->stream(function () use ($disk, $org): void {
            $stream = $disk->readStream($org->logo_path);

            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function profile(Request $request): DesignerProfile
    {
        $user = $request->user();

        abort_unless($user?->hasRole('designer'), 403, 'هذا المسار مخصص للمصممين.');
        abort_if($user->designerProfile === null, 404, 'أنشئ ملف المصمم الأساسي أولًا.');

        return $user->designerProfile;
    }

    private function conflictResponse(?Carbon $currentUpdatedAt): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'تغيرت بيانات المنشأة في الخادم. حمّل النسخة الأحدث ثم حاول مجددًا.',
            'data' => [
                'code' => 'organization_version_conflict',
                'current_updated_at' => $currentUpdatedAt?->toJSON(),
            ]
        ], 409));
    }
}
