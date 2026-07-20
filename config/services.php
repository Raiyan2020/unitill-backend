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

    'myfatoorah' => [
        'base_url' => env('MY_FATOORAH_BASE_URL','https://apitest.myfatoorah.com/v2'),
        'token' => env('MY_FATOORAH_TOKEN','SK_KWT_vVZlnnAqu8jRByOWaRPNId4ShzEDNt256dvnjebuyzo52dXjAfRx2ixW5umjWSUx'),//test
//        'token' => env('MY_FATOORAH_TOKEN','SK_KWT_O26a1e9k8cXBJfKn1yi78LJxYNVrfCD9E5mX7tXSxDbw1ZKz3izUcIirTW8Ri6tq'),
    ],
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'gbp'),
    ],
    'firebase' => [
        'credentials' => storage_path('firebase/firebase_credentials.json'),
    ],
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'instance' => env('WHATSAPP_INSTANCE'),
    ],
    'wawp' => [
        'instance_id' => env('WAWP_INSTANCE', '11199681C9D2'),
        'access_token' => env('WAWP_API_TOKEN','rhS3eDMYV7goCg'),
    ],

    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from'  => env('TWILIO_FROM'),
        'verify_sid' => env('TWILIO_VERIFY_SID'),

    ],
    'vehicle_api' => [
        'key' => env('VEHICLE_API_KEY'),
    ],

];
