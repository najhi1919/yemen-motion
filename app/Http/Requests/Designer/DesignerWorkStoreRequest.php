<?php

namespace App\Http\Requests\Designer;

use App\Models\Work;
use App\Services\Works\WorksSettingsStore;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DesignerWorkStoreRequest extends FormRequest
{
    private const FIELDS = ['title', 'summary', 'description', 'media_type', 'price_amount', 'delivery_days'];

    private ?array $settingsSnapshot = null;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true;
    }

    public function rules(WorksSettingsStore $settingsStore): array
    {
        $settings = $this->resolveSettings($settingsStore);
        $allowedTypes = $settings['values']['media_limits']['allowed_types'] ?? Work::MEDIA_TYPES;

        return [
            'title' => ['bail', 'required', 'string', 'min:2', 'max:160'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:30000'],
            'media_type' => ['sometimes', 'nullable', 'string', Rule::in($allowedTypes)],
            'price_amount' => [
                'sometimes', 'nullable', $this->strictNumericRule(), 'numeric',
                'min:0', 'max:9999999999999.99', $this->twoDecimalsRule(),
            ],
            'delivery_days' => [
                'sometimes', 'nullable', $this->strictIntegerRule(), 'integer', 'between:1,365',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان العمل مطلوب.',
            'title.string' => 'يجب أن يكون عنوان العمل نصًا.',
            'title.min' => 'يجب ألا يقل عنوان العمل عن حرفين.',
            'title.max' => 'يجب ألا يتجاوز عنوان العمل 160 حرفًا.',
            'summary.string' => 'يجب أن يكون الملخص نصًا.',
            'summary.max' => 'يجب ألا يتجاوز الملخص 1000 حرف.',
            'description.string' => 'يجب أن يكون الوصف نصًا.',
            'description.max' => 'يجب ألا يتجاوز الوصف 30000 حرف.',
            'media_type.string' => 'يجب أن يكون نوع العمل نصًا.',
            'media_type.in' => 'نوع العمل المحدد غير مسموح به.',
            'price_amount.numeric' => 'يجب أن يكون السعر رقمًا صالحًا.',
            'price_amount.min' => 'يجب ألا يقل السعر عن صفر.',
            'price_amount.max' => 'قيمة السعر تتجاوز الحد المسموح به.',
            'delivery_days.integer' => 'يجب أن تكون مدة التسليم عددًا صحيحًا.',
            'delivery_days.between' => 'يجب أن تكون مدة التسليم بين يوم واحد و365 يومًا.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'عنوان العمل',
            'summary' => 'الملخص',
            'description' => 'الوصف',
            'media_type' => 'نوع العمل',
            'price_amount' => 'السعر',
            'delivery_days' => 'مدة التسليم',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add((string) $parameter, 'معاملات الاستعلام غير مدعومة لإنشاء مسودة العمل.');
            }
            foreach (array_diff(array_keys($this->request->all()), self::FIELDS) as $field) {
                $validator->errors()->add((string) $field, 'حقل الطلب غير مدعوم لإنشاء مسودة العمل.');
            }
        });
    }

    public function authoringSettings(): array
    {
        return $this->settingsSnapshot ?? [];
    }

    private function resolveSettings(WorksSettingsStore $store): array
    {
        return $this->settingsSnapshot ??= $store->getGlobalSettings();
    }

    private function strictIntegerRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && ! is_int($value)) {
                $fail('يجب أن يكون حقل :attribute عددًا صحيحًا فعليًا.');
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

    private function twoDecimalsRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || (! is_int($value) && ! is_float($value))) {
                return;
            }
            $number = (float) $value;
            $tolerance = PHP_FLOAT_EPSILON * max(1.0, abs($number)) * 4;
            if (! is_finite($number) || abs($number - round($number, 2)) > $tolerance) {
                $fail('يجب ألا يتجاوز حقل :attribute منزلتين عشريتين.');
            }
        };
    }
}
