<?php

namespace App\Services\Torrents;

use App\Enums\TorrentSourceType;
use App\Models\Torrent;
use RuntimeException;

class QBittorrentMetadataInspector implements TorrentMetadataInspector
{
    public function __construct(private QBittorrentClient $client) {}

    public function inspect(Torrent $torrent): TorrentMetadata
    {
        $hash = $this->extractInfoHash($torrent);

        if ($torrent->source_type === TorrentSourceType::Magnet) {
            $this->client->addMagnet($torrent, paused: true);
        } elseif ($torrent->source_type === TorrentSourceType::TorrentFile) {
            $this->client->addTorrentFile($torrent, paused: true);
        } else {
            throw new RuntimeException('Unsupported torrent source type.');
        }

        try {
            $details = $this->waitForMetadata($torrent, $hash);
            $hash = strtolower((string) ($details['hash'] ?? $hash));

            if ($hash === '') {
                throw new RuntimeException('qBittorrent did not return a torrent hash.');
            }

            $files = collect($this->client->files($hash))
                ->map(fn (array $file): array => [
                    'path' => (string) ($file['name'] ?? ''),
                    'size_bytes' => (int) ($file['size'] ?? 0),
                ])
                ->filter(fn (array $file): bool => $file['path'] !== '' && $file['size_bytes'] > 0)
                ->values()
                ->all();

            if ($files === []) {
                throw new RuntimeException('qBittorrent did not return torrent files.');
            }

            return new TorrentMetadata(
                name: (string) ($details['name'] ?? 'Unnamed torrent'),
                infoHash: $hash,
                totalSizeBytes: array_sum(array_column($files, 'size_bytes')),
                files: $files,
            );
        } finally {
            if ($hash !== null && $hash !== '') {
                $this->client->delete($hash);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function waitForMetadata(Torrent $torrent, ?string $hash): array
    {
        $attempts = max(1, (int) config('torrents.qbittorrent.metadata_poll_attempts', 30));
        $intervalMs = max(0, (int) config('torrents.qbittorrent.metadata_poll_interval_ms', 1000));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $details = $hash !== null
                ? $this->client->getTorrent($hash)
                : $this->findTorrentBySavePath($torrent);

            if ($this->hasMetadata($details)) {
                return $details;
            }

            if ($attempt < $attempts && $intervalMs > 0) {
                usleep($intervalMs * 1000);
            }
        }

        throw new RuntimeException('Timed out while waiting for torrent metadata.');
    }

    /**
     * @return array<string, mixed>
     */
    private function findTorrentBySavePath(Torrent $torrent): array
    {
        $expectedSavePath = '/downloads/'.($torrent->info_hash ?: $torrent->id);

        foreach ($this->client->torrents() as $candidate) {
            $savePath = rtrim((string) ($candidate['save_path'] ?? ''), '/');

            if ($savePath === $expectedSavePath) {
                return $candidate;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function hasMetadata(array $details): bool
    {
        return ($details['hash'] ?? null) !== null
            && ($details['name'] ?? null) !== null;
    }

    private function extractInfoHash(Torrent $torrent): ?string
    {
        if ($torrent->info_hash !== null) {
            return strtolower($torrent->info_hash);
        }

        if ($torrent->magnet_uri === null) {
            return null;
        }

        parse_str((string) parse_url($torrent->magnet_uri, PHP_URL_QUERY), $query);
        $xt = $query['xt'] ?? null;

        if (! is_string($xt) || ! str_starts_with(strtolower($xt), 'urn:btih:')) {
            return null;
        }

        $infoHash = substr($xt, 9);

        if (preg_match('/\A[0-9a-f]{40}\z/i', $infoHash) === 1) {
            return strtolower($infoHash);
        }

        return null;
    }
}
