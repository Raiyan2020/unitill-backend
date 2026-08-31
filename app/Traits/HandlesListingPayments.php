<?php

namespace App\Traits;

use App\Models\Ad;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\User;
use App\Services\ListingPaymentService;
use App\Services\StripeService;
use App\Services\CouponRedemptionService;
use Illuminate\Support\Facades\DB;

trait HandlesListingPayments
{
    protected function startPublication(Ad $ad, ?string $couponCode = null, ?float $feeOverride = null, string $type = 'listing'): array
    {
        // Where to return the ad if Stripe setup fails: a posted ad goes back to
        // "pending", a draft being published goes back to "draft". Hardcoding one
        // of them would turn the other into something it never was.
        $originalStatus = $ad->status;

        // A previous attempt's intent may have already died (card declined,
        // user backed out, etc.) without us hearing about it — the webhook
        // only fires on success. If so, release its coupon redemption before
        // the transaction below runs, so a retry can apply a different code
        // (or the same one again) instead of being stuck on the first result.
        if ($ad->stripe_payment_intent_id && $ad->status !== 'published') {
            $ad = $this->releaseDeadPaymentAttempt($ad);
        }

        $result = DB::transaction(function () use ($ad, $couponCode, $feeOverride) {
            $lockedAd = Ad::query()->lockForUpdate()->findOrFail($ad->id);
            // Serialize a user's quota checks so two concurrent publish calls
            // cannot both consume the final free listing.
            User::query()->lockForUpdate()->findOrFail($lockedAd->user_id);
            if ($lockedAd->status === 'published') {
                return ['published' => true, 'ad' => $lockedAd];
            }

            $limit = max(0, (int) setting('free_ads_per_user', '0'));
            $used = Ad::withTrashed()->where('user_id', $lockedAd->user_id)->where('is_free_listing', true)->count();
            $fee = $feeOverride ?? ($lockedAd->mainCategory?->resolvedListingFee() ?? (float) setting('post_price', '0.99'));
            if ($used < $limit || $fee <= 0) {
                $publishedAt = now();
                $lockedAd->update([
                    'status' => 'published', 'published_at' => $publishedAt,
                    'expires_at' => $publishedAt->copy()->addDays((int) setting('post_duration', '30')),
                    'listing_fee' => 0, 'payment_status' => $used < $limit ? 'free' : 'waived', 'is_free_listing' => $used < $limit,
                ]);
                return ['published' => true, 'ad' => $lockedAd->fresh(), 'fee' => $fee];
            }

            // A Stripe intent locks the amount. Do not apply a second coupon
            // if the client retries the publish request. But the earlier
            // redemption (if any) already discounted listing_fee above, so
            // surface it again here — otherwise a retry after a cancelled/
            // failed payment silently drops the "coupon applied" state and
            // the app hides the discount code UI as if none was ever used.
            if ($lockedAd->stripe_payment_intent_id) {
                $redemption = CouponRedemption::with('coupon')
                    ->where('ad_id', $lockedAd->id)
                    ->latest('id')
                    ->first();

                $coupon = $redemption ? [
                    'applied' => true,
                    'code' => $redemption->coupon->code,
                    'discount_amount' => (float) $redemption->discount_amount,
                ] : null;

                return ['published' => false, 'ad' => $lockedAd->fresh(), 'coupon' => $coupon];
            }

            $coupon = null;
            $couponCode = trim((string) $couponCode);
            if ($couponCode !== '') {
                $redemption = app(CouponRedemptionService::class)->redeem(
                    $couponCode,
                    $lockedAd->user,
                    $fee,
                    $lockedAd->id
                );
                if (isset($redemption['error'])) {
                    return ['published' => false, 'coupon_error' => $redemption['error']];
                }
                $fee = (float) $redemption['final_amount'];
                $coupon = [
                    'applied' => true,
                    'code' => $redemption['code'],
                    'discount_amount' => $redemption['discount_amount'],
                ];
            }

            if ($fee <= 0) {
                $publishedAt = now();
                $lockedAd->update([
                    'status' => 'published', 'published_at' => $publishedAt,
                    'expires_at' => $publishedAt->copy()->addDays((int) setting('post_duration', '30')),
                    'listing_fee' => 0, 'payment_status' => 'coupon',
                ]);
                return ['published' => true, 'ad' => $lockedAd->fresh(), 'coupon' => $coupon, 'fee' => $fee];
            }

            $lockedAd->update(['status' => 'pending', 'listing_fee' => $fee, 'payment_status' => 'requires_payment']);
            return ['published' => false, 'ad' => $lockedAd->fresh(), 'coupon' => $coupon, 'fee' => $fee];
        });

        if (isset($result['coupon_error'])) {
            return ['published' => false, 'coupon_error' => $result['coupon_error']];
        }

        if ($result['published']) {
            if (isset($result['fee'])) {
                $this->recordPayment($result['ad'], $type, (float) $result['fee'], $result['ad']->payment_status, now());
            }

            return app(ListingPaymentService::class)->publicationState($result['ad']) + [
                'coupon' => $result['coupon'] ?? null,
                'free_ads_remaining' => $this->freeAdsRemaining($ad->user_id),
            ];
        }

        $paymentAd = $result['ad'];
        try {
            $intent = $paymentAd->stripe_payment_intent_id
                ? app(StripeService::class)->paymentIntent($paymentAd->stripe_payment_intent_id)
                : app(StripeService::class)->createListingPaymentIntent($paymentAd, $type);
            if (! $paymentAd->stripe_payment_intent_id) {
                $paymentAd->update(['stripe_payment_intent_id' => $intent['id']]);
            }
        } catch (\Throwable $exception) {
            $paymentAd->update(['status' => $originalStatus, 'payment_status' => 'payment_setup_failed']);
            throw $exception;
        }

        $paymentAd->stripe_payment_intent_id = $intent['id'];

        if (isset($result['fee'])) {
            $this->recordPayment($paymentAd, $type, (float) $result['fee'], 'requires_payment', null, $intent['id']);
        }

        return app(ListingPaymentService::class)->publicationState($paymentAd, $intent) + [
            'coupon' => $result['coupon'] ?? null,
        ];
    }

