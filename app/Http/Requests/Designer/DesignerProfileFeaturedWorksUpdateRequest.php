<?php

declare(strict_types=1);

namespace App\Http\Requests\Designer;

use App\Services\Designer\DesignerProfileFeaturedWorksService;
use Illuminate\Foundation\Http\FormRequest;

class DesignerProfileFeaturedWorksUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true
            && $this->user()?->isActive() === true;
    }

    protected function prepareForValidation(): void
    {
        $allowed = ['expected_updated_at', 'work_ids'];

        if (
            $this->query->count() > 0
            || array_diff(array_keys($this->request->all()), $allowed) !== []
        ) {
            $this->merge(['unsupported_request_input' => true]);
        }
    }

    public function rules(): array
    {
        return [
            'expected_updated_at' => ['bail', 'required', 'string', 'date'],
            'work_ids' => [
                'present',
                'array',
                'max:'.DesignerProfileFeaturedWorksService::LIMIT,
            ],
            'work_ids.*' => [
                'bail',
                'required',
                'integer',
                'min:1',
                'distinct:strict',
            ],
            'unsupported_request_input' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'expected_updated_at.required' => 'نسخة بيانات الملف الحالية مطلوبة.',
            'expected_updated_at.string' => 'يجب إرسال نسخة بيانات الملف كنص.',
            'expected_updated_at.date' => 'نسخة بيانات الملف ليست بتاريخ صالح.',
            'work_ids.present' => 'قائمة الأعمال المميزة مطلوبة.',
            'work_ids.array' => 'قائمة الأعمال المميزة غير صالحة.',
            'work_ids.max' => 'لا يمكن اختيار أكثر من 6 أعمال مميزة.',
            'work_ids.*.required' => 'معرف العمل مطلوب.',
            'work_ids.*.integer' => 'يجب أن يكون معرف العمل عددًا صحيحًا.',
            'work_ids.*.min' => 'معرف العمل غير صالح.',
            'work_ids.*.distinct' => 'لا يمكن تكرار العمل نفسه.',
            'unsupported_request_input.prohibited' => 'يحتوي الطلب على حقول أو معاملات غير مسموح بها.',
        ];
    }
}
