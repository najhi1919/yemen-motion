<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;

class UploadDesignerProfileOrganizationLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_updated_at' => ['required', 'date'],
            'logo' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ];
    }
}
