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
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            // Free-tier eligible Flash model; set to the latest Flash as Google ships it.
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'embed_model' => env('GEMINI_EMBED_MODEL', 'gemini-embedding-001'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
            'timeout' => env('GEMINI_TIMEOUT', 60),
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

    // Seconds a standalone QuakeBot answer is cached per user (saves free-tier
    // quota on repeated questions; short so answers stay fresh as data changes).
    'chat_cache_ttl' => env('AI_CHAT_CACHE_TTL', 900),
];
