<?php

namespace App\Http\Requests\Designer;

use App\Models\DesignerProfile;
use App\Support\UsernamePolicy;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertDesignerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('username')) {
            $this->merge([
                'username' => UsernamePolicy::normalize($this->input('username')),
            ]);
        }
    }

    public function rules(): array
    {
        $user = $this->user();
        $currentUsername = $user?->username;

        return [
            'username' => [
                $currentUsername === null ? 'required' : 'nullable',
                'string',
                'max:24',
                function (string $attribute, mixed $value, Closure $fail) use ($currentUsername): void {
                    $normalized = UsernamePolicy::normalize(is_string($value) ? $value : null);

                    if ($currentUsername !== null) {
                        if ($normalized !== $currentUsername) {
                            $fail('لا يمكن تغيير اسم المستخدم من هذه الشاشة.');
                        }

                        return;
                    }

                    if ($normalized === null || ! UsernamePolicy::isValid($normalized)) {
                        $fail('اسم المستخدم غير صالح أوغير متاح.');
                    }
                },
                Rule::unique('users', 'username')->ignore($user?->id),
            ],
            'display_name' => ['required', 'string', 'min:2', 'max:120'],
            'professional_title' => ['nullable', 'string', 'max:160'],
            'primary_specialty' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:800'],
            'availability' => ['sometimes', 'required', 'string', Rule::in(DesignerProfile::AVAILABILITIES)],
        ];
    }
}
