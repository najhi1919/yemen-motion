<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorksReviewActionRequest extends FormRequest
{
    /** @var array<string, string> */
    private const ACTION_PERMISSIONS = [
        'start' => 'admin.works.review.start',
        'assignReviewer' => 'admin.works.review.assign_reviewer',
        'approve' => 'admin.works.review.approve',
        'requestChanges' => 'admin.works.review.request_changes',
        'reject' => 'admin.works.review.reject',
        'publishAfterApproval' => 'admin.works.review.publish_after_approval',
        'reopen' => 'admin.works.review.reopen',
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_BODY_FIELDS = [
        'start' => [],
        'assignReviewer' => ['reviewer_id'],
        'approve' => [],
        'requestChanges' => ['change_request_notes'],
        'reject' => ['rejection_reason'],
        'publishAfterApproval' => [],
        'reopen' => [],
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

        if (! $user->hasAnyRole(['admin', 'staff'])) {
            return false;
        }

        $action = $this->actionMethod();
        $permission = self::ACTION_PERMISSIONS[$action] ?? null;

        return $permission !== null
            && $user->can('admin.works.access')
            && $user->can($permission);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return match ($this->actionMethod()) {
            'assignReviewer' => [
                'reviewer_id' => ['bail', 'required', 'integer', Rule::exists('users', 'id')],
            ],
            'requestChanges' => [
                'change_request_notes' => ['bail', 'required', 'string', 'min:5', 'max:2000'],
            ],
            'reject' => [
                'rejection_reason' => ['bail', 'required', 'string', 'min:5', 'max:2000'],
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reviewer_id.required' => 'معرّف المراجع مطلوب.',
            'reviewer_id.integer' => 'معرّف المراجع يجب أن يكون عددًا صحيحًا.',
            'reviewer_id.exists' => 'المراجع المحدد غير موجود.',
            'change_request_notes.required' => 'ملاحظات طلب التعديلات مطلوبة.',
            'change_request_notes.string' => 'ملاحظات طلب التعديلات يجب أن تكون نصًا.',
            'change_request_notes.min' => 'ملاحظات طلب التعديلات يجب ألا تقل عن 5 أحرف.',
            'change_request_notes.max' => 'ملاحظات طلب التعديلات يجب ألا تتجاوز 2000 حرف.',
            'rejection_reason.required' => 'سبب الرفض مطلوب.',
            'rejection_reason.string' => 'سبب الرفض يجب أن يكون نصًا.',
            'rejection_reason.min' => 'سبب الرفض يجب ألا يقل عن 5 أحرف.',
            'rejection_reason.max' => 'سبب الرفض يجب ألا يتجاوز 2000 حرف.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add((string) $parameter, 'معاملات الاستعلام غير مدعومة لإجراءات المراجعة.');
            }

            $action = $this->actionMethod();
            $allowedFields = self::ALLOWED_BODY_FIELDS[$action] ?? [];
            $unexpectedFields = array_diff(array_keys($this->request->all()), $allowedFields);

            foreach ($unexpectedFields as $field) {
                $validator->errors()->add((string) $field, 'حقل الطلب غير مدعوم لإجراء المراجعة.');
            }

            if ($action !== 'assignReviewer' || $validator->errors()->has('reviewer_id')) {
                return;
            }

            $reviewer = User::query()->find($this->integer('reviewer_id'));

            if (
                ! $reviewer
                || $reviewer->hasAnyRole(['client', 'designer'])
                || ! $reviewer->hasAnyRole(['super-admin', 'admin', 'staff'])
            ) {
                $validator->errors()->add('reviewer_id', 'يجب اختيار مراجع من الأدوار الإدارية الداخلية.');
            }
        });
    }

    private function actionMethod(): string
    {
        $action = $this->route()?->getActionMethod();

        return is_string($action) ? $action : '';
    }
}
