<?php

use App\Mail\OtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/test', function () {
    return view('test');
});

// مسار تجربة البريد الإلكتروني
Route::get('email', function () {
    try {
        Mail::to('aalshy00@gmail.com')->send(new OtpMail('1234'));
        return response()->json(['ok' => true, 'message' => 'sent']);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

// Fallback آمن للـ API والويب (يعيد 404 حقيقي بدلاً من إرجاع HTML بالخطأ)
Route::fallback(function (Request $request) {
    if ($request->is('api/*')) {
        return sendError(
            "The route {$request->path()} could not be found.",
            [],
            404
        );
    }

    abort(404, 'Page not found.');
});