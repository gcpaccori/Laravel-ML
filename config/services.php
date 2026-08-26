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

    'aquaculture_backend' => [
        'url' => env('AQUACULTURE_BACKEND_URL', 'http://aquaculture_backend:8000/api/v1'),
        'connect_timeout' => (int) env('AQUACULTURE_BACKEND_CONNECT_TIMEOUT', 2),
        'timeout' => (int) env('AQUACULTURE_BACKEND_TIMEOUT', 18),
        'cache_seconds' => (int) env('MODEL_ALERTS_CACHE_SECONDS', 60),
        'stale_seconds' => (int) env('MODEL_ALERTS_STALE_SECONDS', 1800),
    ],

];
