<?php

namespace Database\Factories;

use App\Enums\TorrentSourceType;
use App\Enums\TorrentStatus;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Torrent>
 */
class TorrentFactory extends Factory
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
            'source_type' => TorrentSourceType::Magnet,
            'magnet_uri' => 'magnet:?xt=urn:btih:'.fake()->regexify('[A-Fa-f0-9]{40}'),
            'torrent_file_path' => null,
            'info_hash' => fake()->regexify('[A-Fa-f0-9]{40}'),
            'name' => fake()->words(3, true),
            'status' => TorrentStatus::PendingMetadata,
            'progress' => 0,
            'total_size_bytes' => fake()->numberBetween(1024, 734003200),
            'downloaded_bytes' => 0,
            'qbittorrent_hash' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
