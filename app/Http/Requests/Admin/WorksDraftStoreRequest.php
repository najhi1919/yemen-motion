<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Models\Work;
use App\Services\Works\WorksSettingsStore;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorksDraftStoreRequest extends FormRequest
{
    /** @var list<string> */
    private const ALLOWED_BODY_FIELDS = [
        'title',
        'summary',
        'description',
        'media_type',
        'price_amount',
        'delivery_days',
        'designer_id',
        'internal_notes',
    ];

    /** @var array<string, string> */
    private const ADVANCED_FIELD_PERMISSIONS = [
        'price_amount' => 'admin.works.update.pricing',
        'delivery_days' => 'admin.works.update.delivery',
        'designer_id' => 'admin.works.update.designer',
        'internal_notes' => 'admin.works.update.private_notes',
    ];

    /** @var array<string, mixed>|null */
    private ?array $settingsSnapshot = null;

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');

        if (is_string($title)) {
            $this->merge(['title' => trim($title)]);
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

        if (! $user->hasAnyRole(['admin', 'staff'])
            || ! $user->can('admin.works.access')
            || ! $user->can('admin.works.create')) {
            return false;
        }

        foreach (self::ADVANCED_FIELD_PERMISSIONS as $field => $permission) {
            if ($this->request->has($field) && ! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(WorksSettingsStore $settingsStore): array
    {
        $settings = $this->resolveSettings($settingsStore);
        $allowedMediaTypes = $settings['values']['media_limits']['allowed_types']
            ?? Work::MEDIA_TYPES;

        return [
            'title' => ['bail', 'required', 'string', 'min:2', 'max:160'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:30000'],
            'media_type' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in($allowedMediaTypes),
            ],
            'price_amount' => [
                'sometimes',
                'nullable',
                $this->strictNumericRule(),
                'numeric',
                'min:0',
                'max:9999999999999.99',
                $this->maximumTwoDecimalPlacesRule(),
            ],
            'delivery_days' => [
                'sometimes',
                'nullable',
                $this->strictIntegerRule(),
                'integer',
                'between:1,365',
            ],
            'designer_id' => [
                'sometimes',
                'nullable',
                $this->strictPositiveIntegerRule(),
                $this->designerRoleRule(),
            ],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add(
                    (string) $parameter,
                    'معاملات الاستعلام غير مدعومة لإنشاء مسودة العمل.',
                );
            }

            foreach (array_diff(
                array_keys($this->request->all()),
                self::ALLOWED_BODY_FIELDS,
            ) as $field) {
                $validator->errors()->add(
                    (string) $field,
                    'حقل الطلب غير مدعوم لإنشاء مسودة العمل.',
                );
            }
        });
    }

    /** @return array<string, mixed> */
    public function authoringSettings(): array
    {
        return $this->settingsSnapshot ?? [];
    }

    /** @return array<string, mixed> */
    private function resolveSettings(WorksSettingsStore $settingsStore): array
    {
        return $this->settingsSnapshot ??= $settingsStore->getGlobalSettings();
    }

    private function strictIntegerRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && ! is_int($value)) {
                $fail('يجب أن يكون حقل :attribute عددًا صحيحًا فعليًا.');
            }
        };
    }

    private function strictPositiveIntegerRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && (! is_int($value) || $value < 1)) {
                $fail('يجب أن يكون حقل :attribute عددًا صحيحًا موجبًا فعليًا.');
            }
        };
    }

    private function strictNumericRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && ! is_int($value) && ! is_float($value)) {
                $fail('يجب أن يكون حقل :attribute رقمًا فعليًا.');
            }
        };
    }

    private function maximumTwoDecimalPlacesRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || (! is_int($value) && ! is_float($value))) {
                return;
            }

            $numericValue = (float) $value;
            $tolerance = PHP_FLOAT_EPSILON * max(1.0, abs($numericValue)) * 4;

            if (! is_finite($numericValue)
                || abs($numericValue - round($numericValue, 2)) > $tolerance) {
                $fail('يجب ألا يتجاوز حقل :attribute منزلتين عشريتين.');
            }
        };
    }

    private function designerRoleRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || ! is_int($value) || $value < 1) {
                return;
            }

            $isDesigner = User::query()
                ->whereKey($value)
                ->whereHas('roles', fn ($query) => $query->where('name', 'designer'))
                ->exists();

            if (! $isDesigner) {
                $fail('يجب أن يشير حقل :attribute إلى مستخدم يحمل دور المصمم.');
            }
        };
    }
}
