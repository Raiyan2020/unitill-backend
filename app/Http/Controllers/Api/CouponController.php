<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        ]);

        $ar = $request->header('lang') === 'ar';
        $amount = (float) (setting('post_price') ?? 0);

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

        return sendResponse($result, $ar ? 'تم تطبيق كود الخصم' : 'Discount code applied');
    }

    private function errorMessage(array $result, bool $ar): string
    {
        return match ($result['error']) {
            'not_started' => $ar ? 'هذا الكود لم يبدأ العمل به بعد' : 'This code is not active yet',
            'expired' => $ar ? 'انتهت صلاحية هذا الكود' : 'This code has expired',
            'exhausted' => $ar ? 'تم استهلاك جميع استخدامات هذا الكود' : 'This code has reached its usage limit',
            'already_used' => $ar ? 'لقد استخدمت هذا الكود من قبل' : 'You have already used this code',
            'min_amount' => $ar
                ? 'هذا الكود يتطلب مبلغاً أدنى قدره £'.number_format($result['min_amount'] ?? 0, 2)
                : 'This code requires a minimum of £'.number_format($result['min_amount'] ?? 0, 2),
            default => $ar ? 'كود الخصم غير صالح' : 'Invalid discount code',
        };
    }
}
