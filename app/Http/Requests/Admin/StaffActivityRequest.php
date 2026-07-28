<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StaffActivityRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_QUERY_PARAMETERS = [
        'event_type',
        'category',
        'severity',
        'outcome',
        'from',
        'to',
        'per_page',
        'page',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ! $user->hasAnyRole(['client', 'designer'])
            && $user->hasAnyRole(['super-admin', 'admin', 'staff'])
            && $user->can('admin.staff.activity.view');
    }

    public function rules(): array
    {
        return [
            'event_type' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'severity' => ['nullable', 'string', 'max:40'],
            'outcome' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unexpected = array_diff(
                array_keys($this->query->all()),
                self::ALLOWED_QUERY_PARAMETERS,
            );

            foreach ($unexpected as $parameter) {
                $validator->errors()->add((string) $parameter, 'معامل البحث غير مسموح.');
            }
        });
    }
}
