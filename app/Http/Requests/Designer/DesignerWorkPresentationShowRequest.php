<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;

class DesignerWorkPresentationShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->query->count() > 0 || $this->request->count() > 0) {
            $this->merge(['request' => true]);
        }
    }

    public function rules(): array
    {
        return ['request' => ['prohibited']];
    }

    public function messages(): array
    {
        return ['request.prohibited' => 'هذا الطلب لا يقبل بيانات إضافية.'];
    }
}
