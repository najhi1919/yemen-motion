<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorksVisibilityActionRequest extends FormRequest
{
    /** @var array<string, string> */
    private const ACTION_PERMISSIONS = [
        'publish' => 'admin.works.publish',
        'unpublish' => 'admin.works.unpublish',
        'hide' => 'admin.works.hide',
        'restore' => 'admin.works.restore_visibility',
        'feature' => 'admin.works.feature',
        'unfeature' => 'admin.works.unfeature',
        'pin' => 'admin.works.pin',
        'unpin' => 'admin.works.unpin',
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

        $action = $this->route()?->getActionMethod();
        $permission = is_string($action) ? self::ACTION_PERMISSIONS[$action] ?? null : null;

        return $permission !== null
            && $user->can('admin.works.access')
            && $user->can($permission);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $key) {
                $validator->errors()->add((string) $key, 'معاملات الاستعلام غير مدعومة لهذا الإجراء.');
            }

            foreach (array_keys($this->request->all()) as $key) {
                $validator->errors()->add((string) $key, 'بيانات الطلب غير مدعومة لهذا الإجراء.');
            }
        });
    }
}
