<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        // Meta "App ID" is numeric; tolerate spaces from copy/paste. Fallback names match common tutorials.
        'client_id' => preg_replace('/\s+/u', '', trim((string) (env('FACEBOOK_CLIENT_ID') ?: env('FACEBOOK_APP_ID')))),
        'client_secret' => trim((string) env('FACEBOOK_CLIENT_SECRET')),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
        // Optional: Meta → Facebook Login for Business → Configurations → Configuration ID (User token flows).
        'login_config_id' => trim((string) env('FACEBOOK_LOGIN_CONFIG_ID')),
    ],

    'x' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_REDIRECT_URI'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    'linkedin' => [
        'api_version' => env('LINKEDIN_API_VERSION', '202505'),
    ],

    'ai' => [
        'driver' => env('AI_DRIVER', 'stub'),
        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            // Groq retired llama-3.3-70b-versatile on 2026-08-16 (free/developer tier).
            // https://console.groq.com/docs/deprecations
            'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        ],
        'ollama' => [
            'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3.2'),
        ],
        'grok' => [
            'api_key' => env('XAI_API_KEY'),
            'model' => env('GROK_MODEL', 'grok-4-0709'),
        ],
        'image' => [
            // Fallback provider when a user has not chosen a default in AI Settings.
            'driver' => env('AI_IMAGE_DRIVER', 'stub'),
            'openai' => [
                'api_key' => env('OPENAI_API_KEY'),
                'model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
                'size' => env('OPENAI_IMAGE_SIZE', '1024x1024'),
            ],
            'flux' => [
                'api_key' => env('BFL_API_KEY'),
                'model' => env('FLUX_IMAGE_MODEL', 'flux-kontext-pro'),
            ],
            'gemini' => [
                'api_key' => env('GEMINI_API_KEY'),
                'model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
            ],
            'grok' => [
                'api_key' => env('XAI_API_KEY'),
                'model' => env('GROK_IMAGE_MODEL', 'grok-imagine-image-2.0'),
            ],
        ],
    ],

];
