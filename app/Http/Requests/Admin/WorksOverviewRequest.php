<?php

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorksOverviewRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_QUERY_PARAMETERS = [
        'period',
        'from',
        'to',
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
            && $user->can('admin.works.overview.view');
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(['day', 'week', 'month', 'year'])],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'required_with:from', 'after_or_equal:from'],
        ];
    }

    public function messages(): array
    {
        return [
            'period.in' => 'الفترة يجب أن تكون day أو week أو month أو year.',
            'from.required_with' => 'تاريخ البداية مطلوب عند تحديد تاريخ النهاية.',
            'to.required_with' => 'تاريخ النهاية مطلوب عند تحديد تاريخ البداية.',
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
                $validator->errors()->add((string) $parameter, 'معامل النظرة العامة غير مسموح.');
            }

            if (
                $validator->errors()->has('from')
                || $validator->errors()->has('to')
                || ! $this->filled('from')
                || ! $this->filled('to')
            ) {
                return;
            }

            $from = Carbon::parse((string) $this->query('from'))->startOfDay();
            $to = Carbon::parse((string) $this->query('to'))->startOfDay();

            // نقبل عشر سنوات كاملة، ونرفض أي يوم يتجاوز الحد.
            if ($to->gt($from->copy()->addYearsNoOverflow(10))) {
                $validator->errors()->add('to', 'المدى الزمني يجب ألا يتجاوز عشر سنوات.');
            }
        });
    }
}
