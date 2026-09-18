<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    'ransomware_live' => [
        // v2 (no key) is for personal use only; set the PRO base URL and key for ministry use.
        'base_url' => env('RANSOMWARE_LIVE_BASE_URL', 'https://api.ransomware.live/v2'),
        'key' => env('RANSOMWARE_LIVE_KEY'),
        'key_header' => env('RANSOMWARE_LIVE_KEY_HEADER', 'X-API-KEY'),
    ],

    'nvd' => [
        'key' => env('NVD_API_KEY'),
    ],

    'abusech' => [
        'key' => env('ABUSECH_AUTH_KEY'),
    ],

    'otx' => [
        'key' => env('OTX_API_KEY'),
    ],

    'youtube' => [
        'key' => env('YOUTUBE_API_KEY'),
    ],

    'bluesky' => [
        'identifier' => env('BLUESKY_IDENTIFIER'),
        'app_password' => env('BLUESKY_APP_PASSWORD'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    ],

];
