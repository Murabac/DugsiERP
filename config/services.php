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

    'sms' => [
        // auto | textbee | telesom — auto prefers TextBee when configured
        'driver' => env('SMS_DRIVER', 'auto'),
    ],

    'textbee' => [
        'api_base' => env('TEXTBEE_API_BASE', 'https://api.textbee.dev/api/v1'),
        'api_key' => env('TEXTBEE_API_KEY'),
        'device_id' => env('TEXTBEE_DEVICE_ID'),
        // Set false on Windows PHP without a CA bundle (local testing only).
        'verify_ssl' => filter_var(env('TEXTBEE_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
    ],

    'telesom' => [
        'api_url' => env('TELESOM_SMS_API_URL', 'https://sms.mytelesom.com/index.php/Gway/sendsms/'),
        'username' => env('TELESOM_SMS_USERNAME'),
        'password' => env('TELESOM_SMS_PASSWORD'),
        'sender' => env('TELESOM_SMS_SENDER'),
        'secret' => env('TELESOM_SMS_SECRET'),
    ],

];
