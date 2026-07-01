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

        // Email ingestion: read BidPrime daily alert emails from a Gmail inbox
        // over IMAP (App Password) instead of the API. Disabled by default; a
        // fake inbox client is used until this is enabled with real credentials.
        'email' => [
            'enabled' => env('GMAIL_INGEST_ENABLED', false),
            'host' => env('GMAIL_IMAP_HOST', 'imap.gmail.com'),
            'port' => (int) env('GMAIL_IMAP_PORT', 993),
            'encryption' => env('GMAIL_IMAP_ENCRYPTION', 'ssl'),
            // Reuse the app's existing Gmail mailbox + App Password (already set
            // for outbound mail) unless dedicated IMAP creds are provided.
            'username' => env('GMAIL_IMAP_USERNAME', env('MAIL_USERNAME')),
            'password' => env('GMAIL_IMAP_APP_PASSWORD', env('MAIL_PASSWORD')),
            'mailbox' => env('GMAIL_IMAP_MAILBOX', 'INBOX'),
            'since_days' => (int) env('GMAIL_INGEST_SINCE_DAYS', 3),
            // Comma-separated sender fragments that identify a BidPrime email.
            'from_filters' => array_values(array_filter(array_map('trim', explode(',', (string) env('GMAIL_BIDPRIME_FROM', 'bidprime.com,bidprime'))))),
            // Optional comma-separated subject fragments to narrow further.
            'subject_filters' => array_values(array_filter(array_map('trim', explode(',', (string) env('GMAIL_BIDPRIME_SUBJECT', ''))))),
        ],
    ],

    'govwin' => [
        'api_key' => env('GOVWIN_API_KEY'),
        'sync_enabled' => env('GOVWIN_SYNC_ENABLED', false),
    ],

    'mail' => [
        // Route every outgoing email through the central system mailer (the
        // platform's verified Gmail account) instead of a user's own connected
        // work mailbox, so all mail is sent from a single address. Set to false
        // to let users send proposal emails from their connected mailbox again.
        'force_central_sender' => env('MAIL_FORCE_CENTRAL_SENDER', true),
    ],
];
