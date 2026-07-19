<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\Works\WorksSettingsStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorksMediaUploadRequest extends FormRequest
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
            'file' => ['bail', 'required', 'file'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add(
                    (string) $parameter,
                    'معاملات الاستعلام غير مدعومة لرفع وسيط العمل.',
                );
            }

            $bodyKeys = array_keys($this->all());

            foreach (array_diff($bodyKeys, ['file']) as $field) {
                $validator->errors()->add(
                    (string) $field,
                    'حقل الطلب غير مدعوم لرفع وسيط العمل.',
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
