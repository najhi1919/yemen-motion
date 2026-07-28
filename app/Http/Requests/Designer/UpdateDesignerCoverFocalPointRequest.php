<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDesignerCoverFocalPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') ?? false;
    }

    public function rules(): array
    {
        return [
            'x' => ['required', 'integer', 'between:0,100'],
            'y' => ['required', 'integer', 'between:0,100'],
        ];
    }
}
