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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'fonnte' => [
        'endpoint' => env('FONNTE_API_ENDPOINT', env('WHATSAPP_API_ENDPOINT', 'https://api.fonnte.com/send')),
        'token' => env('FONNTE_TOKEN', env('WHATSAPP_API_KEY')),
        'country_code' => env('FONNTE_COUNTRY_CODE', '62'),
        'default_target' => env('FONNTE_DEFAULT_TARGET', env('DEFAULT_NOTIFICATION_PHONE')),
        'timeout' => env('FONNTE_TIMEOUT', 10),
        'retry_times' => env('FONNTE_RETRY_TIMES', 2),
        'retry_sleep' => env('FONNTE_RETRY_SLEEP', 500),
    ],

];
