<?php

$gigabyte = 1024 * 1024 * 1024;
$maxRqbitTransferLimitBytes = 4294967295;

return [
    'default_user_quota_bytes' => (int) env('TORRENTS_DEFAULT_USER_QUOTA_BYTES', 2 * $gigabyte),
    'global_active_limit' => (int) env('TORRENTS_GLOBAL_ACTIVE_LIMIT', 100),
    'per_user_active_limit' => (int) env('TORRENTS_PER_USER_ACTIVE_LIMIT', 50),
    'engine' => env('TORRENTS_ENGINE', 'rqbit'),

    'rqbit' => [
        'base_url' => env('RQBIT_BASE_URL', 'http://rqbit:3030'),
        'username' => env('RQBIT_USERNAME'),
        'password' => env('RQBIT_PASSWORD'),
        'timeout' => (int) env('RQBIT_TIMEOUT', 10),
        'add_timeout' => (int) env('RQBIT_ADD_TIMEOUT', 120),
        'metadata_timeout' => (int) env('RQBIT_METADATA_TIMEOUT', 8),
        'metadata_poll_attempts' => (int) env('RQBIT_METADATA_POLL_ATTEMPTS', 90),
        'metadata_poll_interval_ms' => (int) env('RQBIT_METADATA_POLL_INTERVAL_MS', 2000),
        'download_path' => env('RQBIT_DOWNLOAD_PATH', '/downloads'),
        'keep_after_import' => env('RQBIT_KEEP_AFTER_IMPORT', true),
        'torrenting_port' => (int) env('RQBIT_TORRENTING_PORT', 6881),
        'torrenting_max_port' => (int) env('RQBIT_TORRENTING_MAX_PORT', 6999),
        'worker_threads' => (int) env('RQBIT_WORKER_THREADS', 32),
        'max_blocking_threads' => (int) env('RQBIT_MAX_BLOCKING_THREADS', 128),
        'concurrent_init_limit' => (int) env('RQBIT_CONCURRENT_INIT_LIMIT', 64),
        'tracker_refresh_interval' => env('RQBIT_TRACKER_REFRESH_INTERVAL', '30s'),
        'peer_connect_timeout' => env('RQBIT_PEER_CONNECT_TIMEOUT', '2s'),
        'peer_read_write_timeout' => env('RQBIT_PEER_READ_WRITE_TIMEOUT', '10s'),
        'dht_queries_per_second' => (int) env('RQBIT_DHT_QUERIES_PER_SECOND', 256),
        'upload_limit_bytes' => (int) env('RQBIT_UPLOAD_LIMIT_BYTES', $maxRqbitTransferLimitBytes),
        'download_limit_bytes' => (int) env('RQBIT_DOWNLOAD_LIMIT_BYTES', $maxRqbitTransferLimitBytes),
    ],
];
