<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdResource;
use App\Models\Ad;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The public-safe view of another user. Deliberately narrower than
 * GET /show-profile: no email of any kind, no phone, no city id.
 */
class SellerController extends Controller
{
    public function show(Request $request, int $id)
    {
        $lang = (string) $request->header('lang', 'en');
        $locale = $lang === 'ar' ? 'ar' : 'en';

        $seller = User::query()
            ->withCount([
                'ads as active_ads_count' => fn ($query) => $query
                    ->where('status', 'published')
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
                'ratingsReceived as total_reviews_count',
            ])
            ->withAvg('ratingsReceived as average_rating', 'score')
            ->find($id);

        if (! $seller) {
            return sendError(__('api.auth.user_not_found'), [], 404);
        }

        // Honours the seller's own "show last name" preference.
        $name = $seller->show_last_name
            ? trim($seller->first_name.' '.$seller->last_name)
            : (string) $seller->first_name;

        return sendResponse([
            'id' => $seller->id,
            'name' => $name !== '' ? $name : (string) $seller->name,
            'first_name' => $seller->first_name,
            'image' => $seller->image ? getimg($seller->image) : null,
            'is_verified_student' => $seller->student_verified_at !== null,
            'is_trusted_seller' => (bool) $seller->is_trusted_seller,
            'average_rating' => round((float) ($seller->average_rating ?? 0), 1),
            'total_reviews' => (int) ($seller->total_reviews_count ?? 0),
            'active_ads_count' => (int) ($seller->active_ads_count ?? 0),
            'created_at' => $seller->created_at?->toIso8601String(),
            'member_since' => $seller->created_at
                ? $seller->created_at->locale($locale)->translatedFormat('F Y')
                : null,
        ]);
    }

    /** The seller's live listings, paginated, using the standard ads shape. */
    public function ads(Request $request, int $id)
    {
        if (! User::query()->whereKey($id)->exists()) {
            return sendError(__('api.auth.user_not_found'), [], 404);
        }

        $ads = Ad::query()
            ->where('user_id', $id)
            ->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with(['city.translations', 'mainCategory.translations', 'subCategory.translations'])
            ->orderByDesc('published_at')
            ->paginate(min((int) $request->input('per_page', 20), 50));

        return sendResponse(AdResource::collection($ads)->response()->getData(true));
    }
}
