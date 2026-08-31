<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\MyAdResource;
use App\Models\Ad;
use App\Traits\HandlesListingPayments;
use Illuminate\Http\Request;

class MyAdController extends Controller
{
    use HandlesListingPayments;

    public function extend(Request $request, string $id)
    {
        $request->validate([
            'confirm_publish_immediately' => 'required|accepted',
        ], [
            'confirm_publish_immediately.required' => __('ad_v2.confirmation_required'),
            'confirm_publish_immediately.accepted' => __('ad_v2.confirmation_required'),
        ]);

        $ad = Ad::query()
            ->where('user_id', $request->user()->id)
            ->where(fn ($query) => $query->where('id', $id)->orWhere('public_id', $id))
            ->first();

        if (! $ad || ! in_array($ad->status, ['paused', 'expired'], true)) {
            return sendError(__('api.ad.only_expired_extend'), [], 422);
        }

        if ($this->hasUnexpiredPaidPeriod($ad)) {
            return sendError(__('api.ad.not_yet_eligible_extend'), [], 422);
        }

        $ad->update(['publish_confirmed_at' => now()]);

        $publication = $this->startExtension($ad, $request->input('coupon_code'), $request->has('coupon_code'));

        if (isset($publication['coupon_error'])) {
            return sendError(
                __('api.ad.coupon_failed'),
                ['coupon_code' => $publication['coupon_error']],
                422
            );
        }

        return sendResponse(
            [
                'ad' => new MyAdResource($ad->fresh()),
                'publication' => $publication,
            ],
            __('api.ad.extended')
        );
    }
}
