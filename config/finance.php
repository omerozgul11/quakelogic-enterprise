<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Provider
    |--------------------------------------------------------------------------
    |
    | Which gateway processes online invoice payments. Mirrors the AI provider
    | pattern: the default is the deterministic `fake` provider, and a live
    | provider selected without credentials degrades back to `fake` rather than
    | failing — it activates automatically once its keys are configured.
    |
    | Supported: fake, stripe, paypal, square
    |
    */

    'provider' => env('PAYMENT_PROVIDER', 'fake'),

    'currency' => env('FINANCE_DEFAULT_CURRENCY', 'USD'),

    'providers' => [
        'stripe' => [
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
        ],
        'square' => [
            'access_token' => env('SQUARE_ACCESS_TOKEN'),
            'location_id' => env('SQUARE_LOCATION_ID'),
        ],
    ],
];
