<?php

return [
    'enabled' => env('TRANSFERD_ENABLED', false),
    'public_url' => env('TRANSFERD_PUBLIC_URL', '/__transferd'),
    'signing_key' => env('TRANSFERD_SIGNING_KEY', env('APP_KEY')),
    'url_ttl_seconds' => (int) env('TRANSFERD_URL_TTL_SECONDS', 300),
];
