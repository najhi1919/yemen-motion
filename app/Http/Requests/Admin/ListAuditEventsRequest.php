<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListAuditEventsRequest extends FormRequest
{
    /**
     * قائمة معاملات البحث والترقيم الوحيدة المسموح بها.
     *
     * @var list<string>
     */
    private const ALLOWED_QUERY_PARAMETERS = [
        'event_type',
        'category',
        'severity',
        'outcome',
        'actor_id',
        'target_type',
        'target_id',
        'from',
        'to',
        'per_page',
        'page',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('super-admin');
    }

    public function rules(): array
    {
        return [
            'event_type' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'severity' => ['nullable', 'string', 'max:40'],
            'outcome' => ['nullable', 'string', 'max:40'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'target_type' => ['nullable', 'string', 'max:80'],
            'target_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
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
                $validator->errors()->add((string) $parameter, 'معامل البحث غير مسموح.');
            }
        });
    }
}
