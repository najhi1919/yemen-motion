<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\Works\WorksSettingsStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorksMediaReorderRequest extends FormRequest
{
    /** @var array<string, mixed>|null */
    private ?array $settingsSnapshot = null;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || $user->hasAnyRole(['client', 'designer'])) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasAnyRole(['admin', 'staff'])
            && $user->can('admin.works.access')
            && $user->can('admin.works.update.media');
    }

    /** @return array<string, list<mixed>> */
    public function rules(WorksSettingsStore $settingsStore): array
    {
        $this->settingsSnapshot ??= $settingsStore->getGlobalSettings();

        return [
            'media_ids' => ['bail', 'present', 'array', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add(
                    (string) $parameter,
                    'معاملات الاستعلام غير مدعومة لترتيب وسائط العمل.',
                );
            }

            foreach (array_diff(array_keys($this->all()), ['media_ids']) as $field) {
                $validator->errors()->add(
                    (string) $field,
                    'حقل الطلب غير مدعوم لترتيب وسائط العمل.',
                );
            }

            $mediaIds = $this->input('media_ids');

            if (! is_array($mediaIds)) {
                return;
            }

            if (! array_is_list($mediaIds)) {
                $validator->errors()->add(
                    'media_ids',
                    'يجب أن تكون قائمة معرّفات الوسائط متسلسلة.',
                );
            }

            $containsOnlyValidIntegers = true;

            foreach ($mediaIds as $mediaId) {
                if (! is_int($mediaId) || $mediaId < 1) {
                    $containsOnlyValidIntegers = false;
                    $validator->errors()->add(
                        'media_ids',
                        'يجب أن تحتوي قائمة الوسائط على أعداد صحيحة موجبة فعلية فقط.',
                    );

                    break;
                }
            }

            if ($containsOnlyValidIntegers
                && count($mediaIds) !== count(array_unique($mediaIds))) {
                $validator->errors()->add(
                    'media_ids',
                    'لا يجوز تكرار معرّفات الوسائط.',
                );
            }
        });
    }

    /** @return array<string, mixed> */
    public function mediaSettings(): array
    {
        return $this->settingsSnapshot ?? [];
    }
}