    /**
     * One row per payment attempt, upserted by Stripe intent when there is
     * one — audit trail for admin/financial reporting, additive only. The ad
     * row stays the source of truth for current-state reads.
     */
    protected function recordPayment(
        Ad $ad,
        string $type,
        float $amount,
        string $status,
        ?\Illuminate\Support\Carbon $paidAt = null,
        ?string $stripePaymentIntentId = null
    ): void {
        Payment::updateOrCreate(
            $stripePaymentIntentId
                ? ['stripe_payment_intent_id' => $stripePaymentIntentId]
                : ['ad_id' => $ad->id, 'type' => $type, 'stripe_payment_intent_id' => null, 'status' => $status],
            [
                'ad_id' => $ad->id,
                'user_id' => $ad->user_id,
                'type' => $type,
                'amount' => $amount,
                'currency' => strtoupper(config('services.stripe.currency', 'GBP')),
                'status' => $status,
                'paid_at' => $paidAt,
            ]
        );
    }

    /**
     * Checks Stripe for a pending intent's real status and, only if it's
     * genuinely dead — or abandoned but still cancelable — clears it and
     * frees up any coupon redemption tied to this ad so a retry starts
     * clean. "processing" is deliberately excluded: Stripe itself refuses
     * to cancel money already in flight, and neither should we release the
     * coupon behind it.
     */
    protected function releaseDeadPaymentAttempt(Ad $ad): Ad
    {
        try {
            $intent = app(StripeService::class)->paymentIntent($ad->stripe_payment_intent_id);
        } catch (\Throwable) {
            return $ad;
        }

        $status = $intent['status'] ?? null;
        if (! in_array($status, ['requires_payment_method', 'requires_action', 'canceled'], true)) {
            return $ad;
        }

        if ($status !== 'canceled') {
            try {
                app(StripeService::class)->cancel($ad->stripe_payment_intent_id);
            } catch (\Throwable) {
                // Already progressed past a cancelable state between our check and
                // this call (e.g. the user completed 3DS in that instant) — leave
                // the coupon and intent alone rather than release under a live charge.
                return $ad;
            }
        }

        app(CouponRedemptionService::class)->release($ad->id);
        $ad->update(['stripe_payment_intent_id' => null]);

        return $ad->fresh();
    }

    protected function freeAdsRemaining(int $userId): int
    {
        $used = Ad::withTrashed()->where('user_id', $userId)->where('is_free_listing', true)->count();
        return max(0, (int) setting('free_ads_per_user', '0') - $used);
    }

    protected function startExtension(Ad $ad, ?string $couponCode): array
    {
        $ad->update([
            'paused_at' => null,
            'inactive_reason' => null,
            'listing_fee' => null,
            'payment_status' => 'not_required',
            'stripe_payment_intent_id' => null,
        ]);

        return $this->startPublication(
            $ad->fresh(),
            $couponCode,
            (float) setting('listing_extension_price', '0.99'),
            'extension'
        );
    }

    protected function hasUnexpiredPaidPeriod(Ad $ad): bool
    {
        $settled = in_array($ad->payment_status, ['paid', 'free', 'waived', 'coupon'], true);

        return $settled
            && $ad->expires_at !== null
            && $ad->expires_at->isFuture();
    }
}
