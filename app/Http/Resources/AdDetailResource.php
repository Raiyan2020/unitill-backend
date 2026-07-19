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
        $publishedAt = $this->published_at ?? $this->created_at;

        $sellerName = trim(
            ($this->user?->first_name ?? '').' '.($this->user?->last_name ?? '')
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

        if (! $viewerId) {
            return $public + [
                // Signal to Flutter that it should prompt for registration
                'requires_auth' => true,
                'hidden_for_guests' => ['description', 'location', 'attributes', 'seller'],
            ];
        }

        return $public + $this->privateFields($lang, $locale, $publishedAt, $sellerName, $favoriteIds);
    }

    /**
     * Fields requiring authentication, including masked location data.
     */
    private function privateFields(
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
                return $this->attributeValues->map(function ($row) use ($lang) {
                    return [
                        'slug' => $row->definition?->slug,
                        'label' => $row->definition?->labelForLanguageCode($lang),
                        'value' => $row->value,
                    ];
                })->values();
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