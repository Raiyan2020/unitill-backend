<?php

namespace App\Services;

use App\Models\Ad;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeService
{
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
}
