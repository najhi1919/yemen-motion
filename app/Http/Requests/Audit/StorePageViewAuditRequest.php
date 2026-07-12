<?php

namespace App\Http\Requests\Audit;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePageViewAuditRequest extends FormRequest
{
    /**
     * الحقول الوحيدة التي يقبلها endpoint تتبع زيارة الصفحة.
     *
     * @var list<string>
     */
    private const ALLOWED_FIELDS = [
        'page_key',
        'path',
        'section',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyRole([
            'super-admin',
            'admin',
            'staff',
        ]);
    }

    public function rules(): array
    {
        return [
            'page_key' => ['required', 'string', 'min:2', 'max:120'],
            'path' => [
                'bail',
                'required',
                'string',
                'min:1',
                'max:240',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $decodedPath = rawurldecode($value);
                    $isAllowedPath = $value === '/admin'
                        || str_starts_with($value, '/admin/')
                        || $value === '/staff'
                        || str_starts_with($value, '/staff/');
                    $containsQueryOrFragment = str_contains($decodedPath, '?')
                        || str_contains($decodedPath, '#');

                    if (! $isAllowedPath || $containsQueryOrFragment) {
                        $fail('المسار يجب أن يكون مسار إدارة داخليًا دون رابط كامل أو query string.');
                    }
                },
            ],
            'section' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unexpectedFields = array_diff(
                array_keys($this->all()),
                self::ALLOWED_FIELDS,
            );

            foreach ($unexpectedFields as $field) {
                $validator->errors()->add((string) $field, 'هذا الحقل غير مسموح.');
            }
        });
    }
}
