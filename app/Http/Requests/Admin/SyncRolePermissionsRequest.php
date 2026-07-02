<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('admin.roles.sync_permissions');
    }

    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', 'exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.present' => 'قائمة الصلاحيات مطلوبة.',
            'permissions.array' => 'قائمة الصلاحيات يجب أن تكون مصفوفة.',
            'permissions.*.exists' => 'إحدى الصلاحيات المحددة غير موجودة.',
        ];
    }
}
