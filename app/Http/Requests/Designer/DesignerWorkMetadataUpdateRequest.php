<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;

class DesignerWorkMetadataUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true;
    }

    protected function prepareForValidation(): void
    {
        $allowed = ['category_id', 'tag_ids'];
        $unsupported = array_diff(array_keys($this->request->all()), $allowed);

        if ($this->query->count() > 0 || $unsupported !== []) {
            $this->merge(['request' => true]);
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => ['bail', 'present', 'nullable', 'integer', 'min:1'],
            'tag_ids' => ['bail', 'present', 'array', 'max:10'],
            'tag_ids.*' => ['bail', 'integer', 'min:1', 'distinct'],
            'request' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.present' => 'حقل التصنيف مطلوب.',
            'category_id.integer' => 'يجب أن يكون معرّف التصنيف عددًا صحيحًا.',
            'category_id.min' => 'معرّف التصنيف غير صالح.',
            'tag_ids.present' => 'حقل الوسوم مطلوب.',
            'tag_ids.array' => 'يجب إرسال الوسوم في قائمة.',
            'tag_ids.max' => 'لا يمكن اختيار أكثر من 10 وسوم.',
            'tag_ids.*.integer' => 'يجب أن تكون معرّفات الوسوم أعدادًا صحيحة.',
            'tag_ids.*.min' => 'أحد معرّفات الوسوم غير صالح.',
            'tag_ids.*.distinct' => 'لا يمكن تكرار الوسم نفسه.',
            'request.prohibited' => 'هذا الحقل غير مسموح به.',
        ];
    }
}
