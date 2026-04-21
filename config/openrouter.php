<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenRouter API Configuration
    |--------------------------------------------------------------------------
    | OpenRouter provides access to multiple AI models via an OpenAI-compatible
    | API. We use it for both chat completions and text embeddings.
    */

    'api_key'         => env('OPENROUTER_API_KEY', ''),
    'base_url'        => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
    'chat_model'      => env('OPENROUTER_CHAT_MODEL', 'openai/gpt-4o-mini'),
    'embedding_model' => env('OPENROUTER_EMBEDDING_MODEL', 'openai/text-embedding-3-small'),

    /*
    | HTTP timeout in seconds for API requests
    */
    'timeout' => 60,

    /*
    | Site info sent in headers (recommended by OpenRouter)
    */
    'site_url'  => env('APP_URL', 'http://localhost'),
    'site_name' => env('APP_NAME', 'eCommerce AI Assistant'),
];
