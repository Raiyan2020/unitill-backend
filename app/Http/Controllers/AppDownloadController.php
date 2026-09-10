<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Public, no-auth landing page for {app.url}/download — shared as "get the app"
 * link (App Store QR posters, WhatsApp, etc). Client-side script picks the right
 * store from the device, so this also works fine for bots/crawlers server-side.
 */
class AppDownloadController extends Controller
{
    public function show(Request $request)
    {
        return view('public.download', [
            'appName' => setting('app_name', 'UniTill'),
            'iosUrl' => config('app_stores.ios_url'),
            'androidUrl' => config('app_stores.android_url'),
            'androidPackage' => config('app_stores.android_package'),
        ]);
    }
}
