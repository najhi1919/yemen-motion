<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorksMediaDeleteRequest extends FormRequest
{
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
            && $user->can('admin.works.update.media');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->query->all()) as $parameter) {
                $validator->errors()->add(
                    (string) $parameter,
                    'معاملات الاستعلام غير مدعومة لحذف وسيط العمل.',
                );
            }

            foreach (array_keys($this->all()) as $field) {
                $validator->errors()->add(
                    (string) $field,
                    'جسم الطلب غير مدعوم لحذف وسيط العمل.',
                );
            }
        });
    }
}
