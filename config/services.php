<?php

// Same resolution as config/fcm.php: FIREBASE_CREDENTIALS may be absolute or
// relative to the project root (the web server's CWD is public/).
$firebaseCredentials = env('FIREBASE_CREDENTIALS', 'storage/app/firebase/firebase_credentials.json');

if (is_string($firebaseCredentials) && $firebaseCredentials !== '' && ! preg_match('#^(/|[A-Za-z]:[\\\\/])#', $firebaseCredentials)) {
    $firebaseCredentials = base_path($firebaseCredentials);
}

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

    // Credentials come from the environment only. Two live tokens were
    // previously inlined here as env() defaults; they are in the git history of
    // both repositories and must be treated as compromised and rotated, not
    // merely deleted.
    'myfatoorah' => [
        'base_url' => env('MY_FATOORAH_BASE_URL', 'https://apitest.myfatoorah.com/v2'),
        'token' => env('MY_FATOORAH_TOKEN'),
    ],
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'gbp'),
    ],
    'firebase' => [
        'credentials' => $firebaseCredentials,
    ],
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'instance' => env('WHATSAPP_INSTANCE'),
    ],
    'wawp' => [
        'instance_id' => env('WAWP_INSTANCE'),
        'access_token' => env('WAWP_API_TOKEN'),
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

    'google_translate' => [
        // Service-account JSON key (Cloud Translation API v3), same pattern as
        // FIREBASE_CREDENTIALS below — a project-relative path.
        'credentials_path' => env('GOOGLE_TRANSLATE_CREDENTIALS'),
    ],

];
