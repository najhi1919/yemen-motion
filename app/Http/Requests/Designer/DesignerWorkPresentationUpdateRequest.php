<?php

namespace App\Http\Requests\Designer;

use App\Models\Work;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class DesignerWorkPresentationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true;
    }

    protected function prepareForValidation(): void
    {
        $unsupported = array_diff(
            array_keys($this->request->all()),
            ['cover_display_mode', 'cover_focal_point'],
        );

        if ($this->query->count() > 0 || $unsupported !== []) {
            $this->merge(['request' => true]);
        }

        $focalPoint = $this->input('cover_focal_point');
        if (is_array($focalPoint)
            && array_diff(array_keys($focalPoint), ['x', 'y']) !== []) {
            $this->merge([
                'cover_focal_point' => [...$focalPoint, 'request' => true],
            ]);
        }
    }

    public function rules(): array
    {
        $strictInteger = static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_int($value)) {
                $fail('يجب أن تكون قيمة :attribute عددًا صحيحًا.');
            }
        };

        return [
            'cover_display_mode' => [
                'bail',
                'present',
                'string',
                'in:'.implode(',', Work::COVER_DISPLAY_MODES),
            ],
            'cover_focal_point' => ['bail', 'present', 'array'],
            'cover_focal_point.x' => ['bail', 'present', $strictInteger, 'integer', 'min:0', 'max:100'],
            'cover_focal_point.y' => ['bail', 'present', $strictInteger, 'integer', 'min:0', 'max:100'],
            'cover_focal_point.request' => ['prohibited'],
            'request' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'cover_display_mode.present' => 'طريقة عرض الغلاف مطلوبة.',
            'cover_display_mode.string' => 'طريقة عرض الغلاف غير صالحة.',
            'cover_display_mode.in' => 'اختر طريقة عرض غلاف مدعومة.',
            'cover_focal_point.present' => 'نقطة تركيز الغلاف مطلوبة.',
            'cover_focal_point.array' => 'نقطة تركيز الغلاف غير صالحة.',
            'cover_focal_point.x.present' => 'الموضع الأفقي مطلوب.',
            'cover_focal_point.x.integer' => 'يجب أن يكون الموضع الأفقي عددًا صحيحًا.',
            'cover_focal_point.x.min' => 'يجب ألا يقل الموضع الأفقي عن 0.',
            'cover_focal_point.x.max' => 'يجب ألا يزيد الموضع الأفقي على 100.',
            'cover_focal_point.y.present' => 'الموضع الرأسي مطلوب.',
            'cover_focal_point.y.integer' => 'يجب أن يكون الموضع الرأسي عددًا صحيحًا.',
            'cover_focal_point.y.min' => 'يجب ألا يقل الموضع الرأسي عن 0.',
            'cover_focal_point.y.max' => 'يجب ألا يزيد الموضع الرأسي على 100.',
            'cover_focal_point.request.prohibited' => 'هذا الحقل غير مسموح به.',
            'request.prohibited' => 'هذا الحقل غير مسموح به.',
        ];
    }
}
