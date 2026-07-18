<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorksSettingsUpdateRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_BODY_FIELDS = [
        'version',
        'values',
    ];

    /**
     * @var array<string, string>
     */
    private const FIELD_PERMISSIONS = [
        'review_sla_hours' => 'admin.works.settings.review_sla.manage',
        'direct_publish_trust_enabled' => 'admin.works.settings.direct_publish_trust.manage',
        'media_limits' => 'admin.works.settings.media_limits.manage',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || $user->hasAnyRole(['client', 'designer'])) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        if (! $user->hasAnyRole(['admin', 'staff'])
            || ! $user->can('admin.works.access')
            || ! $user->can('admin.works.settings.view')) {
            return false;
        }

        $values = $this->input('values');

        if (! is_array($values)) {
            return true;
        }

        $hasGlobalPermission = $user->can('admin.works.settings.manage');

        foreach (array_keys($values) as $field) {
            $permission = is_string($field)
                ? self::FIELD_PERMISSIONS[$field] ?? null
                : null;

            if ($permission !== null && ! $hasGlobalPermission && ! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'version' => [
                'required',
                $this->strictIntegerRule(),
                'integer',
                'min:1',
            ],
            'values' => [
                'required',
                'array:review_sla_hours,direct_publish_trust_enabled,media_limits',
                'min:1',
            ],
            'values.review_sla_hours' => [
                'sometimes',
                'nullable',
                $this->strictIntegerRule(),
                'integer',
                'between:1,720',
            ],
            'values.direct_publish_trust_enabled' => [
                'sometimes',
                'required',
                'boolean',
                $this->strictBooleanRule(),
            ],
            'values.media_limits' => [
                'sometimes',
                'array:max_items,max_file_size_kb,allowed_types',
                'min:1',
            ],
            'values.media_limits.max_items' => [
                'sometimes',
                'nullable',
                $this->strictIntegerRule(),
                'integer',
                'between:1,100',
            ],
            'values.media_limits.max_file_size_kb' => [
                'sometimes',
                'nullable',
                $this->strictIntegerRule(),
                'integer',
                'between:1,2097152',
            ],
            'values.media_limits.allowed_types' => [
                'sometimes',
                'nullable',
                'array',
            ],
            'values.media_limits.allowed_types.*' => [
                'string',
                'distinct',
                Rule::in(['image', 'video', 'gallery']),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add(
                    (string) $parameter,
                    'معاملات الاستعلام غير مدعومة لتحديث إعدادات الأعمال.',
                );
            }

            foreach (array_diff(
                array_keys($this->request->all()),
                self::ALLOWED_BODY_FIELDS,
            ) as $field) {
                $validator->errors()->add(
                    (string) $field,
                    'حقل الطلب غير مدعوم لتحديث إعدادات الأعمال.',
                );
            }
        });
    }

    private function strictBooleanRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_bool($value)) {
                $fail('يجب أن يكون حقل :attribute قيمة منطقية فعلية.');
            }
        };
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
