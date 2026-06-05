<?php

return [
    'default' => env('AI_PROVIDER', 'fake'),

    'providers' => [
        'fake' => [
            'enabled' => true,
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => env('OPENAI_TIMEOUT', 60),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'timeout' => env('ANTHROPIC_TIMEOUT', 60),
        ],
        'local' => [
            'base_url' => env('LOCAL_LLM_URL', 'http://localhost:11434'),
            'model' => env('LOCAL_LLM_MODEL', 'llama3'),
            'timeout' => env('LOCAL_LLM_TIMEOUT', 120),
        ],
    ],

    'extraction' => [
        'max_document_size' => env('AI_MAX_DOC_SIZE', 50000),
        'confidence_threshold' => env('AI_CONFIDENCE_THRESHOLD', 0.7),
    ],
];
