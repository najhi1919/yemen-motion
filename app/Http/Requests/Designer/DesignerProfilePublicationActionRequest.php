<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;

class DesignerProfilePublicationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true
            && $this->user()?->isActive() === true;
    }

    protected function prepareForValidation(): void
    {
        $allowed = ['expected_updated_at'];
        if ($this->query->count() > 0 || array_diff(array_keys($this->request->all()), $allowed) !== []) {
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
            'expected_updated_at.required' => 'نسخة بيانات الملف الحالية مطلوبة.',
            'expected_updated_at.string' => 'يجب إرسال نسخة بيانات الملف كنص.',
            'expected_updated_at.date' => 'نسخة بيانات الملف ليست بتاريخ صالح.',
            'unsupported_request_input.prohibited' => 'يحتوي الطلب على حقول أو معاملات غير مسموح بها.',
        ];
    }
}
