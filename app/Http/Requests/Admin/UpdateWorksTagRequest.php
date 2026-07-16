<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWorksTagRequest extends FormRequest
{
    /** @var list<string> */
    private const ALLOWED_BODY_FIELDS = [
        'name_ar',
        'name_en',
        'sort_order',
    ];

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['name_ar', 'name_en'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $trimmed[$field] = trim($value);
            }
        }

        $this->merge($trimmed);
    }

    public function authorize(): bool
    {
        return $this->authorizedFor('admin.works.taxonomy.tags.update');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name_ar' => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
            'name_en' => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add((string) $parameter, 'معاملات الاستعلام غير مدعومة لتحديث وسم الأعمال.');
            }

            $bodyFields = array_keys($this->request->all());
            $unexpectedFields = array_diff($bodyFields, self::ALLOWED_BODY_FIELDS);

            foreach ($unexpectedFields as $field) {
                $validator->errors()->add((string) $field, 'حقل الطلب غير مدعوم لتحديث وسم الأعمال.');
            }

            if (array_intersect($bodyFields, self::ALLOWED_BODY_FIELDS) === []) {
                $validator->errors()->add('tag', 'يجب إرسال حقل واحد على الأقل لتحديث وسم الأعمال.');
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
            && $user->can('admin.works.taxonomy.tags.view')
            && $user->can($permission);
    }
}
