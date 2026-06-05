<?php

return [
    'sam_gov' => [
        'api_key' => env('SAM_GOV_API_KEY'),
        'base_url' => env('SAM_GOV_BASE_URL', 'https://api.sam.gov/opportunities/v2'),
        'sync_enabled' => env('SAM_GOV_SYNC_ENABLED', false),
        'daily_limit' => env('SAM_GOV_DAILY_LIMIT', 1000),
    ],

    'bidprime' => [
        'api_key' => env('BIDPRIME_API_KEY'),
        'base_url' => env('BIDPRIME_BASE_URL', 'https://api.bidprime.com/v1'),
        'sync_enabled' => env('BIDPRIME_SYNC_ENABLED', false),
    ],

    'govwin' => [
        'api_key' => env('GOVWIN_API_KEY'),
        'sync_enabled' => env('GOVWIN_SYNC_ENABLED', false),
    ],
];
