<?php

use App\Http\Controllers\PublicAccountDeletionController;
use App\Http\Controllers\PublicAdController;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

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
    } catch (Throwable $e) {
        return response()->json([
            'ok' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

// Public landing page for a shared ad link. The mobile share sheet builds
// {app.url}/ads/{public_id}, so this route is the recipient's entry point —
// it renders the listing and carries the Open Graph tags for link previews.
Route::get('/ads/{publicId}', [PublicAdController::class, 'show'])
    ->where('publicId', '[A-Za-z0-9_-]+')
    ->name('ads.public');

Route::get('/delete-account', [PublicAccountDeletionController::class, 'create'])
    ->name('delete-account.create');
Route::post('/delete-account', [PublicAccountDeletionController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('delete-account.store');

// React admin dashboard. Every /admin/* URL returns the SPA shell and React Router
// (mounted with basename="/admin") picks the page, so deep links and refreshes work.
// Assets stay under /dist and are served straight off disk, not through this route.
Route::get('/admin/{any?}', function () {
    return response()->file(public_path('dist/index.html'));
})->where('any', '.*')->name('admin.spa');

// Diagnostic-only landing pages for the Apple Pay Checkout test (see
// PaymentDiagnosticsController). Confirmed working; disabled, not deleted,
// in case it's needed again.
//Route::get('/payments/test-success', fn () => response('Payment successful — Apple Pay works on this Stripe account/domain. You can close this window.'));
//Route::get('/payments/test-cancel', fn () => response('Payment cancelled. You can close this window.'));

// Diagnostic-only: open inside the app's WebView (and in Chrome / Safari for
// comparison) to see whether that surface can render Google Pay / Apple Pay at
// all. Stripe Checkout gates wallets on the same browser signals this page
// reports (PaymentRequest API, secure context, Google Pay readiness).
Route::get('/payments/webview-check', fn () => view('payments.webview-check'))->name('payments.webview-check');

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
