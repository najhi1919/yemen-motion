<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('admin.roles.update');
    }

    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = $role instanceof Role ? $role->id : null;

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:80',
                'regex:/^[a-z0-9][a-z0-9._-]*[a-z0-9]$/',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($roleId),
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
