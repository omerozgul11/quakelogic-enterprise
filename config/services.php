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
    | Expenses — Intuit QuickBooks Online (Accounting API, OAuth 2.0). When
    | sync_enabled is false (default) or credentials are missing, the
    | FakeQuickBooksClient is bound and no calls hit Intuit — the integration is
    | fully exercisable in dev/tests and "demo connections" can be created so the
    | UI works. Flip sync_enabled + add the Intuit app credentials to go live.
    | environment selects the sandbox vs production API + auth hosts.
    */
    'quickbooks' => [
        'sync_enabled' => env('QUICKBOOKS_SYNC_ENABLED', false),
        'client_id' => env('QUICKBOOKS_CLIENT_ID'),
        'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
        'environment' => env('QUICKBOOKS_ENVIRONMENT', 'production'), // 'sandbox' | 'production'
        'redirect_uri' => env('QUICKBOOKS_REDIRECT_URI'),
        // Stable Intuit endpoints (same for sandbox + production).
        'authorize_url' => env('QUICKBOOKS_AUTHORIZE_URL', 'https://appcenter.intuit.com/connect/oauth2'),
        'token_url' => env('QUICKBOOKS_TOKEN_URL', 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer'),
        'scope' => 'com.intuit.quickbooks.accounting',
        // Webhook verifier token (from the Intuit app's Webhooks page). Enables
        // real-time QuickBooks→app sync: POST /api/quickbooks/webhook, signed
        // with this token, triggers an immediate pull for the affected company.
        'webhook_token' => env('QUICKBOOKS_WEBHOOK_TOKEN'),
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
    | J.B. Hunt 360 tracking (freight). OAuth2 client-credentials, mirroring UPS:
    | RealJbHuntTrackingClient is bound only when JBHUNT_SYNC_ENABLED=true AND
    | credentials are present; otherwise a fake drives dev/tests (and in
    | production the carrier stays manual — see TrackingClientFactory). The
    | endpoint paths/scope/subscription-key are env-driven so the contract can be
    | matched to the J.B. Hunt 360 developer docs (developer.jbhunt.com) without
    | a code change once sandbox credentials are issued.
    */
    'jbhunt' => [
        'sync_enabled' => env('JBHUNT_SYNC_ENABLED', false),
        'client_id' => env('JBHUNT_CLIENT_ID'),
        'client_secret' => env('JBHUNT_CLIENT_SECRET'),
        'base_url' => env('JBHUNT_BASE_URL', 'https://api.jbhunt.com'),
        'token_url' => env('JBHUNT_TOKEN_URL'),            // OAuth2 token endpoint; defaults to base_url + /tokens/oauth2/v1
        'scope' => env('JBHUNT_SCOPE'),                    // optional OAuth scope, if the tenant requires one
        'subscription_key' => env('JBHUNT_SUBSCRIPTION_KEY'), // Apigee/APIM gateway key, sent as a header when set
        'track_path' => env('JBHUNT_TRACK_PATH', '/shipment-tracking/v1/shipments/{tracking}'),
    ],

    /*
    | R+L Carriers (LTL freight) tracking by PRO number. Unlike J.B. Hunt, R+L's
    | public tracing page is reachable without reCAPTCHA, so tracking works with no
    | credentials (the client scrapes the results page). Setting RL_API_KEY (free
    | for R+L account holders at MyRLC → API) switches it to R+L's documented REST
    | API (api.rlc.com/ShipmentTracing) for robust, structured status + history.
    */
    'rlcarriers' => [
        'api_key' => env('RL_API_KEY'),
        'api_base_url' => env('RL_API_BASE_URL', 'https://api.rlc.com'),
        'web_base_url' => env('RL_WEB_BASE_URL', 'https://www.rlcarriers.com'),
    ],

    /*
    | DHL — "Shipment Tracking - Unified" + Push. One DHL-API-Key powers both the
    | request/response tracking API (manual "Refresh", RealDhlTrackingClient) and
    | the push subscriptions that deliver live updates to our webhook
    | (POST /api/dhl/webhook/{token}). Until DHL_API_KEY is set, DHL stays manual /
    | push-only and no calls hit DHL (a fake drives the test suite). base_url is
    | DHL's single production host.
    */
    'dhl' => [
        'api_key' => env('DHL_API_KEY'),
        'base_url' => env('DHL_BASE_URL', 'https://api-eu.dhl.com'),
        'push' => [
            // Secret path segment on our webhook URL — DHL doesn't sign push
            // notifications, so this unguessable token authenticates inbound calls.
            // Generate a random string; the same value goes in the URL you register
            // with DHL. Without it the webhook rejects everything (401).
            'webhook_token' => env('DHL_PUSH_WEBHOOK_TOKEN'),
            // Optional explicit callback URL registered with DHL; defaults to
            // APP_URL + /api/dhl/webhook/{token} when blank.
            'webhook_url' => env('DHL_PUSH_WEBHOOK_URL'),
        ],
    ],

    /*
    | Exchange rates shown on the dashboard. Refreshed on a schedule into a cache
    | the dashboard reads instantly. Source chain (best first):
    |   1. realtime  — free, no-key, near-real-time market quotes (Yahoo Finance).
    |   2. live      — frankfurter.app / ECB daily reference rate.
    |   3. reference — static rates in App\Support\Currency.
    | Each step falls through to the next on failure; in tests `enabled` is false
    | so it always uses the static reference and never touches the network.
    */
    'exchange_rates' => [
        'enabled' => env('EXCHANGE_RATES_ENABLED', true),
        'base_url' => env('EXCHANGE_RATES_BASE_URL', 'https://api.frankfurter.app'),
        'timeout' => env('EXCHANGE_RATES_TIMEOUT', 4),

        // Real-time (intraday) quotes. Yahoo's chart endpoint is free and needs
        // no key, but is unofficial — disable to fall back to the ECB daily feed.
        'realtime_enabled' => env('EXCHANGE_RATES_REALTIME_ENABLED', true),
        'realtime_base_url' => env('EXCHANGE_RATES_REALTIME_BASE_URL', 'https://query1.finance.yahoo.com'),
    ],

];
