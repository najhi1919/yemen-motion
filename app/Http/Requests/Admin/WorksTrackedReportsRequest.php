<?php

namespace App\Http\Requests\Admin;

use App\Models\WorkReport;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorksTrackedReportsRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_QUERY_PARAMETERS = [
        'status',
        'reason_code',
        'reporter_id',
        'reviewed_by',
        'from',
        'to',
        'sort',
        'direction',
        'page',
        'per_page',
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
            && $user->can('admin.works.reports.view')
            && $user->can('admin.works.reports.list');
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(WorkReport::STATUSES)],
            'reason_code' => ['nullable', 'string', 'max:50', 'regex:/\A[a-z0-9_.-]+\z/'],
            'reporter_id' => ['nullable', 'integer', 'min:1'],
            'reviewed_by' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in([
                'created_at',
                'updated_at',
                'status',
                'reviewed_at',
                'dismissed_at',
                'archived_at',
            ])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50])],
        ];
    }

    public function messages(): array
    {
        return [
            'reason_code.regex' => 'رمز سبب البلاغ يحتوي على محارف غير مسموحة.',
            'to.after_or_equal' => 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.',
            'per_page.in' => 'حجم الصفحة يجب أن يكون 15 أو 25 أو 50 عنصرًا.',
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
                $validator->errors()->add((string) $parameter, 'معامل قائمة البلاغات المتتبعة غير مسموح.');
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

            if ($to->gt($from->copy()->addYearsNoOverflow(10))) {
                $validator->errors()->add('to', 'المدى الزمني يجب ألا يتجاوز عشر سنوات.');
            }
        });
    }
}
