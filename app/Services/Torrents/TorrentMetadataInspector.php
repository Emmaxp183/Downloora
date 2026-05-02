<?php

namespace App\Services\Torrents;

use App\Models\Torrent;

interface TorrentMetadataInspector
{
    public function inspect(Torrent $torrent): TorrentMetadata;
}
