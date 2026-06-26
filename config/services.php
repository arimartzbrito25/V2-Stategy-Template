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

    'wompi' => [
        'api_key'  => env('WOMPI_API_KEY', ''),
        'endpoint' => env('WOMPI_ENDPOINT', 'https://sandbox.wompi.co/v1'),
    ],

    'n1co' => [
        'api_key'  => env('N1CO_API_KEY', ''),
        'endpoint' => env('N1CO_ENDPOINT', 'https://sandbox.n1co.com/v2'),
    ],

    'bac' => [
        'api_key'  => env('BAC_API_KEY', ''),
        'endpoint' => env('BAC_ENDPOINT', 'https://sandbox.bac.com/v1'),
    ],

    'payment' => [
        'default' => env('PAYMENT_PROVIDER', 'wompi'),
    ],

];
