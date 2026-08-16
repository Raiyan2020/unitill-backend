<?php

// Allow FIREBASE_CREDENTIALS to be either an absolute path or one relative to
// the project root; the web server's CWD is public/, so relative paths must be
// resolved explicitly.
$firebaseCredentials = env('FIREBASE_CREDENTIALS', 'storage/app/firebase/firebase_credentials.json');

if (is_string($firebaseCredentials) && $firebaseCredentials !== '' && ! preg_match('#^(/|[A-Za-z]:[\\\\/])#', $firebaseCredentials)) {
    $firebaseCredentials = base_path($firebaseCredentials);
}

return [

    'credentials' => $firebaseCredentials,

    /*
    |--------------------------------------------------------------------------
    | FCM topic for broadcast notifications to all app users.
    |--------------------------------------------------------------------------
    */
    'all_users_topic' => env('FCM_ALL_USERS_TOPIC', 'unitill_all'),

    /*
    |--------------------------------------------------------------------------
    | FCM topic for marketing broadcasts — only users who opted in via
    | notify_marketing are subscribed to this topic.
    |--------------------------------------------------------------------------
    */
    'marketing_topic' => env('FCM_MARKETING_TOPIC', 'unitill_marketing'),

];
