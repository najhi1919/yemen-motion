<?php

namespace App\Http\Requests\Admin\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserReportRequest extends FormRequest
{
    /**
     * معاملات التقرير الوحيدة المسموح بتمريرها.
     *
     * @var list<string>
     */
    private const ALLOWED_QUERY_PARAMETERS = [
        'from',
        'to',
        'role',
        'period',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('super-admin');
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'role' => [
                'nullable',
                'string',
                'max:80',
                Rule::exists('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'web')),
            ],
            'period' => ['nullable', 'string', Rule::in(['day', 'week', 'month', 'year'])],
        ];
    }

    public function messages(): array
    {
        return [
            'role.exists' => 'الدور المحدد غير موجود.',
            'period.in' => 'الفترة يجب أن تكون day أو week أو month أو year.',
            'to.after_or_equal' => 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.',
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
                $validator->errors()->add((string) $parameter, 'معامل التقرير غير مسموح.');
            }
        });
    }
}
