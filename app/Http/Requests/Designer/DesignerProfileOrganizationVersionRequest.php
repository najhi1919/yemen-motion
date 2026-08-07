<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;

class DesignerProfileOrganizationVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_updated_at' => ['required', 'date'],
        ];
    }
}
