<?php

namespace App\Enums;

enum MediaImportStatus: string
{
    case Inspecting = 'inspecting';
    case Ready = 'ready';
    case Queued = 'queued';
    case Downloading = 'downloading';
    case Importing = 'importing';
    case Completed = 'completed';
    case InspectionFailed = 'inspection_failed';
    case QuotaExceeded = 'quota_exceeded';
    case DownloadFailed = 'download_failed';
    case ImportFailed = 'import_failed';
    case Cancelled = 'cancelled';
}
