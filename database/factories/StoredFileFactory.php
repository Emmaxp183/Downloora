<?php

namespace Database\Factories;

use App\Models\StoredFile;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoredFile>
 */
class StoredFileFactory extends Factory
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
            'torrent_id' => Torrent::factory(),
            's3_disk' => 's3',
            's3_bucket' => 'seedr',
            's3_key' => 'users/1/torrents/1/'.fake()->uuid().'.bin',
            'original_path' => fake()->filePath(),
            'name' => fake()->word().'.bin',
            'mime_type' => 'application/octet-stream',
            'size_bytes' => fake()->numberBetween(1, 1000000),
        ];
    }
}
