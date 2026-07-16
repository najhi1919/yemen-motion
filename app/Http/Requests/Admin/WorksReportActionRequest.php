<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorksReportActionRequest extends FormRequest
{
    /** @var array<string, string> */
    private const ACTION_PERMISSIONS = [
        'review' => 'admin.works.reports.review',
        'dismiss' => 'admin.works.reports.dismiss',
        'archive' => 'admin.works.reports.archive',
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_BODY_FIELDS = [
        'review' => [],
        'dismiss' => ['resolution_notes'],
        'archive' => [],
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

        $permission = self::ACTION_PERMISSIONS[$this->actionMethod()] ?? null;

        return $permission !== null
            && $user->can('admin.works.access')
            && $user->can('admin.works.reports.view')
            && $user->can($permission);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return match ($this->actionMethod()) {
            'dismiss' => [
                'resolution_notes' => ['bail', 'required', 'string', 'min:5', 'max:2000'],
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'resolution_notes.required' => 'ملاحظات معالجة البلاغ مطلوبة.',
            'resolution_notes.string' => 'ملاحظات معالجة البلاغ يجب أن تكون نصًا.',
            'resolution_notes.min' => 'ملاحظات معالجة البلاغ يجب ألا تقل عن 5 أحرف.',
            'resolution_notes.max' => 'ملاحظات معالجة البلاغ يجب ألا تتجاوز 2000 حرف.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add((string) $parameter, 'معاملات الاستعلام غير مدعومة لإجراءات البلاغات.');
            }

            $allowedFields = self::ALLOWED_BODY_FIELDS[$this->actionMethod()] ?? [];
            $unexpectedFields = array_diff(array_keys($this->request->all()), $allowedFields);

            foreach ($unexpectedFields as $field) {
                $validator->errors()->add((string) $field, 'حقل الطلب غير مدعوم لإجراء البلاغ.');
            }
        });
    }

    private function actionMethod(): string
    {
        $action = $this->route()?->getActionMethod();

        return is_string($action) ? $action : '';
    }
}
