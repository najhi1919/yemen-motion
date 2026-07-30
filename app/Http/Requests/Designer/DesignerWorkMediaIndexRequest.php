<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class DesignerWorkMediaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true;
    }

    public function rules(): array
    {
        return [];
    }

    protected function passedValidation(): void
    {
        if ($this->query->count() > 0 || $this->request->count() > 0) {
            throw ValidationException::withMessages([
                'request' => ['هذا الطلب لا يقبل بيانات إضافية.'],
            ]);
        }
    }
}
