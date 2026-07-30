<?php

namespace App\Http\Requests\Designer;

use App\Services\Works\WorksSettingsStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class DesignerWorkMediaUploadRequest extends FormRequest
{
    private ?array $settings = null;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true;
    }

    public function rules(): array
    {
        $this->settings = app(WorksSettingsStore::class)->getGlobalSettings();

        return ['file' => ['required', 'file']];
    }

    public function mediaSettings(): array
    {
        return $this->settings ??= app(WorksSettingsStore::class)->getGlobalSettings();
    }

    public function messages(): array
    {
        return [
            'file.required' => 'ملف الوسيط مطلوب.',
            'file.file' => 'يجب اختيار ملف صالح للرفع.',
        ];
    }

    protected function passedValidation(): void
    {
        $extra = array_diff(array_keys($this->all()), ['file']);
        if ($extra !== [] || $this->query->count() > 0) {
            throw ValidationException::withMessages([
                $extra[0] ?? 'request' => ['هذا الحقل غير مسموح به.'],
            ]);
        }
    }
}
