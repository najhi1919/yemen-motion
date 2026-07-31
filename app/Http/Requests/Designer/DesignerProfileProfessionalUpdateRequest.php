<?php

namespace App\Http\Requests\Designer;

use App\Models\DesignerProfile;
use App\Models\DesignerProfileLanguage;
use App\Models\DesignerProfileSkill;
use App\Models\DesignerProfileTool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DesignerProfileProfessionalUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('designer') === true;
    }

    protected function prepareForValidation(): void
    {
        $allowed = [
            'expected_updated_at', 'availability', 'years_of_experience', 'professional_note',
            'visibility', 'specialties', 'skills', 'tools', 'languages',
        ];

        if ($this->query->count() > 0 || array_diff(array_keys($this->request->all()), $allowed) !== []) {
            $this->merge(['unsupported_request_input' => true]);
        }

        $specialties = $this->input('specialties');
        if (is_array($specialties)) {
            foreach (['service', 'occasion', 'style'] as $kind) {
                if (isset($specialties[$kind]) && is_array($specialties[$kind])) {
                    $specialties[$kind] = array_map(
                        fn (mixed $name): mixed => is_string($name) ? self::cleanName($name) : $name,
                        $specialties[$kind],
                    );
                }
            }
            $this->merge(['specialties' => $specialties]);
        }

        foreach (['skills', 'tools', 'languages'] as $section) {
            $items = $this->input($section);
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $index => $item) {
                if (is_array($item) && isset($item['name']) && is_string($item['name'])) {
                    $items[$index]['name'] = self::cleanName($item['name']);
                }
            }
            $this->merge([$section => $items]);
        }

        if ($this->has('professional_note') && is_string($this->input('professional_note'))) {
            $note = trim(str_replace(["\r\n", "\r"], "\n", $this->input('professional_note')));
            $this->merge(['professional_note' => $note === '' ? null : $note]);
        }
    }

    public function rules(): array
    {
        return [
            'expected_updated_at' => ['bail', 'required', 'string', 'date'],
            'availability' => ['bail', 'required', 'string', Rule::in(DesignerProfile::AVAILABILITIES)],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:70'],
            'professional_note' => ['nullable', 'string', 'max:1200'],
            'visibility' => ['required', 'array:availability,specialties,skills,tools,languages,experience', 'size:6'],
            'visibility.availability' => ['required', 'boolean'],
            'visibility.specialties' => ['required', 'boolean'],
            'visibility.skills' => ['required', 'boolean'],
            'visibility.tools' => ['required', 'boolean'],
            'visibility.languages' => ['required', 'boolean'],
            'visibility.experience' => ['required', 'boolean'],
            'specialties' => ['required', 'array:service,occasion,style', 'size:3'],
            'specialties.service' => ['present', 'array', 'max:6'],
            'specialties.occasion' => ['present', 'array', 'max:6'],
            'specialties.style' => ['present', 'array', 'max:6'],
            'specialties.*.*' => ['bail', 'string', 'min:2', 'max:80'],
            'skills' => ['present', 'array', 'max:20'],
            'skills.*' => ['array:name,level'],
            'skills.*.name' => ['bail', 'required', 'string', 'min:2', 'max:80'],
            'skills.*.level' => ['bail', 'required', 'string', Rule::in(DesignerProfileSkill::LEVELS)],
            'tools' => ['present', 'array', 'max:20'],
            'tools.*' => ['array:name,level'],
            'tools.*.name' => ['bail', 'required', 'string', 'min:2', 'max:80'],
            'tools.*.level' => ['bail', 'required', 'string', Rule::in(DesignerProfileTool::LEVELS)],
            'languages' => ['present', 'array', 'max:8'],
            'languages.*' => ['array:name,level'],
            'languages.*.name' => ['bail', 'required', 'string', 'min:2', 'max:80'],
            'languages.*.level' => ['bail', 'required', 'string', Rule::in(DesignerProfileLanguage::LEVELS)],
            'unsupported_request_input' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $specialties = $this->input('specialties', []);
            $total = 0;
            if (is_array($specialties)) {
                foreach (['service', 'occasion', 'style'] as $kind) {
                    $items = is_array($specialties[$kind] ?? null) ? $specialties[$kind] : [];
                    $total += count($items);
                    $this->addDuplicateErrors($validator, "specialties.{$kind}", $items);
                }
            }
            if ($total > 12) {
                $validator->errors()->add('specialties', 'لا يمكن إضافة أكثر من 12 تخصصًا إجمالًا.');
            }
            foreach (['skills', 'tools', 'languages'] as $section) {
                $items = is_array($this->input($section)) ? $this->input($section) : [];
                $names = array_map(static fn (mixed $item): mixed => is_array($item) ? ($item['name'] ?? null) : null, $items);
                $this->addDuplicateErrors($validator, $section, $names, '.name');
            }
        });
    }

    public function messages(): array
    {
        return [
            'expected_updated_at.required' => 'نسخة بيانات الملف الحالية مطلوبة.',
            'expected_updated_at.string' => 'يجب إرسال نسخة بيانات الملف كنص.',
            'expected_updated_at.date' => 'نسخة بيانات الملف ليست بتاريخ صالح.',
            'availability.required' => 'حالة التوفر مطلوبة.',
            'availability.string' => 'حالة التوفر غير صالحة.',
            'availability.in' => 'حالة التوفر المحددة غير صالحة.',
            'years_of_experience.min' => 'سنوات الخبرة يجب ألا تقل عن 0.',
            'years_of_experience.max' => 'سنوات الخبرة يجب ألا تزيد على 70.',
            'professional_note.max' => 'المعلومات المهنية الإضافية يجب ألا تتجاوز 1200 حرف.',
            'specialties.*.max' => 'لا يمكن إضافة أكثر من 6 عناصر في هذا النوع.',
            'skills.max' => 'لا يمكن إضافة أكثر من 20 مهارة.',
            'tools.max' => 'لا يمكن إضافة أكثر من 20 أداة.',
            'languages.max' => 'لا يمكن إضافة أكثر من 8 لغات.',
            '*.array' => 'بنية هذا الحقل غير صالحة.',
            '*.required' => 'هذا الحقل مطلوب.',
            '*.string' => 'يجب أن تكون القيمة نصًا.',
            '*.integer' => 'يجب أن تكون القيمة عددًا صحيحًا.',
            '*.boolean' => 'يجب أن تكون قيمة الخصوصية صحيحة أو خاطئة.',
            '*.size' => 'يجب إرسال جميع الحقول المطلوبة دون زيادة أو نقص.',
            '*.min' => 'يجب ألا يقل الاسم عن حرفين.',
            '*.max' => 'يجب ألا يزيد الاسم على 80 حرفًا.',
            '*.in' => 'المستوى المحدد غير صالح.',
            'unsupported_request_input.prohibited' => 'يحتوي الطلب على حقول أو معاملات غير مسموح بها.',
        ];
    }

    public static function cleanName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    public static function normalizedName(string $name): string
    {
        return mb_strtolower(self::cleanName($name), 'UTF-8');
    }

    /** @param array<int, mixed> $values */
    private function addDuplicateErrors(Validator $validator, string $path, array $values, string $suffix = ''): void
    {
        $seen = [];
        foreach ($values as $index => $value) {
            if (! is_string($value) || self::cleanName($value) === '') {
                continue;
            }
            $normalized = self::normalizedName($value);
            if (isset($seen[$normalized])) {
                $validator->errors()->add("{$path}.{$index}{$suffix}", 'لا يمكن تكرار الاسم نفسه.');
            }
            $seen[$normalized] = true;
        }
    }
}
