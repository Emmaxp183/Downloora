<?php

return [
    'yt_dlp' => [
        'binary' => env('YTDLP_BINARY', 'yt-dlp'),
        'inspect_timeout' => env('YTDLP_INSPECT_TIMEOUT', 60),
        'download_timeout' => env('YTDLP_DOWNLOAD_TIMEOUT', 3600),
    ],
];
