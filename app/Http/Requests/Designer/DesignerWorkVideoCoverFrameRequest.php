<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class DesignerWorkVideoCoverFrameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true;
    }

    public function rules(): array
    {
        return [
            'time_ms' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'time_ms.required' => 'يجب تحديد زمن اللقطة المطلوبة.',
            'time_ms.integer' => 'زمن اللقطة يجب أن يكون عددًا صحيحًا بالمللي ثانية.',
            'time_ms.min' => 'زمن اللقطة لا يمكن أن يكون سالبًا.',
        ];
    }

    protected function passedValidation(): void
    {
        $extra = array_diff(array_keys($this->all()), ['time_ms']);

        if ($extra !== [] || $this->query->count() > 0) {
            throw ValidationException::withMessages([
                $extra[0] ?? 'request' => ['هذا الحقل غير مسموح به.'],
            ]);
        }
    }
}
