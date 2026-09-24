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

    'whatsapp_otp' => [
        'ttl_minutes' => env('WHATSAPP_OTP_TTL_MINUTES', 5),
    ],

    'whatsapp_gateway' => [
        'url' => env('WAG_URL', 'https://waghub.mekayastudio.com'),
        'token' => env('WAG_TOKEN'),
        'connect_timeout' => (float) env('WA_CONNECT_TIMEOUT', 5),
        'timeout' => (float) env('WA_API_TIMEOUT', 15),
        'default_target' => env('WHATSAPP_DEFAULT_TARGET'),
        'country_code' => '62',
        'min_digits' => 10,
        'max_digits' => 15,
    ],

];
