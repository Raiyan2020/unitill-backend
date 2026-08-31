<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdResource;
use App\Http\Resources\CategoryResource;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Support\AdSort;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $lang = $request->header('lang', 'en');
        $user = auth('sanctum')->user();
        $cityId = $request->integer('city_id') ?: $user?->city_id;

        $request->validate([
            'sort' => ['nullable', Rule::in(AdSort::allowed())],
            'city_id' => 'nullable|integer|exists:cities,id',
        ]);

        $sort = AdSort::normalize($request->input('sort'));

        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->with('translations')
            ->orderBy('sort')
            ->get();

        $adsQuery = Ad::query()
            ->published()
            ->with([
                'city.translations',
                'mainCategory.translations',
            ]);

        AdSort::apply($adsQuery, $sort);

        if ($cityId) {
            $adsQuery->where('city_id', $cityId);
        }

        $this->attachFavoriteIds($request);

        $perPage = max(1, min((int) $request->input('per_page', 20), 50));
        $ads = $adsQuery->paginate($perPage);

        $recentAds = AdResource::collection($ads)->response()->getData(true);
        $recentAds['sort_options'] = AdSort::options($lang);
        $recentAds['current_sort'] = $sort;

        return sendResponse([
            'location' => $this->resolveLocation($cityId, $lang),
            'categories' => CategoryResource::collection($categories),
            'recent_ads' => $recentAds,
        ]);
    }

    protected function resolveLocation(?int $cityId, string $lang): ?array
    {
        if (! $cityId) {
            return null;
        }

        $city = City::query()
            ->where('status', 'active')
            ->with('translations')
            ->find($cityId);

        if (! $city) {
            return null;
        }

        $name = $city->nameForLanguageCode($lang);

        return [
            'city_id' => $city->id,
            'city_name' => $name,
            'display_name' => strtoupper($name),
        ];
    }

    protected function attachFavoriteIds(Request $request): void
    {
        $user = auth('sanctum')->user();
        $favoriteIds = $user
            ? $user->favoriteAds()->pluck('ads.id')->all()
            : [];

        $request->attributes->set('favorite_ad_ids', $favoriteIds);
    }
}
