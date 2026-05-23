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

    'mikrotik' => [
        'host' => env('MIKROTIK_HOST'),
        'port' => (int) env('MIKROTIK_PORT', 443),
        'user' => env('MIKROTIK_USER'),
        'pass' => env('MIKROTIK_PASS'),
        'ssl' => filter_var(env('MIKROTIK_SSL', true), FILTER_VALIDATE_BOOL),
        'verify_ssl' => filter_var(env('MIKROTIK_VERIFY_SSL', false), FILTER_VALIDATE_BOOL),
        'timeout' => (int) env('MIKROTIK_TIMEOUT', 10),
        'api_port' => (int) env('MIKROTIK_API_PORT', 8728),
        'api_ssl' => filter_var(env('MIKROTIK_API_SSL', false), FILTER_VALIDATE_BOOL),
        'api_timeout' => (int) env('MIKROTIK_API_TIMEOUT', 10),
        'sync_enabled' => filter_var(env('MIKROTIK_SYNC_ENABLED', true), FILTER_VALIDATE_BOOL),
        'sync_mode' => env('MIKROTIK_SYNC_MODE', 'hotspot'),
        'hotspot_profile' => env('MIKROTIK_HOTSPOT_PROFILE'),
        'pppoe_profile' => env('MIKROTIK_PPPOE_PROFILE'),
    ],

    'whatsapp' => [
        'enabled' => filter_var(env('WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'url' => env('WHATSAPP_URL'),
        'token' => env('WHATSAPP_TOKEN'),
        'owner_number' => env('OWNER_WA_NUMBER'),
    ],

];
