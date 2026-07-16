<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateWorksTagRequest extends FormRequest
{
    /** @var list<string> */
    private const ALLOWED_BODY_FIELDS = [
        'name_ar',
        'name_en',
        'slug',
        'sort_order',
    ];

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['name_ar', 'name_en', 'slug'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $trimmed[$field] = trim($value);
            }
        }

        $this->merge($trimmed);
    }

    public function authorize(): bool
    {
        return $this->authorizedFor('admin.works.taxonomy.tags.create');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name_ar' => ['bail', 'required', 'string', 'min:2', 'max:120'],
            'name_en' => ['bail', 'required', 'string', 'min:2', 'max:120'],
            'slug' => [
                'bail',
                'required',
                'string',
                'min:2',
                'max:160',
                'lowercase',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('work_tags', 'slug'),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add((string) $parameter, 'معاملات الاستعلام غير مدعومة لإنشاء وسم الأعمال.');
            }

            $unexpectedFields = array_diff(
                array_keys($this->request->all()),
                self::ALLOWED_BODY_FIELDS,
            );

            foreach ($unexpectedFields as $field) {
                $validator->errors()->add((string) $field, 'حقل الطلب غير مدعوم لإنشاء وسم الأعمال.');
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
