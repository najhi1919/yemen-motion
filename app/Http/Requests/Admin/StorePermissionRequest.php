<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('admin.permissions.create');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:120',
                'regex:/^[a-z0-9][a-z0-9._-]*[a-z0-9]$/',
                Rule::unique('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الصلاحية مطلوب.',
            'name.regex' => 'اسم الصلاحية يجب أن يكون بصيغة تقنية مثل custom.reports.export.',
            'name.unique' => 'هذه الصلاحية موجودة مسبقًا.',
        ];
    }
}
