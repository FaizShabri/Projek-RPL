<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Anthropic API Configuration
    |--------------------------------------------------------------------------
    |
    | API key dan model untuk integrasi AI Chatbot.
    | API key WAJIB disimpan di .env, TIDAK BOLEH di-expose ke frontend.
    |
    */

    'api_key' => env('ANTHROPIC_API_KEY'),
    'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
    'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 2048),
    'api_url' => 'https://api.anthropic.com/v1/messages',
];
