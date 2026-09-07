<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\StripeService;

/**
 * Diagnostic-only endpoint to isolate whether Apple Pay not appearing in the
 * native app is a Stripe/account/backend issue or a Flutter merchantIdentifier
 * config issue: it opens a hosted Stripe Checkout page (in a webview) instead
 * of the native PaymentSheet, which decides Apple Pay eligibility from the
 * account's verified web payment method domains rather than the app's
 * merchantIdentifier. Not linked to any Ad or real listing payment — remove
 * once the Apple Pay issue is resolved.
 */
class PaymentDiagnosticsController extends Controller
{
    public function applePayCheckout(StripeService $stripe)
    {
        $session = $stripe->createDiagnosticCheckoutSession(
            config('app.url').'/payments/test-success',
            config('app.url').'/payments/test-cancel'
        );

        return sendResponse(['url' => $session['url'], 'id' => $session['id']]);
    }
}
