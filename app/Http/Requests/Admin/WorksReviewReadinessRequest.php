<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorksReviewReadinessRequest extends FormRequest
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
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add((string) $parameter, 'معاملات الاستعلام غير مدعومة لفحص الجاهزية.');
            }
            foreach (array_keys($this->request->all()) as $field) {
                $validator->errors()->add((string) $field, 'جسم الطلب غير مدعوم لفحص الجاهزية.');
            }
        });
    }
}
