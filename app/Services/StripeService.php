<?php

namespace App\Services;

use App\Models\Ad;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeService
{
    /**
     * Stripe's documented per-currency minimum chargeable amount, in that
     * currency's smallest unit (docs.stripe.com/currencies#minimum-and-maximum-charge-amounts).
     * A coupon can discount a fee down to a few pence that Stripe will
     * outright refuse to charge — this lets callers catch that before ever
     * hitting the API. Unlisted currencies fall back to the common 50-unit
     * minimum most currencies share.
     */
    private const MIN_CHARGE_MINOR_UNITS = [
        'usd' => 50, 'aud' => 50, 'cad' => 50, 'chf' => 50, 'eur' => 50, 'gbp' => 30, 'nzd' => 50,
        'jpy' => 50, 'hkd' => 400, 'mxn' => 1000, 'sek' => 300, 'sgd' => 50, 'dkk' => 250, 'nok' => 300,
    ];

    public function minimumChargeAmount(string $currency): float
    {
        $minorUnits = self::MIN_CHARGE_MINOR_UNITS[strtolower($currency)] ?? 50;

        return $minorUnits / 100;
    }

    public function createListingPaymentIntent(Ad $ad, string $type = 'listing'): array
    {
        $secret = config('services.stripe.secret');
        if (! $secret) {
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET in the environment.');
        }

        $amount = (int) round(((float) $ad->listing_fee) * 100);
        $currency = strtolower(config('services.stripe.currency', 'gbp'));

        // Keying on the ad id alone collided an extension payment with the
        // ad's original listing payment (same ad, different amount) —
        // Stripe rejects a reused key whose parameters changed. Amount is
        // included so a genuine same-request retry still dedupes safely.
        $idempotencyKey = "listing-payment-{$ad->id}-{$type}-{$amount}";

        $response = Http::asForm()
            ->withBasicAuth($secret, '')
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => $amount,
                'currency' => $currency,
                'description' => "UniTill listing #{$ad->public_id}",
                'metadata' => ['ad_id' => $ad->id, 'user_id' => $ad->user_id],
                'automatic_payment_methods' => ['enabled' => 'true'],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: 'Unable to create the Stripe payment.');
        }

        return $response->json();
    }

    public function refund(string $paymentIntentId, ?int $amount = null): array
    {
        $secret = config('services.stripe.secret');
        if (! $secret) {
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET in the environment.');
        }

        $payload = ['payment_intent' => $paymentIntentId];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        $response = Http::asForm()
            ->withBasicAuth($secret, '')
            ->withHeaders(['Idempotency-Key' => "refund-{$paymentIntentId}"])
            ->post('https://api.stripe.com/v1/refunds', $payload);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: 'Unable to refund the Stripe payment.');
        }

        return $response->json();
    }

    public function paymentIntent(string $id): array
    {
        $secret = config('services.stripe.secret');
        if (! $secret) {
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET in the environment.');
        }

        $response = Http::withBasicAuth($secret, '')->get("https://api.stripe.com/v1/payment_intents/{$id}");
        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: 'Unable to verify the Stripe payment.');
        }

        return $response->json();
    }

    /**
     * Only ever call this against an intent whose attempt has already
     * concluded (requires_payment_method after a decline, or already
     * canceled) — Stripe itself refuses to cancel one that's processing or
     * succeeded, which is exactly the money-in-flight case that must stay
     * untouched.
     */
    public function cancel(string $id): array
    {
        $secret = config('services.stripe.secret');
        if (! $secret) {
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET in the environment.');
        }

        $response = Http::asForm()
            ->withBasicAuth($secret, '')
            ->post("https://api.stripe.com/v1/payment_intents/{$id}/cancel");

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: 'Unable to cancel the Stripe payment.');
        }

        return $response->json();
    }
}
