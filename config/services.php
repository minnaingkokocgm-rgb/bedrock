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

    'bedrock' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'token' => env('AWS_SESSION_TOKEN'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'model_id' => env('BEDROCK_MODEL_ID', 'amazon.nova-lite-v1:0'),
        // Output generation limit for verdict JSON (plenty for reason text; model max is 65_534).
        'max_tokens' => (int) env('BEDROCK_MAX_TOKENS', 4096),
        'temperature' => (float) env('BEDROCK_TEMPERATURE', 0.5),
        // Soft cap on extracted text chars sent in the prompt (leave headroom for video/S3).
        'content_char_limit' => (int) env('BEDROCK_CONTENT_CHAR_LIMIT', 500_000),
        // Seconds to wait for Converse (large S3 videos can take several minutes).
        'http_timeout' => (int) env('BEDROCK_HTTP_TIMEOUT', 600),
        'http_connect_timeout' => (int) env('BEDROCK_HTTP_CONNECT_TIMEOUT', 30),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
