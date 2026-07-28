<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncStaffRolesRequest extends FormRequest
{
    private const ALLOWED_INPUTS = [
        'roles',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ! $user->hasAnyRole(['client', 'designer'])
            && $user->hasAnyRole(['super-admin', 'admin', 'staff'])
            && $user->can('admin.staff.assign_roles');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1', 'max:2'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in(['staff', 'admin'])],
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
            'roles.required' => 'حقل الأدوار مطلوب.',
            'roles.array' => 'يجب أن تكون الأدوار مصفوفة.',
            'roles.min' => 'يجب اختيار دور واحد على الأقل.',
            'roles.max' => 'لا يمكن اختيار أكثر من دورين.',
            'roles.*.required' => 'لا يجوز أن يحتوي حقل الأدوار على قيمة فارغة.',
            'roles.*.string' => 'يجب أن يكون كل دور نصًا.',
            'roles.*.distinct' => 'لا يجوز تكرار الدور نفسه.',
            'roles.*.in' => 'الدور المحدد غير مسموح به. الأدوار المتاحة هي staff وadmin فقط.',
        ];
    }
}
