<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;

class UploadDesignerAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') ?? false;
    }

    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
                'dimensions:min_width=256,min_height=256',
            ],
        ];
    }
}
