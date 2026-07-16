<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DisableWorksCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->authorizedFor('admin.works.taxonomy.categories.disable');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add((string) $parameter, 'معاملات الاستعلام غير مدعومة لتعطيل تصنيف الأعمال.');
            }

            foreach (array_keys($this->request->all()) as $field) {
                $validator->errors()->add((string) $field, 'تعطيل تصنيف الأعمال لا يقبل حقولًا في الطلب.');
            }
        });
    }

    private function authorizedFor(string $permission): bool
    {
        $user = $this->user();

        if (! $user || $user->hasAnyRole(['client', 'designer'])) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasAnyRole(['admin', 'staff'])
            && $user->can('admin.works.access')
            && $user->can('admin.works.taxonomy.view')
            && $user->can('admin.works.taxonomy.categories.view')
            && $user->can($permission);
    }
}
