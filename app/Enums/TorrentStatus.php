<?php

namespace App\Enums;

enum TorrentStatus: string
{
    case PendingMetadata = 'pending_metadata';
    case Rejected = 'rejected';
    case Queued = 'queued';
    case Downloading = 'downloading';
    case Importing = 'importing';
    case Completed = 'completed';
    case MetadataFailed = 'metadata_failed';
    case QuotaExceeded = 'quota_exceeded';
    case DownloadFailed = 'download_failed';
    case ImportFailed = 'import_failed';
    case Cancelled = 'cancelled';
}
