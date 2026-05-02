<?php

namespace App\Enums;

enum TorrentSourceType: string
{
    case Magnet = 'magnet';
    case TorrentFile = 'torrent_file';
}
