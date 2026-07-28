<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;

class UploadDesignerCoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') ?? false;
    }

    public function rules(): array
    {
        return [
            'cover' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
                'dimensions:min_width=800,min_height=240',
            ],
        ];
    }
}
