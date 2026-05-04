<?php

namespace Database\Factories;

use App\Enums\MediaImportStatus;
use App\Models\MediaImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaImport>
 */
class MediaImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_url' => fake()->url(),
            'source_domain' => fake()->domainName(),
            'title' => fake()->sentence(3),
            'thumbnail_url' => fake()->imageUrl(),
            'status' => MediaImportStatus::Inspecting,
            'progress' => 0,
            'duration_seconds' => fake()->numberBetween(30, 3600),
            'estimated_size_bytes' => fake()->numberBetween(1024, 734003200),
            'downloaded_bytes' => 0,
            'formats' => null,
            'selected_format' => null,
            'local_file_path' => null,
            'error_message' => null,
            'inspected_at' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
