<?php

return [
    'yt_dlp' => [
        'binary' => env('YTDLP_BINARY', 'yt-dlp'),
        'inspect_timeout' => env('YTDLP_INSPECT_TIMEOUT', 60),
        'download_timeout' => env('YTDLP_DOWNLOAD_TIMEOUT', 3600),
        'concurrent_fragments' => env('YTDLP_CONCURRENT_FRAGMENTS', 8),
        'retries' => env('YTDLP_RETRIES', 10),
        'fragment_retries' => env('YTDLP_FRAGMENT_RETRIES', 10),
        'socket_timeout' => env('YTDLP_SOCKET_TIMEOUT', 30),
    ],
];
