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

    'pst_digital_url' => env('PST_DIGITAL_URL', 'https://pst.bps.go.id/'),

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'admin_emails' => array_values(array_filter(array_map(
            static fn(string $email): string => strtolower(trim($email)),
            explode(',', (string) env('GOOGLE_ADMIN_EMAILS', '')),
        ))),
    ],

    'elasticsearch' => [
        'url' => env('ELASTICSEARCH_URL'),
        'api_key' => env('ELASTICSEARCH_API_KEY'),
        'index' => env('ELASTICSEARCH_GUEST_INDEX', 'bps-guest-entries'),
    ],

];
