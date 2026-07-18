<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Work>
 */
class WorkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(8)),
            'summary' => fake()->optional()->sentence(),
            'description' => fake()->optional()->paragraphs(2, true),
            'status' => Work::STATUS_DRAFT,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'media_type' => fake()->optional()->randomElement(Work::MEDIA_TYPES),
            'cover_media_id' => null,
            'price_amount' => fake()->optional()->randomFloat(2, 1000, 1000000),
            'delivery_days' => fake()->optional()->numberBetween(1, 60),
            'designer_id' => User::factory(),
            'reviewer_id' => null,
            'category_id' => null,
            'is_featured' => false,
            'is_pinned' => false,
            'is_trusted_direct_publish' => false,
            'views_count' => 0,
            'likes_count' => 0,
            'reports_count' => 0,
            'submitted_at' => null,
            'reviewed_at' => null,
            'approved_at' => null,
            'published_at' => null,
            'rejected_at' => null,
            'hidden_at' => null,
            'archived_at' => null,
            'rejection_reason' => null,
            'change_request_notes' => null,
            'internal_notes' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Work::STATUS_SUBMITTED,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'submitted_at' => now()->subHours(fake()->numberBetween(1, 36)),
        ]);
    }

    public function inReview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Work::STATUS_IN_REVIEW,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'submitted_at' => now()->subHours(fake()->numberBetween(1, 36)),
            'reviewer_id' => User::factory(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Work::STATUS_APPROVED,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'submitted_at' => now()->subDays(3),
            'reviewed_at' => now()->subDays(2),
            'approved_at' => now()->subDays(2),
            'reviewer_id' => User::factory(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Work::STATUS_PUBLISHED,
            'visibility_status' => Work::VISIBILITY_PUBLIC,
            'submitted_at' => now()->subDays(4),
            'reviewed_at' => now()->subDays(3),
            'approved_at' => now()->subDays(3),
            'published_at' => now()->subDays(2),
            'reviewer_id' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Work::STATUS_REJECTED,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'submitted_at' => now()->subDays(3),
            'reviewed_at' => now()->subDays(2),
            'rejected_at' => now()->subDays(2),
            'rejection_reason' => fake()->sentence(),
            'reviewer_id' => User::factory(),
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Work::STATUS_HIDDEN,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'submitted_at' => now()->subDays(5),
            'reviewed_at' => now()->subDays(4),
            'approved_at' => now()->subDays(4),
            'published_at' => now()->subDays(3),
            'hidden_at' => now(),
            'reviewer_id' => User::factory(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Work::STATUS_ARCHIVED,
            'visibility_status' => Work::VISIBILITY_HIDDEN,
            'archived_at' => now(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_featured' => true,
        ]);
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_pinned' => true,
        ]);
    }

    public function reported(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reports_count' => fake()->numberBetween(1, 8),
        ]);
    }
}
