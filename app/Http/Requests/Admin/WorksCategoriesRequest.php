<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorksCategoriesRequest extends FormRequest
{
    /** @var list<string> */
    private const ALLOWED_QUERY_PARAMETERS = [
        'q',
        'state',
        'sort',
        'direction',
        'page',
        'per_page',
    ];

    /** @var list<string> */
    public const STATES = ['all', 'active', 'disabled'];

    /** @var list<string> */
    public const SORTS = [
        'sort_order',
        'name_ar',
        'name_en',
        'slug',
        'works_count',
        'created_at',
        'updated_at',
    ];

    /** @var list<int> */
    public const PER_PAGE_OPTIONS = [15, 25, 50];

    protected function prepareForValidation(): void
    {
        $query = $this->query('q');

        if (is_string($query)) {
            $this->merge(['q' => trim($query)]);
        }
    }

    public function authorize(): bool
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
            && $user->can('admin.works.taxonomy.categories.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'min:2', 'max:120'],
            'state' => ['nullable', Rule::in(self::STATES)],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unexpectedParameters = array_diff(
                array_keys($this->query->all()),
                self::ALLOWED_QUERY_PARAMETERS,
            );

            foreach ($unexpectedParameters as $parameter) {
                $validator->errors()->add((string) $parameter, 'معامل كتالوج تصنيفات الأعمال غير مسموح.');
            }
        });
    }
}
