<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;

class UpsertDesignerProfileOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'max:160'],
            'organization_type' => ['required', 'string', 'in:studio,agency,company,brand,other'],
            'description' => ['nullable', 'string', 'max:1000'],
            'website_url' => ['nullable', 'string', 'max:2048', 'url:https'],
            'show_publicly' => ['required', 'boolean'],
            'expected_updated_at' => ['present', 'nullable', 'date'],
        ];
    }
}
