<?php

$gigabyte = 1024 * 1024 * 1024;

return [
    'subscription_type' => env('DOWNLOORA_SUBSCRIPTION_TYPE', 'default'),

    'default_quota_bytes' => (int) env('TORRENTS_DEFAULT_USER_QUOTA_BYTES', 2 * $gigabyte),

    'plans' => [
        'basic' => [
            'id' => 'basic',
            'name' => 'Basic',
            'stripe_price_id' => env('STRIPE_BASIC_PRICE_ID'),
            'quota_bytes' => 50 * $gigabyte,
        ],
        'pro' => [
            'id' => 'pro',
            'name' => 'Pro',
            'stripe_price_id' => env('STRIPE_PRO_PRICE_ID'),
            'quota_bytes' => 100 * $gigabyte,
        ],
        'master' => [
            'id' => 'master',
            'name' => 'Master',
            'stripe_price_id' => env('STRIPE_MASTER_PRICE_ID'),
            'quota_bytes' => 1024 * $gigabyte,
        ],
    ],
];
