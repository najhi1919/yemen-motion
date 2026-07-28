<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DesignerWorkAuthoringShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true;
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add((string) $parameter, 'معاملات الاستعلام غير مدعومة.');
            }
        });
    }
}
