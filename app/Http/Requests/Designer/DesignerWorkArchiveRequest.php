<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;

class DesignerWorkArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true;
    }

    protected function prepareForValidation(): void
    {
        if (
            $this->query->count() > 0
            || array_diff(array_keys($this->request->all()), ['expected_updated_at']) !== []
        ) {
            $this->merge(['unsupported_request_input' => true]);
        }
    }

    public function rules(): array
    {
        return [
            'expected_updated_at' => ['bail', 'required', 'string', 'date'],
            'unsupported_request_input' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'expected_updated_at.required' => 'نسخة العمل الحالية مطلوبة.',
            'expected_updated_at.string' => 'يجب إرسال نسخة العمل كنص بتاريخ ISO-8601.',
            'expected_updated_at.date' => 'نسخة العمل المرسلة ليست بتاريخ صالح.',
            'unsupported_request_input.prohibited' => 'يحتوي الطلب على حقول أو معاملات غير مسموح بها.',
        ];
    }
}
