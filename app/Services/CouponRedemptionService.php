<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Discount codes applied to the listing fee at ad-creation time.
 *
 * Per the spec these exist ONLY in the Sell flow (page 11, step 4) and must
 * never surface in the profile area (page 13), so there is no "my coupons"
 * listing here by design.
 */
class CouponRedemptionService
{
    /**
     * Checks a code without consuming it, for the "apply" button preview.
     * Returns ['error' => reason] rather than throwing, so the controller can
     * map each reason to its own message.
     */
    public function preview(string $code, User $user, float $amount): array
    {
        $coupon = Coupon::whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])->first();

        if (! $coupon || ! $coupon->is_active) {
            return ['error' => 'invalid'];
        }

        if (! $coupon->hasStarted()) {
            return ['error' => 'not_started'];
        }

        if ($coupon->isExpired()) {
            return ['error' => 'expired'];
        }

        if ($coupon->isExhausted()) {
            return ['error' => 'exhausted'];
        }

        if ($coupon->redeemedBy($user->id)) {
            return ['error' => 'already_used'];
        }

        if ($coupon->min_amount !== null && $amount < (float) $coupon->min_amount) {
            return ['error' => 'min_amount', 'min_amount' => (float) $coupon->min_amount];
        }

        $discount = $coupon->discountFor($amount);

        return [
            'coupon' => $coupon,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'original_amount' => round($amount, 2),
            'discount_amount' => $discount,
            'final_amount' => round($amount - $discount, 2),
        ];
    }

    /**
     * Consumes the code. Re-validates first because preview and redeem are
     * separate requests and the coupon may have been exhausted in between.
     *
     * The unique index on (coupon_id, user_id) is the real guarantee: two
     * concurrent requests both passing validation still cannot both insert.
     */
    public function redeem(string $code, User $user, float $amount, ?int $adId = null): array
    {
        $preview = $this->preview($code, $user, $amount);

        if (isset($preview['error'])) {
            return $preview;
        }

        /** @var Coupon $coupon */
        $coupon = $preview['coupon'];

        try {
            return DB::transaction(function () use ($coupon, $user, $preview, $adId) {
                $redemption = CouponRedemption::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $user->id,
                    'ad_id' => $adId,
                    'original_amount' => $preview['original_amount'],
                    'discount_amount' => $preview['discount_amount'],
                    'final_amount' => $preview['final_amount'],
                ]);

                $coupon->increment('redemptions_count');

                return [
                    'redemption_id' => $redemption->id,
                    'code' => $coupon->code,
                    'original_amount' => $preview['original_amount'],
                    'discount_amount' => $preview['discount_amount'],
                    'final_amount' => $preview['final_amount'],
                ];
            });
        } catch (QueryException $e) {
            // Unique violation: the user already redeemed this coupon.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return ['error' => 'already_used'];
            }

            throw $e;
        }
    }

    /**
     * Undoes a redemption tied to an ad whose payment attempt is dead, so
     * the code (or a different one) can be applied again. Only call this
     * once the attempt is confirmed over — never while it could still
     * succeed.
     */
    public function release(int $adId): void
    {
        DB::transaction(function () use ($adId) {
            $redemption = CouponRedemption::where('ad_id', $adId)->lockForUpdate()->latest('id')->first();
            if (! $redemption) {
                return;
            }

            $redemption->coupon()->decrement('redemptions_count');
            $redemption->delete();
        });
    }
}
