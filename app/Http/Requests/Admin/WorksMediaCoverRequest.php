<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\Works\WorksSettingsStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorksMediaCoverRequest extends FormRequest
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
            'cover_media_id' => ['present', 'nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add(
                    (string) $parameter,
                    'معاملات الاستعلام غير مدعومة لتحديث غلاف العمل.',
                );
            }

            foreach (array_diff(array_keys($this->all()), ['cover_media_id']) as $field) {
                $validator->errors()->add(
                    (string) $field,
                    'حقل الطلب غير مدعوم لتحديث غلاف العمل.',
                );
            }

            $coverMediaId = $this->input('cover_media_id');

            if ($coverMediaId !== null
                && (! is_int($coverMediaId) || $coverMediaId < 1)) {
                $validator->errors()->add(
                    'cover_media_id',
                    'يجب أن يكون معرّف الغلاف عددًا صحيحًا موجبًا فعليًا أو null.',
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
