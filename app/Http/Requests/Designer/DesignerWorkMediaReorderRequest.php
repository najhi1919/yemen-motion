<?php

namespace App\Http\Requests\Designer;

use App\Services\Works\WorksSettingsStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class DesignerWorkMediaReorderRequest extends FormRequest
{
    private ?array $settings = null;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true;
    }

    public function rules(): array
    {
        $this->settings = app(WorksSettingsStore::class)->getGlobalSettings();

        return [
            'media_ids' => ['required', 'array', 'min:1', 'max:100'],
            'media_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    public function mediaSettings(): array
    {
        return $this->settings ??= app(WorksSettingsStore::class)->getGlobalSettings();
    }

    public function messages(): array
    {
        return [
            'media_ids.required' => 'قائمة الوسائط مطلوبة.',
            'media_ids.array' => 'يجب إرسال قائمة وسائط صحيحة.',
            'media_ids.min' => 'يجب أن تحتوي قائمة الترتيب على وسيط واحد على الأقل.',
            'media_ids.max' => 'قائمة الوسائط تتجاوز الحد المسموح.',
            'media_ids.*.integer' => 'معرّف الوسيط يجب أن يكون عددًا صحيحًا.',
            'media_ids.*.min' => 'معرّف الوسيط غير صالح.',
            'media_ids.*.distinct' => 'لا يمكن تكرار الوسيط في الترتيب.',
        ];
    }

    protected function passedValidation(): void
    {
        $extra = array_diff(array_keys($this->all()), ['media_ids']);
        if ($extra !== [] || $this->query->count() > 0) {
            throw ValidationException::withMessages([
                $extra[0] ?? 'request' => ['هذا الحقل غير مسموح به.'],
            ]);
        }
    }
}
