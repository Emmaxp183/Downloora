<?php

namespace Database\Factories;

use App\Models\Torrent;
use App\Models\TorrentFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TorrentFile>
 */
class TorrentFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'torrent_id' => Torrent::factory(),
            'path' => fake()->filePath(),
            'size_bytes' => fake()->numberBetween(1024, 1000000),
            'selected' => true,
            'progress' => 0,
        ];
    }
}
