<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StaffLifecycleRequest extends FormRequest
{
    private const ALLOWED_INPUTS = [
        'destroy' => ['confirmation'],
        'disable' => [],
        'restore' => [],
    ];

    private const REQUIRED_PERMISSIONS = [
        'destroy' => 'admin.staff.delete',
        'disable' => 'admin.staff.disable',
        'restore' => 'admin.staff.restore',
    ];

    public function authorize(): bool
    {
        $user = $this->user();
        $action = $this->route()?->getActionMethod();
        $permission = self::REQUIRED_PERMISSIONS[$action] ?? null;

        return $user !== null
            && $permission !== null
            && ! $user->hasAnyRole(['client', 'designer'])
            && $user->hasAnyRole(['super-admin', 'admin', 'staff'])
            && $user->can($permission);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->route()?->getActionMethod() !== 'destroy') {
            return [];
        }

        return [
            'confirmation' => ['required', 'string', Rule::in(['DELETE'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $action = $this->route()?->getActionMethod() ?? '';
            $allowedInputs = self::ALLOWED_INPUTS[$action] ?? [];
            $unexpectedInputs = array_diff(array_keys($this->all()), $allowedInputs);

            foreach ($unexpectedInputs as $input) {
                $validator->errors()->add((string) $input, "الحقل {$input} غير مسموح به.");
            }

            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add((string) $parameter, 'معاملات الرابط غير مسموح بها.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.required' => 'تأكيد الحذف مطلوب.',
            'confirmation.string' => 'يجب أن يكون تأكيد الحذف نصًا.',
            'confirmation.in' => 'يجب أن تكون قيمة تأكيد الحذف DELETE بالضبط.',
        ];
    }
}
