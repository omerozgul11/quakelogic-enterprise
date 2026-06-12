<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-user mailbox providers (Google Workspace / Microsoft 365)
    |--------------------------------------------------------------------------
    | Used to let each user connect their own work email so proposal follow-ups
    | send from their address. Populate these once the Workspace credentials are
    | available; until then the system mailer is used.
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'tenant_id' => env('MICROSOFT_TENANT_ID'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
    ],

    /*
    | Shipments — UPS Tracking API. When sync_enabled is false (default) or
    | credentials are missing, the FakeUpsTrackingClient is bound (no API calls).
    */
    'ups' => [
        'sync_enabled' => env('UPS_SYNC_ENABLED', false),
        'client_id' => env('UPS_CLIENT_ID'),
        'client_secret' => env('UPS_CLIENT_SECRET'),
        'base_url' => env('UPS_BASE_URL', 'https://onlinetools.ups.com'),
    ],

];
