<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdResource;
use App\Models\Ad;
use App\Support\AdSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FavoritedController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'sort' => ['nullable', Rule::in(AdSort::allowed())],
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $user = Auth::user();
        $sort = AdSort::normalize($validated['sort'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = $user->favoriteAds()
            ->select('ads.*')
            ->with([
                'city.translations',
                'mainCategory.translations',
            ]);

        match ($sort) {
            AdSort::PRICE_LOW_TO_HIGH => $query->orderBy('ads.price')->orderByDesc('ad_favorites.id'),
            AdSort::PRICE_HIGH_TO_LOW => $query->orderByDesc('ads.price')->orderByDesc('ad_favorites.id'),
            default => $query->orderByDesc('ad_favorites.created_at'),
        };

        $favoriteIds = $user->favoriteAds()->pluck('ads.id')->all();
        $request->attributes->set('favorite_ad_ids', $favoriteIds);

        $ads = $query->paginate($perPage);

        $response = AdResource::collection($ads)->response()->getData(true);
        $response['sort_options'] = AdSort::options($request->header('lang', 'en'));
        $response['current_sort'] = $sort;

        return sendResponse($response);
    }

    public function store(Request $request)
    {
        $lang = $request->header('lang') === 'ar';

        $validated = $request->validate([
            'ad_id' => 'required|integer|exists:ads,id',
        ]);

        $user = Auth::user();
        $adId = (int) $validated['ad_id'];

        if ($user->favoriteAds()->where('ads.id', $adId)->exists()) {
            return sendError(
                __('api.favorite.already_favorited'),
                [],
                422
            );
        }

        $ad = Ad::query()
            ->published()
            ->with(['city.translations', 'mainCategory.translations'])
            ->find($adId);

        if (! $ad) {
            return sendError(
                __('api.favorite.ad_not_available'),
                [],
                404
            );
        }

        $user->favoriteAds()->attach($adId);

        $request->attributes->set('favorite_ad_ids', [$adId]);

        return sendResponse(
            new AdResource($ad),
            __('api.favorite.added')
        );
    }

    public function destroy(Request $request, int $ad_id)
    {
        $lang = $request->header('lang') === 'ar';
        $user = Auth::user();

        if (! $user->favoriteAds()->where('ads.id', $ad_id)->exists()) {
            return sendError(
                __('api.favorite.not_favorited'),
                [],
                404
            );
        }

        $user->favoriteAds()->detach($ad_id);

        return sendResponse(
            ['ad_id' => $ad_id],
            __('api.favorite.removed')
        );
    }
}
