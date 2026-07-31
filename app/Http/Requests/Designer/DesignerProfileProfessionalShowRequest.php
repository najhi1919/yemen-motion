<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;

class DesignerProfileProfessionalShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->query->count() > 0) {
            $this->merge(['unsupported_query' => true]);
        }
    }

    public function rules(): array
    {
        return ['unsupported_query' => ['prohibited']];
    }

    public function messages(): array
    {
        return ['unsupported_query.prohibited' => 'لا يقبل هذا الطلب أي معاملات في الرابط.'];
    }
}
