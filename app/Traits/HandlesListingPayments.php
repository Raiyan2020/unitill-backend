<?php

namespace App\Traits;

use App\Models\Ad;
use App\Models\User;
use App\Services\StripeService;
use App\Services\CouponRedemptionService;
use Illuminate\Support\Facades\DB;

trait HandlesListingPayments
{
    protected function startPublication(Ad $ad, ?string $couponCode = null): array
    {
        // Where to return the ad if Stripe setup fails: a posted ad goes back to
        // "pending", a draft being published goes back to "draft". Hardcoding one
        // of them would turn the other into something it never was.
        $originalStatus = $ad->status;

        $result = DB::transaction(function () use ($ad, $couponCode) {
            $lockedAd = Ad::query()->lockForUpdate()->findOrFail($ad->id);
            // Serialize a user's quota checks so two concurrent publish calls
            // cannot both consume the final free listing.
            User::query()->lockForUpdate()->findOrFail($lockedAd->user_id);
            if ($lockedAd->status === 'published') {
                return ['published' => true, 'ad' => $lockedAd];
            }

            $limit = max(0, (int) setting('free_ads_per_user', '0'));
            $used = Ad::withTrashed()->where('user_id', $lockedAd->user_id)->where('is_free_listing', true)->count();
            $fee = (float) setting('post_price', '0');
            if ($used < $limit || $fee <= 0) {
                $publishedAt = now();
                $lockedAd->update([
                    'status' => 'published', 'published_at' => $publishedAt,
                    'expires_at' => $publishedAt->copy()->addDays((int) setting('post_duration', '30')),
                    'listing_fee' => 0, 'payment_status' => $used < $limit ? 'free' : 'waived', 'is_free_listing' => $used < $limit,
                ]);
                return ['published' => true, 'ad' => $lockedAd->fresh()];
            }

            // A Stripe intent locks the amount. Do not apply a second coupon
            // if the client retries the publish request.
            if ($lockedAd->stripe_payment_intent_id) {
                return ['published' => false, 'ad' => $lockedAd->fresh()];
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
                return ['published' => true, 'ad' => $lockedAd->fresh(), 'coupon' => $coupon];
            }

            $lockedAd->update(['status' => 'pending', 'listing_fee' => $fee, 'payment_status' => 'requires_payment']);
            return ['published' => false, 'ad' => $lockedAd->fresh(), 'coupon' => $coupon];
        });

        if (isset($result['coupon_error'])) {
            return ['published' => false, 'coupon_error' => $result['coupon_error']];
        }

        if ($result['published']) {
            return ['published' => true, 'payment_required' => false, 'coupon' => $result['coupon'] ?? null, 'free_ads_remaining' => $this->freeAdsRemaining($ad->user_id)];
        }

        $paymentAd = $result['ad'];
        try {
            $intent = $paymentAd->stripe_payment_intent_id
                ? app(StripeService::class)->paymentIntent($paymentAd->stripe_payment_intent_id)
                : app(StripeService::class)->createListingPaymentIntent($paymentAd);
            if (! $paymentAd->stripe_payment_intent_id) {
                $paymentAd->update(['stripe_payment_intent_id' => $intent['id']]);
            }
        } catch (\Throwable $exception) {
            $paymentAd->update(['status' => $originalStatus, 'payment_status' => 'payment_setup_failed']);
            throw $exception;
        }

        return ['published' => false, 'payment_required' => true, 'amount' => (float) $paymentAd->listing_fee,
            'currency' => strtoupper(config('services.stripe.currency', 'GBP')),
            'payment_intent_id' => $intent['id'], 'client_secret' => $intent['client_secret'], 'coupon' => $result['coupon'] ?? null];
    }

    protected function freeAdsRemaining(int $userId): int
    {
        $used = Ad::withTrashed()->where('user_id', $userId)->where('is_free_listing', true)->count();
        return max(0, (int) setting('free_ads_per_user', '0') - $used);
    }
}
