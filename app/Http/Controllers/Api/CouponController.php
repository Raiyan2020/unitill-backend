<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CouponRedemptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CouponController extends Controller
{
    public function __construct(protected CouponRedemptionService $coupons) {}

    /**
     * Previews a discount code against the current listing fee. Does not
     * consume the code — that happens when the ad is actually published.
     */
    public function validateCode(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:40',
            'main_category_id' => 'sometimes|nullable|integer|exists:categories,id',
        ]);

        $ar = $request->header('lang') === 'ar';
        $category = isset($validated['main_category_id'])
            ? Category::find($validated['main_category_id'])
            : null;
        $amount = $category ? $category->resolvedListingFee() : (float) setting('post_price', '0.99');

        $result = $this->coupons->preview($validated['code'], Auth::user(), $amount);

        if (isset($result['error'])) {
            return sendError(
                $this->errorMessage($result, $ar),
                ['code' => $result['error']],
                422
            );
        }

        unset($result['coupon']);
        $result['formatted_discount'] = '£'.number_format($result['discount_amount'], 2);
        $result['formatted_final'] = '£'.number_format($result['final_amount'], 2);

        return sendResponse($result, __('api.coupon.applied'));
    }

    private function errorMessage(array $result, bool $ar): string
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
}
