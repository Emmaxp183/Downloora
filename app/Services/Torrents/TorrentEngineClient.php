<?php

namespace App\Services\Torrents;

use App\Models\Torrent;

interface TorrentEngineClient
{
    public function addMagnet(Torrent $torrent, bool $paused = false, bool $reuseExisting = true): void;

    public function addTorrentFile(Torrent $torrent, bool $paused = false): void;

    /**
     * @return array<string, mixed>
     */
    public function getTorrent(string $hash): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function torrents(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function files(string $hash): array;

    public function delete(string $hash, bool $deleteFiles = true): void;
}
