<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\Works\WorksSettingsStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorksAuthoringShowRequest extends FormRequest
{
    /** @var list<string> */
    private const UPDATE_PERMISSIONS = [
        'admin.works.update.basic',
        'admin.works.update.media',
        'admin.works.update.pricing',
        'admin.works.update.delivery',
        'admin.works.update.designer',
        'admin.works.update.private_notes',
        'admin.works.update.category',
        'admin.works.update.tags',
    ];

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
            && collect(self::UPDATE_PERMISSIONS)->contains(
                fn (string $permission): bool => $user->can($permission),
            );
    }

    /** @return array<string, list<mixed>> */
    public function rules(WorksSettingsStore $settingsStore): array
    {
        $this->settingsSnapshot ??= $settingsStore->getGlobalSettings();

        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add(
                    (string) $parameter,
                    'معاملات الاستعلام غير مدعومة لعرض عقد تأليف العمل.',
                );
            }

            $bodyFields = array_unique([
                ...array_keys($this->request->all()),
                ...array_keys($this->json()->all()),
            ]);

            foreach ($bodyFields as $field) {
                $validator->errors()->add(
                    (string) $field,
                    'جسم الطلب غير مدعوم لعرض عقد تأليف العمل.',
                );
            }

            foreach (array_keys($this->allFiles()) as $field) {
                $validator->errors()->add(
                    (string) $field,
                    'الملفات غير مدعومة لعرض عقد تأليف العمل.',
                );
            }
        });
    }

    /** @return array<string, mixed> */
    public function authoringSettings(): array
    {
        return $this->settingsSnapshot ?? [];
    }
}
