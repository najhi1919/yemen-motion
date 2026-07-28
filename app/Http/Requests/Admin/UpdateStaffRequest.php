<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStaffRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_INPUTS = [
        'name',
        'email',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ! $user->hasAnyRole(['client', 'designer'])
            && $user->hasAnyRole(['super-admin', 'admin', 'staff'])
            && $user->can('admin.staff.update');
    }

    public function rules(): array
    {
        $staff = $this->route('staff');
        $staffId = $staff instanceof User ? $staff->getKey() : $staff;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($staffId),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unexpectedInputs = array_diff(
                array_keys($this->all()),
                self::ALLOWED_INPUTS,
            );

            foreach ($unexpectedInputs as $input) {
                $validator->errors()->add(
                    (string) $input,
                    'هذا الحقل غير مسموح ضمن تعديل البيانات الأساسية.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الموظف مطلوب.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
            'email.unique' => 'هذا البريد الإلكتروني مستخدم مسبقًا.',
        ];
    }
}
