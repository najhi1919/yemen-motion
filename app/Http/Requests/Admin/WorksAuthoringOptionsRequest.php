<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\Works\WorksSettingsStore;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorksAuthoringOptionsRequest extends FormRequest
{
    /** @var list<string> */
    private const AUTHORING_PERMISSIONS = [
        'admin.works.create',
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

    protected function prepareForValidation(): void
    {
        $search = $this->query('q');
        $limit = $this->query('limit');

        if (is_string($search)) {
            $this->query->set('q', trim($search));
        }

        if (is_string($limit) && preg_match('/^[1-9][0-9]*$/D', $limit) === 1) {
            $this->query->set('limit', (int) $limit);
        }
    }

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
            && collect(self::AUTHORING_PERMISSIONS)->contains(
                fn (string $permission): bool => $user->can($permission),
            );
    }

    /** @return array<string, list<mixed>> */
    public function rules(WorksSettingsStore $settingsStore): array
    {
        $this->settingsSnapshot ??= $settingsStore->getGlobalSettings();

        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:80'],
            'limit' => [
                'sometimes',
                $this->strictIntegerRule(),
                'integer',
                'between:1,25',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->query->all()), ['q', 'limit']) as $parameter) {
                $validator->errors()->add(
                    (string) $parameter,
                    'معامل الاستعلام غير مدعوم لخيارات تأليف الأعمال.',
                );
            }

            $bodyFields = array_unique([
                ...array_keys($this->request->all()),
                ...array_keys($this->json()->all()),
            ]);

            foreach ($bodyFields as $field) {
                $validator->errors()->add(
                    (string) $field,
                    'جسم الطلب غير مدعوم لخيارات تأليف الأعمال.',
                );
            }

            foreach (array_keys($this->allFiles()) as $field) {
                $validator->errors()->add(
                    (string) $field,
                    'الملفات غير مدعومة لخيارات تأليف الأعمال.',
                );
            }
        });
    }

    /** @return array<string, mixed> */
    public function authoringSettings(): array
    {
        return $this->settingsSnapshot ?? [];
    }

    private function strictIntegerRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_int($value)) {
                $fail('يجب أن يكون حقل :attribute عددًا صحيحًا فعليًا.');
            }
        };
    }
}
