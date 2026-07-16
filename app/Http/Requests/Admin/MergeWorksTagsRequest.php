<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MergeWorksTagsRequest extends FormRequest
{
    /** @var list<string> */
    private const ALLOWED_BODY_FIELDS = [
        'target_tag_id',
        'source_tag_ids',
    ];

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
            && $user->can('admin.works.taxonomy.tags.view')
            && $user->can('admin.works.taxonomy.merge_tags');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'target_tag_id' => [
                'bail',
                'required',
                'integer',
                'min:1',
                Rule::exists('work_tags', 'id')->whereNull('disabled_at'),
            ],
            'source_tag_ids' => ['required', 'array', 'min:1', 'max:25'],
            'source_tag_ids.*' => [
                'bail',
                'integer',
                'min:1',
                'distinct',
                Rule::exists('work_tags', 'id'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add((string) $parameter, 'معاملات الاستعلام غير مدعومة لدمج وسوم الأعمال.');
            }

            foreach (array_diff(array_keys($this->request->all()), self::ALLOWED_BODY_FIELDS) as $field) {
                $validator->errors()->add((string) $field, 'حقل الطلب غير مدعوم لدمج وسوم الأعمال.');
            }

            $targetTagId = $this->input('target_tag_id');
            $sourceTagIds = $this->input('source_tag_ids');

            if (! is_numeric($targetTagId) || ! is_array($sourceTagIds)) {
                return;
            }

            $normalizedSourceTagIds = array_map(
                static fn (mixed $tagId): int => is_numeric($tagId) ? (int) $tagId : 0,
                $sourceTagIds,
            );

            if (in_array((int) $targetTagId, $normalizedSourceTagIds, true)) {
                $validator->errors()->add('source_tag_ids', 'لا يجوز تضمين الوسم الهدف ضمن الوسوم المصدر.');
            }
        });
    }
}
