<?php

namespace App\Services;

use App\Models\Ad;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ListingPaymentService
{
    /** Publish exactly once, after Stripe has reported a successful intent. */
    public function publishPaidListing(string $intentId, int $amount, string $currency): Ad
    {
        return DB::transaction(function () use ($intentId, $amount, $currency) {
            $ad = Ad::query()->where('stripe_payment_intent_id', $intentId)->lockForUpdate()->firstOrFail();
            if ($ad->payment_status === 'paid') {
                return $ad;
            }

            if ($ad->status !== 'pending' || $amount !== (int) round(((float) $ad->listing_fee) * 100)
                || strtolower($currency) !== strtolower(config('services.stripe.currency', 'gbp'))) {
                throw new RuntimeException('The Stripe payment does not match this listing.');
            }

            $publishedAt = now();
            $ad->update([
                'status' => 'published', 'payment_status' => 'paid', 'published_at' => $publishedAt,
                'expires_at' => $publishedAt->copy()->addDays((int) setting('post_duration', '30')),
            ]);

            return $ad->fresh();
        });
    }
}
