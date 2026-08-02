<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ApproximatesLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdResource extends JsonResource
{
    use ApproximatesLocation;

    public function toArray(Request $request): array
    {
        $lang = $request->header('lang', 'en');
        $favoriteIds = $request->attributes->get('favorite_ad_ids', []);
        // An ad that has not been paid for has never been published, so it must
        // not report its creation time as "published 1 second ago".
        //
        // But a row whose status IS published must always carry a date: legacy
        // rows created before published_at was populated would otherwise start
        // returning null to clients that have always received a string. Fall
        // back only in that case, which keeps both invariants true.
        $publishedAt = $this->published_at
            ?? ($this->status === 'published' ? $this->created_at : null);

        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'status' => $this->status,
            'price' => $this->price !== null ? (float) $this->price : null,
            'currency' => $this->currency,
            'formatted_price' => $this->formattedPrice(),
            'is_negotiable' => (bool) $this->is_negotiable,
            'is_verified' => (bool) $this->is_verified,
            'cover_image_url' => $this->coverImageUrl(),
            'city_id' => $this->city_id,
            'city_name' => $this->city
                ? $this->city->nameForLanguageCode($lang)
                : null,
            'location_name' => $this->location_name,
            'location_label' => $this->location_name
                ?: ($this->city ? $this->city->nameForLanguageCode($lang) : null),
            'region' => $this->region,
            'category_id' => $this->main_category_id,
            'category_name' => $this->mainCategory
                ? $this->mainCategory->nameForLanguageCode($lang)
                : null,
            'sub_category_id' => $this->sub_category_id,
            'sub_category_name' => $this->subCategory
                ? $this->subCategory->nameForLanguageCode($lang)
                : null,
            'published_at' => $publishedAt?->toIso8601String(),
            'published_ago' => $publishedAt
                ? $publishedAt->locale($lang === 'ar' ? 'ar' : 'en')->diffForHumans()
                : null,
            'favorited_at' => $this->pivot?->created_at?->toIso8601String(),
            'is_favorited' => in_array($this->id, $favoriteIds, true),
        ] + $this->locationPayload(); // Now location is masked for non-owners
    }
}
