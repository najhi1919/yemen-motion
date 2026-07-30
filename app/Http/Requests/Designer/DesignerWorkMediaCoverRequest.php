<?php

namespace App\Http\Requests\Designer;

use App\Services\Works\WorksSettingsStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class DesignerWorkMediaCoverRequest extends FormRequest
{
    private ?array $settings = null;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true;
    }

    public function rules(): array
    {
        $this->settings = app(WorksSettingsStore::class)->getGlobalSettings();

        return ['cover_media_id' => ['present', 'nullable', 'integer', 'min:1']];
    }

    public function mediaSettings(): array
    {
        return $this->settings ??= app(WorksSettingsStore::class)->getGlobalSettings();
    }

    public function messages(): array
    {
        return [
            'cover_media_id.present' => 'يجب تحديد الغلاف أوإرسال قيمة فارغة لإزالته.',
            'cover_media_id.integer' => 'معرّف الغلاف يجب أن يكون عددًا صحيحًا.',
            'cover_media_id.min' => 'معرّف الغلاف غير صالح.',
        ];
    }

    protected function passedValidation(): void
    {
        $extra = array_diff(array_keys($this->all()), ['cover_media_id']);
        if ($extra !== [] || $this->query->count() > 0) {
            throw ValidationException::withMessages([
                $extra[0] ?? 'request' => ['هذا الحقل غير مسموح به.'],
            ]);
        }
    }
}
