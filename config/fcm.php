<?php

return [

    'credentials' => env(
        'FIREBASE_CREDENTIALS',
        storage_path('app/firebase/firebase_credentials.json')
    ),

    /*
    |--------------------------------------------------------------------------
    | FCM topic for broadcast notifications to all app users.
    |--------------------------------------------------------------------------
    */
    'all_users_topic' => env('FCM_ALL_USERS_TOPIC', 'unitill_all'),

];
