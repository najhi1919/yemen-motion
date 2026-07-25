<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorksReviewSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ! $user->hasAnyRole(['client', 'designer'])
            && ($user->hasRole('super-admin') || (
                $user->hasAnyRole(['admin', 'staff'])
                && $user->can('admin.works.access')
                && $user->can('admin.works.review.submit')
            ));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'expected_updated_at' => ['bail', 'required', 'string', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'expected_updated_at.required' => 'وقت آخر تحديث متوقع مطلوب.',
            'expected_updated_at.string' => 'وقت آخر تحديث المتوقع يجب أن يكون نصًا.',
            'expected_updated_at.date' => 'وقت آخر تحديث المتوقع غير صالح.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add((string) $parameter, 'معاملات الاستعلام غير مدعومة لإرسال العمل.');
            }
            foreach (array_diff(array_keys($this->request->all()), ['expected_updated_at']) as $field) {
                $validator->errors()->add((string) $field, 'حقل الطلب غير مدعوم لإرسال العمل.');
            }
        });
    }
}
