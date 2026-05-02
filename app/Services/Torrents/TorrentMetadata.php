<?php

namespace App\Services\Torrents;

class TorrentMetadata
{
    /**
     * @param  array<int, array{path: string, size_bytes: int}>  $files
     */
    public function __construct(
        public string $name,
        public string $infoHash,
        public int $totalSizeBytes,
        public array $files,
    ) {}
}
