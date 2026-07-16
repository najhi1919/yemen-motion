<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Work;
use App\Models\WorkReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkReport>
 */
class WorkReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_id' => Work::factory(),
            'reporter_id' => User::factory(),
            'reason_code' => 'inappropriate_content',
            'details' => 'Reported content requires review.',
            'status' => WorkReport::STATUS_PENDING,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'dismissed_at' => null,
            'archived_at' => null,
            'resolution_notes' => null,
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkReport::STATUS_UNDER_REVIEW,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now()->subHour(),
            'dismissed_at' => null,
            'archived_at' => null,
            'resolution_notes' => null,
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkReport::STATUS_DISMISSED,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now()->subHours(2),
            'dismissed_at' => now()->subHour(),
            'archived_at' => null,
            'resolution_notes' => 'The report was reviewed and dismissed.',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkReport::STATUS_ARCHIVED,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now()->subHours(2),
            'dismissed_at' => null,
            'archived_at' => now()->subHour(),
            'resolution_notes' => null,
        ]);
    }
}
