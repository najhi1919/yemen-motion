<?php

namespace App\Http\Requests\Designer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DesignerWorksIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return method_exists($user, 'hasRole')
            ? $user->hasRole('designer')
            : $user->roles()->where('name', 'designer')->exists();
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:80'],
            'group' => ['nullable', Rule::in(['all', 'draft', 'review', 'changes', 'published', 'closed'])],
            'sort' => ['nullable', Rule::in(['updated_at', 'created_at', 'title'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([12, 18, 24])],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();
        $query = trim((string) ($validated['q'] ?? ''));

        return [
            'q' => $query !== '' ? $query : null,
            'group' => (string) ($validated['group'] ?? 'all'),
            'sort' => (string) ($validated['sort'] ?? 'updated_at'),
            'direction' => (string) ($validated['direction'] ?? 'desc'),
            'per_page' => (int) ($validated['per_page'] ?? 12),
        ];
    }

    public function escapedSearch(): ?string
    {
        $query = $this->filters()['q'];

        return $query === null
            ? null
            : str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
    }
}
