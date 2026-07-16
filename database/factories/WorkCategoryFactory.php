<?php

namespace Database\Factories;

use App\Models\WorkCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @extends Factory<WorkCategory>
 */
class WorkCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameEn = 'Test Work Category '.Str::upper(Str::random(8));

        return [
            'name_ar' => 'تصنيف أعمال اختباري',
            'name_en' => $nameEn,
            'slug' => Str::slug($nameEn),
            'disabled_at' => null,
            'sort_order' => 0,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'disabled_at' => now(),
        ]);
    }

    public function ordered(int $sortOrder): static
    {
        if ($sortOrder < 0) {
            throw new InvalidArgumentException('The category sort order cannot be negative.');
        }

        return $this->state(fn (array $attributes): array => [
            'sort_order' => $sortOrder,
        ]);
    }
}
