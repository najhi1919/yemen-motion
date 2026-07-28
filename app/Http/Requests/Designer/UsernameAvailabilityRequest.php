<?php

namespace App\Http\Requests\Designer;

use App\Support\UsernamePolicy;
use Illuminate\Foundation\Http\FormRequest;

class UsernameAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => UsernamePolicy::normalize($this->input('username')),
        ]);
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
        ];
    }
}
