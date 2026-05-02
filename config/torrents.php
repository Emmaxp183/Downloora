<?php

return [
    'default_user_quota_bytes' => (int) env('TORRENTS_DEFAULT_USER_QUOTA_BYTES', 734003200),
    'global_active_limit' => (int) env('TORRENTS_GLOBAL_ACTIVE_LIMIT', 5),

    'qbittorrent' => [
        'base_url' => env('QBITTORRENT_BASE_URL', 'http://qbittorrent:8080'),
        'username' => env('QBITTORRENT_USERNAME'),
        'password' => env('QBITTORRENT_PASSWORD'),
        'timeout' => (int) env('QBITTORRENT_TIMEOUT', 10),
        'metadata_poll_attempts' => (int) env('QBITTORRENT_METADATA_POLL_ATTEMPTS', 30),
        'metadata_poll_interval_ms' => (int) env('QBITTORRENT_METADATA_POLL_INTERVAL_MS', 1000),
    ],
];
