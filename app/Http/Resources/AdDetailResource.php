<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ApproximatesLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdDetailResource extends JsonResource
{
    use ApproximatesLocation;

    public function toArray(Request $request): array
    {
        $lang = $request->header('lang', 'en');
        $locale = $lang === 'ar' ? 'ar' : 'en';
        $favoriteIds = $request->attributes->get('favorite_ad_ids', []);
        // See AdResource: null until payment settles, but never null on a row
        // that is already published.
        $publishedAt = $this->published_at
            ?? ($this->status === 'published' ? $this->created_at : null);

        // The seller's "show first name only" preference governs how they are
        // named on their own listings.
        $sellerName = trim(
            ($this->user?->first_name ?? '')
            .($this->user?->show_last_name ? ' '.($this->user?->last_name ?? '') : '')
        );
        if ($sellerName === '') {
            $sellerName = (string) ($this->user?->name ?? '');
        }

        $viewerId = auth('sanctum')->id();
        $isOwner = $viewerId && (int) $this->user_id === (int) $viewerId;

        // Public fields visible to guests. 
        // Any sensitive data is strictly kept in privateFields().
        $public = [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'title' => $this->title,
            'status' => $this->status,
            'is_owner' => (bool) $isOwner,
            'price' => $this->price !== null ? (float) $this->price : null,
            'currency' => $this->currency,
            'formatted_price' => $this->formattedPrice(),
            'is_negotiable' => (bool) $this->is_negotiable,
            'cover_image_url' => $this->coverImageUrl(),
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(function ($image) {
                    $path = ltrim((string) $image->path, '/');
                    return [
                        'id' => $image->id,
                        'url' => url('/storage/'.$path),
                        'sort_order' => $image->sort_order,
                    ];
                })->values();
            }),
            'category_path' => array_values(array_filter([
                $this->mainCategory?->nameForLanguageCode($lang),
                $this->subCategory?->nameForLanguageCode($lang),
            ])),
            'main_category_id' => $this->main_category_id,
            'main_category_name' => $this->mainCategory ? $this->mainCategory->nameForLanguageCode($lang) : null,
            'sub_category_id' => $this->sub_category_id,
            'sub_category_name' => $this->subCategory ? $this->subCategory->nameForLanguageCode($lang) : null,
        ];

        // Guests receive the same field set as authenticated viewers. The
        // parallel implementation stripped 17 fields for guests and replaced
        // them with requires_auth/hidden_for_guests, which empties the public
        // ad detail screen. Sensitive location data is still protected — but by
        // masking in locationPayload(), not by removing the keys, so the shape
        // of the response never depends on who is asking.
        return $public + $this->detailFields($lang, $locale, $publishedAt, $sellerName, $favoriteIds);
    }

    /**
     * The full detail payload. Location values within it are masked for anyone
     * who is not the owner; the keys themselves are always present.
     */
    private function detailFields(
        string $lang,
        string $locale,
        ?\Illuminate\Support\Carbon $publishedAt,
        string $sellerName,
        array $favoriteIds
    ): array {
        return [
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'is_verified' => (bool) $this->is_verified,
            'city_id' => $this->city_id,
            'city_name' => $this->city ? $this->city->nameForLanguageCode($lang) : null,
            'location_name' => $this->location_name,
            'location_label' => $this->location_name ?: ($this->city ? $this->city->nameForLanguageCode($lang) : null),
            'region' => $this->region,
            'country_id' => $this->country_id,
            'attributes' => $this->whenLoaded('attributeValues', function () use ($lang) {
                // Multiselect attributes store one row per selected value, so
                // group by slug and join — otherwise the same attribute appears
                // several times in the detail sheet.
                return $this->attributeValues
                    ->groupBy(fn ($row) => $row->definition?->slug)
                    ->map(function ($rows, $slug) use ($lang) {
                        $first = $rows->first();
                        $definition = $first->definition;
                        $values = $rows->pluck('value')->filter()->values();

                        return [
                            'slug' => $slug,
                            'label' => $definition?->labelForLanguageCode($lang),
                            // `value` stays the raw stored option the app filters
                            // with; `value_label` is the localized display text.
                            'value' => $values->implode(', '),
                            'value_label' => $values
                                ->map(fn ($value) => $definition
                                    ? $definition->optionLabelForLanguageCode($lang, (string) $value)
                                    : (string) $value)
                                ->implode(', '),
                        ];
                    })
                    ->values();
            }),
            'seller' => $this->whenLoaded('user', function () use ($sellerName) {
                $trustedContact = $this->user->is_trusted_seller
                    ? $this->user->latestApprovedTrustedSellerApplication
                    : null;

                return [
                    'id' => $this->user->id,
                    'name' => $sellerName,
                    'image' => $this->user->image ? getimg($this->user->image) : null,
                    'is_trusted_seller' => (bool) $this->user->is_trusted_seller,
                    'average_rating' => round((float) ($this->user->average_rating_received ?? 0), 1),
                    'total_reviews' => (int) ($this->user->total_reviews_count ?? 0),
                    'contact_phone' => $trustedContact?->contact_phone,
                    'contact_email' => $trustedContact?->contact_email,
                    'preferred_contact_method' => $trustedContact?->preferred_student_contact_method,
                ];
            }),
            'published_at' => $publishedAt?->toIso8601String(),
            'published_ago' => $publishedAt ? $publishedAt->locale($locale)->diffForHumans() : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'is_favorited' => in_array($this->id, $favoriteIds, true),
        ] + $this->locationPayload(); // Apply the privacy masking logic here
    }
}