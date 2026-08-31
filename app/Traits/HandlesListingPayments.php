<?php

namespace App\Traits;

use App\Models\Ad;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\User;
use App\Services\CouponRedemptionService;
use App\Services\ListingPaymentService;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;

trait HandlesListingPayments
{
    /**
     * $couponCodeProvided distinguishes "the client sent no coupon_code at
     * all" (a plain retry — just re-check/continue the pending attempt) from
     * "the client sent coupon_code, even as an empty string" (an explicit
     * change or removal). Both look identical after trim() otherwise, so
     * callers must pass $request->has('coupon_code') here.
     */
    protected function startPublication(Ad $ad, ?string $couponCode = null, ?float $feeOverride = null, string $type = 'listing', bool $couponCodeProvided = false): array
    {
        // Where to return the ad if Stripe setup fails: a posted ad goes back to
        // "pending", a draft being published goes back to "draft". Hardcoding one
        // of them would turn the other into something it never was.
        $originalStatus = $ad->status;

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

            // An existing intent is handed off to resolveRetryRequest() below,
            // outside this transaction — it needs to talk to Stripe and may
            // recreate the intent, neither of which belongs inside a DB lock.
            if ($lockedAd->stripe_payment_intent_id) {
                return ['published' => false, 'ad' => $lockedAd->fresh(), 'retry' => true];
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

                // Stripe refuses to charge below its per-currency minimum. A
                // coupon that discounts down into that gap can't be charged at
                // all, so treat it as fully covered rather than erroring out.
                $minCharge = app(StripeService::class)->minimumChargeAmount(config('services.stripe.currency', 'gbp'));
                if ($fee > 0 && $fee < $minCharge) {
                    $fee = 0;
                }
            }

            if ($fee <= 0) {
                $publishedAt = now();
                $lockedAd->update([
                    'status' => 'published', 'published_at' => $publishedAt,
                    'expires_at' => $publishedAt->copy()->addDays((int) setting('post_duration', '30')),
                    'listing_fee' => 0, 'payment_status' => 'paid',
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

        if (! empty($result['retry'])) {
            return $this->resolveRetryRequest($paymentAd, $couponCode, $couponCodeProvided, $originalStatus);
        }

        try {
            $intent = app(StripeService::class)->createListingPaymentIntent($paymentAd, $type);
            $paymentAd->update(['stripe_payment_intent_id' => $intent['id']]);
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
     * Handles every request that lands on a listing which already has a
     * pending Stripe intent — a plain retry, a status check, or an explicit
     * coupon change:
     *
     *  1. Always returns the currently-applied coupon (if any), so the
     *     frontend's discount UI never has to infer it silently vanished.
     *  2. Re-validates that coupon against the database on every call; if it
     *     has since expired or been deactivated, detaches it, falls back to
     *     the original price, and reports a warning the frontend can show.
     *  3. If the caller explicitly sent coupon_code (even empty, meaning
     *     "remove it") and it differs from what's currently applied, swaps
     *     to it.
     *
     * Any actual change to money owed only ever happens once the existing
     * attempt has genuinely concluded (requires_payment_method after a
     * decline, or already canceled) — never while Stripe still has it live
     * (processing/requires_action), where a real charge could still land
     * behind our back.
     */
    protected function resolveRetryRequest(Ad $ad, ?string $couponCode, bool $couponCodeProvided, string $originalStatus): array
    {
        try {
            $intent = app(StripeService::class)->paymentIntent($ad->stripe_payment_intent_id);
        } catch (\Throwable $exception) {
            $ad->update(['status' => $originalStatus, 'payment_status' => 'payment_setup_failed']);
            throw $exception;
        }
        $status = $intent['status'] ?? null;

        $redemption = CouponRedemption::with('coupon')->where('ad_id', $ad->id)->latest('id')->first();
        $existingCode = $redemption?->coupon->code;
        $currentCoupon = $redemption ? [
            'applied' => true,
            'code' => $redemption->coupon->code,
            'discount_amount' => (float) $redemption->discount_amount,
        ] : null;

        // Work out what the coupon SHOULD be, independent of whether the
        // intent itself needs replacing.
        $warning = null;
        $targetCode = $existingCode;

        if ($couponCodeProvided) {
            $requested = trim((string) $couponCode);
            $targetCode = $requested !== '' ? $requested : null;
        } elseif ($redemption) {
            $coupon = $redemption->coupon;
            $stillValid = $coupon->is_active && $coupon->hasStarted() && ! $coupon->isExpired();
            if (! $stillValid) {
                $targetCode = null;
                $warning = __('api.ad.coupon_removed_expired');
            }
        }

        // A replace is needed either because the coupon is actually changing,
        // or because the intent itself is already dead and must be recreated
        // regardless (e.g. a plain status check hitting a cancelled intent
        // with no coupon involved at all — that must never be handed back to
        // the app's payment sheet as-is).
        $replaceNeeded = $status === 'canceled' || $targetCode !== $existingCode;

        if (! $replaceNeeded) {
            return app(ListingPaymentService::class)->publicationState($ad, $intent) + [
                'coupon' => $currentCoupon,
                'coupon_warning' => null,
            ];
        }

        if (! in_array($status, ['requires_payment_method', 'canceled'], true)) {
            // A change is warranted but unsafe to act on right now — money
            // could still land against the existing intent. Surface what we
            // know (including the expiry warning) without touching anything.
            return app(ListingPaymentService::class)->publicationState($ad, $intent) + [
                'coupon' => $currentCoupon,
                'coupon_warning' => $warning,
            ];
        }

        if ($status !== 'canceled') {
            try {
                app(StripeService::class)->cancel($ad->stripe_payment_intent_id);
            } catch (\Throwable) {
                // Progressed past a cancelable state between our check and this
                // call (e.g. it just succeeded) — leave the coupon and intent
                // alone rather than release under a payment that may have landed.
                return app(ListingPaymentService::class)->publicationState($ad, $intent) + [
                    'coupon' => $currentCoupon,
                    'coupon_warning' => $warning,
                ];
            }
        }

        if ($redemption) {
            app(CouponRedemptionService::class)->release($ad->id);
        }

        $type = Payment::where('stripe_payment_intent_id', $ad->stripe_payment_intent_id)->value('type') ?? 'listing';
        $baseFee = $type === 'extension'
            ? (float) setting('listing_extension_price', '0.99')
            : ($ad->mainCategory?->resolvedListingFee() ?? (float) setting('post_price', '0.99'));

        $fee = $baseFee;
        $coupon = null;
        $couponError = null;

        if ($targetCode !== null) {
            $redeemResult = app(CouponRedemptionService::class)->redeem($targetCode, $ad->user, $baseFee, $ad->id);
            if (isset($redeemResult['error'])) {
                // Falls back to full price — the requested code did not apply.
                $couponError = $redeemResult['error'];
                $warning = $this->couponErrorMessage($redeemResult);
            } else {
                $fee = (float) $redeemResult['final_amount'];
                $coupon = [
                    'applied' => true,
                    'code' => $redeemResult['code'],
                    'discount_amount' => $redeemResult['discount_amount'],
                ];

                // Stripe refuses to charge below its per-currency minimum. A
                // coupon that discounts into that gap can't be charged at all,
                // so treat it as fully covered rather than erroring out.
                $minCharge = app(StripeService::class)->minimumChargeAmount(config('services.stripe.currency', 'gbp'));
                if ($fee > 0 && $fee < $minCharge) {
                    $fee = 0;
                }
            }
        }

        if ($fee <= 0) {
            $publishedAt = now();
            $ad->update([
                'status' => 'published', 'published_at' => $publishedAt,
                'expires_at' => $publishedAt->copy()->addDays((int) setting('post_duration', '30')),
                'listing_fee' => 0, 'payment_status' => 'paid', 'stripe_payment_intent_id' => null,
            ]);
            $ad = $ad->fresh();
            $this->recordPayment($ad, $type, 0, 'paid', now());

            return app(ListingPaymentService::class)->publicationState($ad) + [
                'coupon' => $coupon,
                'coupon_warning' => $warning,
                'coupon_error' => $couponError,
            ];
        }

        try {
            $ad->update(['listing_fee' => $fee, 'stripe_payment_intent_id' => null, 'payment_status' => 'requires_payment']);
            $ad = $ad->fresh();
            $newIntent = app(StripeService::class)->createListingPaymentIntent($ad, $type);
            $ad->update(['stripe_payment_intent_id' => $newIntent['id']]);
        } catch (\Throwable $exception) {
            $ad->update(['status' => $originalStatus, 'payment_status' => 'payment_setup_failed']);
            throw $exception;
        }

        $this->recordPayment($ad, $type, $fee, 'requires_payment', null, $newIntent['id']);

        return app(ListingPaymentService::class)->publicationState($ad, $newIntent) + [
            'coupon' => $coupon,
            'coupon_warning' => $warning,
            'coupon_error' => $couponError,
        ];
    }

    /**
     * Maps a CouponRedemptionService error code to a translated message —
     * same mapping CouponController uses for the "preview" endpoint, kept in
     * sync so a code that fails validation reads the same reason wherever it
     * surfaces.
     */
    protected function couponErrorMessage(array $result): string
    {
        return match ($result['error']) {
            'not_started' => __('api.coupon.not_active_yet'),
            'expired' => __('api.coupon.expired'),
            'exhausted' => __('api.coupon.limit_reached'),
            'already_used' => __('api.coupon.already_used'),
            'min_amount' => __('api.coupon.min_amount', [
                'amount' => number_format($result['min_amount'] ?? 0, 2),
            ]),
            default => __('api.coupon.invalid'),
        };
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

    protected function freeAdsRemaining(int $userId): int
    {
        $used = Ad::withTrashed()->where('user_id', $userId)->where('is_free_listing', true)->count();
        return max(0, (int) setting('free_ads_per_user', '0') - $used);
    }

    protected function startExtension(Ad $ad, ?string $couponCode, bool $couponCodeProvided = false): array
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
            'extension',
            $couponCodeProvided
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
