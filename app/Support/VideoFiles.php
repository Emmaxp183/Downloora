<?php

namespace App\Support;

use App\Models\StoredFile;
use Illuminate\Support\Str;

class VideoFiles
{
    public static function isVideo(StoredFile $storedFile): bool
    {
        if (Str::startsWith((string) $storedFile->mime_type, 'video/')) {
            return true;
        }

        return in_array(Str::lower(pathinfo($storedFile->name, PATHINFO_EXTENSION)), self::extensions(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function extensions(): array
    {
        return [
            '3gp',
            'asf',
            'avi',
            'divx',
            'dv',
            'f4v',
            'flv',
            'm2ts',
            'm2v',
            'm4v',
            'mkv',
            'mov',
            'mp4',
            'mpeg',
            'mpg',
            'mts',
            'ogm',
            'ogv',
            'rm',
            'rmvb',
            'ts',
            'vob',
            'webm',
            'wmv',
        ];
    }
}
