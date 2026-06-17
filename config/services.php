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

        // Quantum View — account-level auto-ingest of every shipment created on
        // the UPS account (UPS.com, WorldShip, etc.). Requires the Quantum View
        // product on the UPS app + an outbound subscription on the account.
        // Until enabled, a simulator drives dev so the pipeline is testable.
        'quantum_view' => [
            'enabled' => env('UPS_QV_ENABLED', false),
            'subscription' => env('UPS_QV_SUBSCRIPTION'),   // subscription name set up on the UPS account
            'organization_id' => env('UPS_QV_ORGANIZATION_ID'), // org these account shipments belong to
        ],
    ],

    /*
    | Daily exchange rates shown on the dashboard. Pulled once a day from a free,
    | no-key FX feed (frankfurter.app / ECB) and cached; on any failure (or in
    | tests, where it's disabled) the dashboard falls back to the static
    | reference rates in App\Support\Currency.
    */
    'exchange_rates' => [
        'enabled' => env('EXCHANGE_RATES_ENABLED', true),
        'base_url' => env('EXCHANGE_RATES_BASE_URL', 'https://api.frankfurter.app'),
        'timeout' => env('EXCHANGE_RATES_TIMEOUT', 4),
    ],

];
