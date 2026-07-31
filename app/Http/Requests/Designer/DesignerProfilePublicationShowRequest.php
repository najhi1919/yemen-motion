<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;

class DesignerProfilePublicationShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true
            && $this->user()?->isActive() === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->query->count() > 0 || $this->request->count() > 0) {
            $this->merge(['unsupported_request_input' => true]);
        }
    }

    public function rules(): array
    {
        return ['unsupported_request_input' => ['prohibited']];
    }

    public function messages(): array
    {
        return ['unsupported_request_input.prohibited' => 'لا يقبل هذا الطلب أي حقول أو معاملات في الرابط.'];
    }
}
