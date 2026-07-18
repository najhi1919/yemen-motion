<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Work;
use App\Models\WorkMedia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkMedia>
 */
class WorkMediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kind = fake()->randomElement(WorkMedia::KINDS);
        $extension = $kind === WorkMedia::KIND_IMAGE ? 'jpg' : 'mp4';
        $token = (string) Str::uuid();

        return [
            'work_id' => Work::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'works_private',
            'path' => 'works/'.$token.'/'.Str::uuid().'.'.$extension,
            'original_name' => 'work-media-'.Str::lower(Str::random(12)).'.'.$extension,
            'mime_type' => $kind === WorkMedia::KIND_IMAGE ? 'image/jpeg' : 'video/mp4',
            'extension' => $extension,
            'kind' => $kind,
            'size_bytes' => fake()->numberBetween(1024, 10 * 1024 * 1024),
            'position' => fake()->numberBetween(0, 20),
            'width' => fake()->numberBetween(640, 3840),
            'height' => fake()->numberBetween(480, 2160),
            'duration_ms' => $kind === WorkMedia::KIND_VIDEO
                ? fake()->numberBetween(1000, 600000)
                : null,
            'processing_status' => WorkMedia::PROCESSING_PENDING,
            'processing_error' => null,
        ];
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes): array => [
            'path' => 'works/'.Str::uuid().'/'.Str::uuid().'.jpg',
            'original_name' => 'work-image-'.Str::lower(Str::random(12)).'.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'kind' => WorkMedia::KIND_IMAGE,
            'duration_ms' => null,
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes): array => [
            'path' => 'works/'.Str::uuid().'/'.Str::uuid().'.mp4',
            'original_name' => 'work-video-'.Str::lower(Str::random(12)).'.mp4',
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
            'kind' => WorkMedia::KIND_VIDEO,
            'duration_ms' => fake()->numberBetween(1000, 600000),
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => [
            'processing_status' => WorkMedia::PROCESSING_READY,
            'processing_error' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'processing_status' => WorkMedia::PROCESSING_FAILED,
            'processing_error' => 'Media processing failed.',
        ]);
    }
}
