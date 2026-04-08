<?php

namespace App\Traits;

use App\Models\Coupon;
use App\Models\Post;

trait CouponTrait
{
    public function validateAndApplyCoupon($request, $user)
    {
        if (!$request->has('coupon_code') || !$request->coupon_code) {
            return [
                'coupon' => null,
                'discount' => 0,
                'final_price' =>  setting('post_price'),
                'error' => null
            ];
        }

        $coupon = Coupon::where('code', $request->coupon_code)
            ->where('is_active', 1)
            ->first();

        if (!$coupon) {
            $message = $request->header('lang') == 'en' ? 'The coupon is invalid.' : 'الكوبون غير صالح.';
            return ['error' => $message];
        }

        $now = now();

        if ($coupon->starts_at && $now->lt($coupon->starts_at)) {
            $messageAccordingToLang = $request->header('lang') == 'en' ? 'The coupon is not active yet.' : 'الم كوبون غير مفعل بعد.';
            return ['error' => $messageAccordingToLang];
        }

        if ($coupon->expires_at && $now->gt($coupon->expires_at)) {
            $messageExpired = $request->header('lang') == 'en' ? 'The coupon has expired.' : 'انتهت صلاحية الكوبون.';
            return ['error' => $messageExpired];
        }

        if ($coupon->min_amount && setting('post_price') < $coupon->min_amount) {
            $messagePriceTooLow = $request->header('lang') == 'en' ? 'The price is below the minimum amount required for the coupon.' : 'السعر أقل من الحد الأدنى المسموح للكوبون.';
            return ['error' =>$messagePriceTooLow];
        }

        if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
            //message all uses consumed
            $messageAllUsesConsumed = $request->header('lang') == 'en' ? 'All uses of this coupon have been consumed.' : 'تم استهلاك جميع استخدامات هذا الكوبون.';
            return ['error' => $messageAllUsesConsumed];
        }

        if ($coupon->max_uses_per_user) {
            $userUsed = Post::where('user_id', $user->id)
                ->where('coupon_id', $coupon->id)
                ->count();

            if ($userUsed >= $coupon->max_uses_per_user) {

                //message user has used this coupon before
                $messageUserUsedBefore = $request->header('lang') == 'en' ? 'You have already used this coupon.' : 'لقد استخدمت هذا الكوبون مسبقاً.';
                return ['error' => $messageUserUsedBefore];
            }
        }

        // حساب الخصم
        $discount = $coupon->type === 'percent'
            ? (setting('post_price') * $coupon->value) / 100
            : $coupon->value;

        if ($discount > setting('post_price')) {
            $discount = setting('post_price');
        }

        $finalPrice = setting('post_price') - $discount;

        return [
            'coupon' => $coupon,
            'discount' => $discount,
            'final_price' => $finalPrice,
            'error' => null
        ];
    }
}
