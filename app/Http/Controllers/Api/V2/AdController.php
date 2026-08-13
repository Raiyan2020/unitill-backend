<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdDetailResource;
use App\Models\Ad;
use App\Traits\HandlesListingPayments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * V2 checkout confirmation: pays for a listing only after the seller has
 * actively confirmed "Start my listing immediately", with that confirmation
 * kept on record (publish_confirmed_at, alongside the resulting payment
 * intent on the same ad row).
 *
 * V1's POST /ads/{id}/publish takes no such confirmation and the published
 * app does not send one, so it stays untouched — this lives on its own route.
 */
class AdController extends Controller
{
    use HandlesListingPayments;

    public function publish(Request $request, string $id)
    {
        $request->validate([
            'confirm_publish_immediately' => 'required|accepted',
        ], [
            'confirm_publish_immediately.required' => __('ad_v2.confirmation_required'),
            'confirm_publish_immediately.accepted' => __('ad_v2.confirmation_required'),
        ]);

        $user = Auth::user();
        $ad = Ad::where('id', $id)->orWhere('public_id', $id)->first();
        if (! $ad || $ad->user_id !== $user->id) {
            return sendError(__('api.ad.not_found'), [], 404);
        }

        if (! in_array($ad->status, ['draft', 'pending', 'paused', 'expired', 'published'], true)) {
            return sendError(__('api.ad.cannot_publish_in_status'), ['status' => $ad->status], 422);
        }

        if ($ad->images()->count() === 0) {
            return sendError(__('api.ad.image_required'), [], 422);
        }

        $ad->update(['publish_confirmed_at' => now()]);

        $publication = $this->startPublication($ad->fresh(), $request->input('coupon_code'));
        if (isset($publication['coupon_error'])) {
            return sendError(__('api.ad.coupon_failed'), ['coupon_code' => $publication['coupon_error']], 422);
        }

        $ad->load([
            'images',
            'city.translations',
            'mainCategory.translations',
            'subCategory.translations',
            'attributeValues.definition.translations',
        ]);

        return sendResponse(
            ['publication' => $publication, 'ad' => new AdDetailResource($ad)],
            $publication['published']
                ? __('api.ad.published')
                : __('api.ad.payment_required')
        );
    }
}
