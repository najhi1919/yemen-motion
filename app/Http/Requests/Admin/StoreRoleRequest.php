<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('admin.roles.create');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:80',
                'regex:/^[a-z0-9][a-z0-9._-]*[a-z0-9]$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الدور مطلوب.',
            'name.regex' => 'اسم الدور يجب أن يكون بصيغة تقنية مثل support-agent أو finance.manager.',
            'name.unique' => 'هذا الدور موجود مسبقًا.',
        ];
    }
}
