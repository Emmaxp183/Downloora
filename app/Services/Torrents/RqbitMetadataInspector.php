<?php

namespace App\Services\Torrents;

use App\Models\Torrent;
use RuntimeException;

class RqbitMetadataInspector implements TorrentMetadataInspector
{
    public function __construct(private RqbitClient $client) {}

    public function inspect(Torrent $torrent): TorrentMetadata
    {
        $response = $this->client->inspect($torrent);
        $details = data_get($response, 'details', $response);
        $hash = strtolower((string) ($details['info_hash'] ?? ''));

        if ($hash === '') {
            throw new RuntimeException('rqbit did not return a torrent hash.');
        }

        $files = collect($this->client->normalizeFiles($details))
            ->map(fn (array $file): array => [
                'path' => $file['name'],
                'size_bytes' => $file['size'],
            ])
            ->values()
            ->all();

        if ($files === []) {
            throw new RuntimeException('rqbit did not return torrent metadata files.');
        }

        return new TorrentMetadata(
            name: (string) ($details['name'] ?? 'Unnamed torrent'),
            infoHash: $hash,
            totalSizeBytes: array_sum(array_column($files, 'size_bytes')),
            files: $files,
        );
    }
}
