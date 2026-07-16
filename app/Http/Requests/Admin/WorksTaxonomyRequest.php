<?php

namespace App\Http\Requests\Admin;

use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorksTaxonomyRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_QUERY_PARAMETERS = [
        'q',
        'category_id',
        'status',
        'visibility_status',
        'media_type',
        'only_uncategorized',
        'only_reported',
        'only_promoted',
        'from',
        'to',
        'sort',
        'direction',
        'page',
        'per_page',
    ];

    /**
     * @var list<string>
     */
    private const SORTABLE_COLUMNS = [
        'works_count',
        'category_id',
        'latest_work_at',
        'reported_count',
        'published_count',
        'hidden_count',
        'total_reports',
        'total_views',
        'total_likes',
    ];

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
            && $user->can('admin.works.taxonomy.view');
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'min:1', 'max:80'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([
                Work::STATUS_DRAFT,
                Work::STATUS_SUBMITTED,
                Work::STATUS_IN_REVIEW,
                Work::STATUS_CHANGES_REQUESTED,
                Work::STATUS_APPROVED,
                Work::STATUS_PUBLISHED,
                Work::STATUS_REJECTED,
                Work::STATUS_HIDDEN,
                Work::STATUS_ARCHIVED,
            ])],
            'visibility_status' => ['nullable', Rule::in([
                Work::VISIBILITY_HIDDEN,
                Work::VISIBILITY_PUBLIC,
            ])],
            'media_type' => ['nullable', 'string', 'max:40'],
            'only_uncategorized' => ['nullable', 'boolean'],
            'only_reported' => ['nullable', 'boolean'],
            'only_promoted' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in(self::SORTABLE_COLUMNS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.max' => 'نص البحث يجب ألا يتجاوز 80 حرفًا.',
            'to.after_or_equal' => 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.',
            'per_page.max' => 'حجم الصفحة يجب ألا يتجاوز 50 عنصرًا.',
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
                $validator->errors()->add((string) $parameter, 'معامل تصنيفات الأعمال غير مسموح.');
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
