<?php

use App\Models\Ad;
use App\Models\Payment;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * One-time backfill: a payments row for every ad that already went
     * through the fee/payment flow, from its current ads.* columns. Only the
     * current state survives — earlier attempts overwritten by an extension
     * before this table existed are not recoverable.
     */
    public function up(): void
    {
        $currency = strtoupper(config('services.stripe.currency', 'GBP'));

        Ad::withTrashed()
            ->where('payment_status', '!=', 'not_required')
            ->whereNotNull('listing_fee')
            ->chunkById(200, function ($ads) use ($currency) {
                foreach ($ads as $ad) {
                    Payment::firstOrCreate(
                        $ad->stripe_payment_intent_id
                            ? ['stripe_payment_intent_id' => $ad->stripe_payment_intent_id]
                            : ['ad_id' => $ad->id, 'stripe_payment_intent_id' => null],
                        [
                            'ad_id' => $ad->id,
                            'user_id' => $ad->user_id,
                            'type' => 'listing',
                            'amount' => $ad->listing_fee,
                            'currency' => $currency,
                            'status' => $ad->payment_status,
                            'paid_at' => in_array($ad->payment_status, ['paid', 'free', 'waived', 'coupon'], true)
                                ? ($ad->published_at ?? $ad->updated_at)
                                : null,
                            'refund_status' => $ad->refund_status,
                            'refund_reference' => $ad->refund_reference,
                            'refund_reason' => $ad->refund_reason,
                            'refund_requested_at' => $ad->refund_requested_at,
                            'refund_request_reason' => $ad->refund_request_reason,
                            'refunded_at' => $ad->refunded_at,
                            'refund_declined_at' => $ad->refund_declined_at,
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        // Backfilled rows are indistinguishable from organically created ones
        // once new payments exist alongside them — not reversible.
    }
};
