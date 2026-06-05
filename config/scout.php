<?php

return [
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),
    'prefix' => env('SCOUT_PREFIX', ''),
    'queue' => env('SCOUT_QUEUE', true),
    'after_commit' => false,
    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],
    'soft_delete' => false,
    'identify' => env('SCOUT_IDENTIFY', false),
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index-settings' => [
            'opportunities' => [
                'filterableAttributes' => ['status', 'source', 'organization_id', 'naics_code'],
                'sortableAttributes' => ['created_at', 'due_date', 'estimated_value'],
            ],
            'proposal_submissions' => [
                'filterableAttributes' => ['status', 'organization_id'],
                'sortableAttributes' => ['created_at', 'due_date', 'proposal_value'],
            ],
        ],
    ],
];
