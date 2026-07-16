<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkUpdateWorkCategoryAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->authorizedFor([
            'admin.works.taxonomy.categories.view',
            'admin.works.taxonomy.bulk_assign',
            'admin.works.bulk.category_update',
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'work_ids' => ['required', 'array', 'min:1', 'max:100'],
            'work_ids.*' => ['bail', 'integer', 'min:1', 'distinct', Rule::exists('works', 'id')],
            'category_id' => [
                'present',
                'nullable',
                'integer',
                'min:1',
                Rule::exists('work_categories', 'id')->whereNull('disabled_at'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectQueryParametersAndUnknownFields($validator, ['work_ids', 'category_id']);
        });
    }

    /** @param list<string> $permissions */
    private function authorizedFor(array $permissions): bool
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
            && collect($permissions)->every(fn (string $permission): bool => $user->can($permission));
    }

    /** @param list<string> $allowedFields */
    private function rejectQueryParametersAndUnknownFields(Validator $validator, array $allowedFields): void
    {
        foreach (array_keys($this->query->all()) as $parameter) {
            $validator->errors()->add((string) $parameter, 'معاملات الاستعلام غير مدعومة لإسناد تصنيف الأعمال جماعيًا.');
        }

        foreach (array_diff(array_keys($this->request->all()), $allowedFields) as $field) {
            $validator->errors()->add((string) $field, 'حقل الطلب غير مدعوم لإسناد تصنيف الأعمال جماعيًا.');
        }
    }
}
