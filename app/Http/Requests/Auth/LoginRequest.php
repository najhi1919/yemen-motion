<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $loginRules = ['nullable', 'required_without:email', 'string', 'max:255'];

        if (str_contains(trim((string) $this->input('login')), '@')) {
            $loginRules[] = 'email';
        }

        return [
            'login' => $loginRules,
            'email' => ['nullable', 'required_without:login', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function identifier(): string
    {
        return $this->filled('login')
            ? (string) $this->input('login')
            : (string) $this->input('email');
    }
}
