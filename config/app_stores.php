<?php

// Store listings for the /download smart-redirect page (resources/views/public/download.blade.php)
// and the Android package used to try to open the installed app before falling back to the store.
return [
    'ios_url' => env('APP_STORE_URL', 'https://apps.apple.com/eg/app/unitill/id6786251637'),
    'android_url' => env('PLAY_STORE_URL', 'https://play.google.com/store/apps/details?id=uk.unitill.app'),
    'android_package' => env('ANDROID_PACKAGE_NAME', 'uk.unitill.app'),
];
