<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncStaffPermissionsRequest extends FormRequest
{
    private const ALLOWED_INPUTS = [
        'permissions',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && ! $user->hasAnyRole(['client', 'designer'])
            && $user->hasAnyRole(['super-admin', 'admin', 'staff'])
            && $user->can('admin.staff.assign_permissions');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => [
                'required',
                'string',
                'distinct',
                Rule::in($this->manageablePermissionNames()),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unexpectedInputs = array_diff(array_keys($this->all()), self::ALLOWED_INPUTS);

            foreach ($unexpectedInputs as $input) {
                $validator->errors()->add(
                    $input,
                    "الحقل {$input} غير مسموح به.",
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.present' => 'حقل الصلاحيات مطلوب.',
            'permissions.array' => 'يجب أن تكون الصلاحيات مصفوفة.',
            'permissions.*.required' => 'لا يجوز أن يحتوي حقل الصلاحيات على قيمة فارغة.',
            'permissions.*.string' => 'يجب أن تكون كل صلاحية نصًا.',
            'permissions.*.distinct' => 'لا يجوز تكرار الصلاحية نفسها.',
            'permissions.*.in' => 'الصلاحية المحددة غير مسجلة أو خارج نطاق الصلاحيات التي يمكنك إدارتها.',
        ];
    }

    /**
     * @return list<string>
     */
    private function manageablePermissionNames(): array
    {
        $user = $this->user();
        $registeredNames = collect(config('yemen-motion-permissions.permissions', []))
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name))
            ->unique()
            ->sort()
            ->values();

        if ($user instanceof User && $user->isSuperAdmin()) {
            return $registeredNames->all();
        }

        if (! $user instanceof User) {
            return [];
        }

        return $registeredNames
            ->intersect($user->getAllPermissions()->pluck('name'))
            ->values()
            ->all();
    }
}
