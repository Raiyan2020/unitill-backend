<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdRequest;
use App\Http\Resources\AdDetailResource;
use App\Http\Resources\AdResource;
use App\Models\Ad;
use App\Models\AdAttributeValue;
use App\Models\AdImage;
use App\Models\Category;
use App\Models\CategoryAttributeDefinition;
use App\Services\CouponRedemptionService;
use App\Services\StripeService;
use App\Services\ListingPaymentService;
use App\Traits\HandlesListingPayments;
use App\Support\AdFilters;
use App\Support\AdSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdController extends Controller
{
    use HandlesListingPayments;
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'q' => 'nullable|string|max:255',
            'sort' => ['nullable', Rule::in(AdSort::allowed())],
            'category_id' => 'nullable|integer|exists:categories,id',
            'main_category_id' => 'nullable|integer|exists:categories,id',
            'sub_category_id' => 'nullable|integer|exists:categories,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'is_negotiable' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:50',
            'filters' => 'nullable',
        ]);

        $lang = $request->header('lang', 'en');
        $sort = $validated['sort'] ?? 'newest';
        $perPage = (int) ($validated['per_page'] ?? 20);
        $search = trim((string) ($validated['search'] ?? $validated['q'] ?? ''));
        $attributeFilters = AdFilters::extractFromRequest($request);

        $query = Ad::query()
            ->published()
            ->with([
                'city.translations',
                'mainCategory.translations',
                'subCategory.translations',
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('public_id', 'like', "%{$search}%");
            });
        }

        $mainCategoryId = (int) ($validated['main_category_id'] ?? $validated['category_id'] ?? 0);
        $subCategoryId = (int) ($validated['sub_category_id'] ?? 0);

        if ($subCategoryId > 0) {
            $query->where('sub_category_id', $subCategoryId);
        } elseif ($mainCategoryId > 0) {
            $query->where(function ($q) use ($mainCategoryId) {
                $q->where('main_category_id', $mainCategoryId)
                    ->orWhere('sub_category_id', $mainCategoryId);
            });
        }

        if (! empty($validated['city_id'])) {
            $query->where('city_id', (int) $validated['city_id']);
        }

        if (isset($validated['price_min'])) {
            $query->where('price', '>=', (float) $validated['price_min']);
        }

        if (isset($validated['price_max'])) {
            $query->where('price', '<=', (float) $validated['price_max']);
        }

        if (array_key_exists('is_negotiable', $validated)) {
            $query->where('is_negotiable', (bool) $validated['is_negotiable']);
        }

        AdFilters::apply($query, $attributeFilters);

        // Viewer coordinates (sent by the location interceptor as headers, or as
        // query params) power distance sorting.
        $viewerLat = $request->header('lat') ?? $request->input('lat');
        $viewerLng = $request->header('lng') ?? $request->input('lng');
        AdSort::apply(
            $query,
            $sort,
            is_numeric($viewerLat) ? (float) $viewerLat : null,
            is_numeric($viewerLng) ? (float) $viewerLng : null
        );

        $this->attachFavoriteIds($request);

        $ads = $query->paginate($perPage);

        $response = AdResource::collection($ads)->response()->getData(true);
        $response['sort_options'] = AdSort::options($lang);
        $response['current_sort'] = AdSort::normalize($sort);
        $response['applied_filters'] = array_filter([
            'search' => $search !== '' ? $search : null,
            'main_category_id' => $mainCategoryId > 0 ? $mainCategoryId : null,
            'sub_category_id' => $subCategoryId > 0 ? $subCategoryId : null,
            'city_id' => $validated['city_id'] ?? null,
            'price_min' => $validated['price_min'] ?? null,
            'price_max' => $validated['price_max'] ?? null,
            'is_negotiable' => $validated['is_negotiable'] ?? null,
            'attributes' => $attributeFilters ?: null,
        ]);

        // Attribute definitions live on the main category, so resolve to it
        // even when the viewer is browsing a subcategory.
        $attributeCategoryId = $mainCategoryId;
        if ($attributeCategoryId === 0 && $subCategoryId > 0) {
            $attributeCategoryId = (int) (Category::whereKey($subCategoryId)->value('parent_id') ?? $subCategoryId);
        }
        if ($attributeCategoryId > 0) {
            $response['filter_options'] = $this->filterOptionsForCategory($attributeCategoryId, $lang);
        }

        return sendResponse($response);
    }

    public function show(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $userId = auth('sanctum')->id();

        $ad = Ad::query()
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('public_id', $id);
            })
            ->with([
                'images',
                'city.translations',
                'mainCategory.translations',
                'subCategory.translations',
                'attributeValues.definition.translations',
                'user' => function ($query) {
                    $query->withCount('ratingsReceived as total_reviews_count')
                        ->withAvg('ratingsReceived as average_rating_received', 'score')
                        ->with('latestApprovedTrustedSellerApplication');
                },
            ])
            ->first();

        // The owner may view their own ad in any status (draft / inactive /
        // sold); everyone else only sees published ads that have not expired.
        // The expiry check matters because the scheduled command runs hourly,
        // so an ad can be past expires_at while still flagged "published".
        $isOwner = $ad && $userId && (int) $ad->user_id === (int) $userId;
        $isVisible = $ad && $ad->status === 'published' && ! $ad->isExpired();
        if (! $ad || (! $isVisible && ! $isOwner)) {
            return sendError(
                $lang ? 'الإعلان غير موجود' : 'Ad not found',
                [],
                404
            );
        }

        $this->attachFavoriteIds($request);

        return sendResponse(new AdDetailResource($ad));
    }

    protected function filterOptionsForCategory(int $categoryId, string $lang): array
    {
        return CategoryAttributeDefinition::query()
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->map(function (CategoryAttributeDefinition $definition) use ($lang) {
                return [
                    'slug' => $definition->slug,
                    'label' => $definition->labelForLanguageCode($lang),
                    'input_type' => $definition->input_type,
                    'options' => $definition->options ?? [],
                    'is_required' => (bool) $definition->is_required,
                ];
            })
            ->values()
            ->all();
    }

    public function store(StoreAdRequest $request)
    {
        $lang = $request->header('lang') === 'ar';
        $user = Auth::user();
        $data = $request->validated();
        $confirmPublish = (bool) ($data['confirm_publish'] ?? false);
        $attributes = (array) ($data['attributes'] ?? []);
        // Attribute definitions live on the main category; resolve from both.
        $specCategoryIds = array_values(array_filter([
            (int) $data['main_category_id'],
            (int) ($data['sub_category_id'] ?? 0),
        ]));

        $ad = DB::transaction(function () use ($request, $user, $data, $confirmPublish, $attributes, $specCategoryIds) {
            $imagePaths = [];
            foreach ($request->file('images', []) as $index => $image) {
                $imagePaths[] = [
                    'path' => uploader($image, 'ads'),
                    'sort_order' => $index,
                ];
            }

            $coverPath = ltrim(str_replace('/storage/', '', $imagePaths[0]['path']), '/');
            $title = $data['title'];
            $publicId = strtoupper(Str::random(10));

            $ad = Ad::create([
                'user_id' => $user->id,
                'public_id' => $publicId,
                'title' => $title,
                'subtitle' => $data['subtitle'] ?? null,
                'license_plate' => $data['license_plate'] ?? null,
                'description' => $data['description'] ?? null,
                'country_id' => $request->countryId(),
                'city_id' => $data['city_id'],
                'postcode' => $data['postcode'] ?? null,
                'region' => $data['region'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'main_category_id' => $data['main_category_id'],
                'sub_category_id' => $data['sub_category_id'] ?? null,
                'cover_image' => $coverPath,
                'price' => $data['price'],
                'currency' => strtoupper($data['currency'] ?? 'GBP'),
                'is_negotiable' => (bool) ($data['is_negotiable'] ?? false),
                'is_verified' => false,
                'slug' => Str::slug($title.'-'.$publicId),
                'status' => 'draft',
            ]);

            foreach ($imagePaths as $row) {
                AdImage::create([
                    'ad_id' => $ad->id,
                    'path' => ltrim(str_replace('/storage/', '', $row['path']), '/'),
                    'sort_order' => $row['sort_order'],
                ]);
            }

            $definitions = CategoryAttributeDefinition::query()
                ->whereIn('category_id', $specCategoryIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('slug');

            foreach ($attributes as $slug => $value) {
                if ($value === null || $value === '' || ! isset($definitions[$slug])) {
                    continue;
                }

                AdAttributeValue::create([
                    'ad_id' => $ad->id,
                    'category_attribute_definition_id' => $definitions[$slug]->id,
                    'value' => (string) $value,
                ]);
            }

            return $ad;
        });

        $publication = $confirmPublish ? $this->startPublication($ad, $request->input('coupon_code')) : null;
        if (isset($publication['coupon_error'])) {
            return sendError('The coupon code could not be applied.', ['coupon_code' => $publication['coupon_error']], 422);
        }
        $appliedCoupon = $publication['coupon'] ?? null;
        if ($publication !== null) {
            unset($publication['coupon']);
        }
        $ad->refresh()->load([
            'images',
            'city.translations',
            'mainCategory.translations',
            'subCategory.translations',
            'attributeValues.definition.translations',
        ]);

        $listingFee = (float) (setting('post_price') ?? 0);

        return sendResponse(
            array_replace(
                $this->listingFeePayload($request, $ad, $listingFee, false),
                [
                    'coupon' => $appliedCoupon,
                    'total_amount' => (float) ($publication['amount'] ?? 0),
                    'formatted_total_amount' => '£' . number_format((float) ($publication['amount'] ?? 0), 2),
                ]
            )
                + ['publication' => $publication]
                + ['ad' => new AdDetailResource($ad)],
            $lang ? 'تم إنشاء الإعلان بنجاح' : 'Ad created successfully'
        );
    }

    /**
     * Builds the step-4 (Pay) payload for a newly created ad.
     *
     * The code is only consumed when the ad actually goes live — a draft that
     * is never published must not burn the user's single use. If redemption
     * fails the ad still stands; the fee simply comes back undiscounted rather
     * than the whole request failing after the ad was already created.
     */
    protected function listingFeePayload(
        Request $request,
        Ad $ad,
        float $listingFee,
        bool $charge
    ): array {
        $due = $charge ? $listingFee : 0.0;
        $coupon = null;

        $code = trim((string) $request->input('coupon_code', ''));

        if ($charge && $code !== '' && $listingFee > 0) {
            $result = app(CouponRedemptionService::class)
                ->redeem($code, $request->user(), $listingFee, $ad->id);

            if (isset($result['error'])) {
                $coupon = ['applied' => false, 'code' => $code, 'reason' => $result['error']];
            } else {
                $due = $result['final_amount'];
                $coupon = [
                    'applied' => true,
                    'code' => $result['code'],
                    'discount_amount' => $result['discount_amount'],
                    'formatted_discount' => '£'.number_format($result['discount_amount'], 2),
                ];
            }
        }

        return [
            'listing_fee' => $listingFee,
            'formatted_listing_fee' => '£'.number_format($listingFee, 2),
            'coupon' => $coupon,
            'total_amount' => $due,
            'formatted_total_amount' => '£'.number_format($due, 2),
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

    public function storeDraft(Request $request)
    {
        $lang = $request->header('lang') === 'ar';
        $user = Auth::user();
        
        $cityId = $request->input('city_id');
        if ($cityId == 1 && !\App\Models\City::where('id', 1)->exists()) {
            $userCity = $user->city_id;
            if ($userCity && \App\Models\City::where('id', $userCity)->exists()) {
                $cityId = $userCity;
            } else {
                $cityId = \App\Models\City::first()?->id ?? 1;
            }
        }

        $validated = $request->validate([
            'main_category_id' => 'required|integer|exists:categories,id',
            'sub_category_id' => 'nullable|integer|exists:categories,id',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'license_plate' => [
                'nullable',
                'string',
                'regex:/^[A-Z]{2}[0-9]{2}[A-Z]{3}$/', 
                'max:7'
            ],
            'description' => 'nullable|string|max:5000',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'is_negotiable' => 'nullable|boolean',
            'postcode' => ['nullable', 'string', 'regex:/^([A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2}|GIR ?0AA)$/i'],
            'region' => 'nullable|string|max:191',
            'location_name' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'attributes' => 'nullable|array',
            'attributes.*' => 'nullable|string|max:1000',
        ]);

        $attributes = (array) ($validated['attributes'] ?? []);
        // Attribute definitions live on the main category, so resolve them from
        // both main and sub category ids (the sub rarely carries its own).
        $specCategoryIds = array_values(array_filter([
            (int) $validated['main_category_id'],
            (int) ($validated['sub_category_id'] ?? 0),
        ]));

        $ad = DB::transaction(function () use ($request, $user, $validated, $attributes, $specCategoryIds, $cityId) {
            $title = $validated['title'];
            $publicId = strtoupper(Str::random(10));

            $ad = Ad::create([
                'user_id' => $user->id,
                'public_id' => $publicId,
                'title' => $title,
                'subtitle' => $validated['subtitle'] ?? null,
                'license_plate' => $validated['license_plate'] ?? null,
                'description' => $validated['description'] ?? null,
                'country_id' => \App\Models\City::find($cityId)?->country_id ?? 1,
                'city_id' => $cityId,
                'main_category_id' => $validated['main_category_id'],
                'sub_category_id' => $validated['sub_category_id'] ?? null,
                'price' => $validated['price'],
                'currency' => strtoupper($validated['currency'] ?? 'GBP'),
                'is_negotiable' => (bool) ($validated['is_negotiable'] ?? false),
                'postcode' => $validated['postcode'] ?? null,
                'region' => $validated['region'] ?? null,
                'location_name' => $validated['location_name'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'is_verified' => false,
                'slug' => Str::slug($title.'-'.$publicId),
                'status' => 'draft',
            ]);

            $definitions = CategoryAttributeDefinition::query()
                ->whereIn('category_id', $specCategoryIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('slug');

            foreach ($attributes as $slug => $value) {
                if ($value === null || $value === '' || ! isset($definitions[$slug])) {
                    continue;
                }

                AdAttributeValue::create([
                    'ad_id' => $ad->id,
                    'category_attribute_definition_id' => $definitions[$slug]->id,
                    'value' => (string) $value,
                ]);
            }

            return $ad;
        });

        $ad->load([
            'images',
            'city.translations',
            'mainCategory.translations',
            'subCategory.translations',
            'attributeValues.definition.translations',
        ]);

        return sendResponse([
            'ad' => new AdDetailResource($ad),
        ], $lang ? 'تم حفظ المسودة بنجاح' : 'Draft saved successfully');
    }

    public function uploadImage(Request $request, $id)
    {
        $lang = $request->header('lang') === 'ar';
        $user = Auth::user();
        
        $ad = Ad::where('id', $id)->orWhere('public_id', $id)->first();
        if (!$ad || $ad->user_id !== $user->id) {
            return sendError($lang ? 'الإعلان غير موجود' : 'Ad not found', [], 404);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $path = uploader($request->file('image'), 'ads');
        $cleanPath = ltrim(str_replace('/storage/', '', $path), '/');
        
        $sortOrder = $ad->images()->max('sort_order') + 1;

        AdImage::create([
            'ad_id' => $ad->id,
            'path' => $cleanPath,
            'sort_order' => $sortOrder,
        ]);

        if (!$ad->cover_image) {
            $ad->update(['cover_image' => $cleanPath]);
        }

        return sendResponse(['path' => $path], $lang ? 'تم رفع الصورة' : 'Image uploaded');
    }

    public function publishDraft(Request $request, $id)
    {
        $lang = $request->header('lang') === 'ar';
        $user = Auth::user();
        
        $ad = Ad::where('id', $id)->orWhere('public_id', $id)->first();
        if (!$ad || $ad->user_id !== $user->id) {
            return sendError($lang ? 'الإعلان غير موجود' : 'Ad not found', [], 404);
        }

        if ($ad->images()->count() === 0) {
            return sendError($lang ? 'يجب رفع صورة واحدة على الأقل' : 'At least one image is required', [], 422);
        }

        $publication = $this->startPublication($ad, $request->input('coupon_code'));
        if (isset($publication['coupon_error'])) {
            return sendError('The coupon code could not be applied.', ['coupon_code' => $publication['coupon_error']], 422);
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
            $lang ? 'تم نشر الإعلان بنجاح' : 'Ad published successfully'
        );
    }

    public function completeStripePayment(Request $request, string $id, ListingPaymentService $listings)
    {
        $ad = Ad::query()->where('user_id', Auth::id())->where(fn ($query) => $query->where('id', $id)->orWhere('public_id', $id))->first();
        if (! $ad || ! $ad->stripe_payment_intent_id) {
            return sendError('Payment for this ad was not found.', [], 404);
        }

        $intent = app(StripeService::class)->paymentIntent($ad->stripe_payment_intent_id);
        if (($intent['status'] ?? null) !== 'succeeded') {
            return sendError('The Stripe payment has not succeeded yet.', [], 422);
        }

        $ad = $listings->publishPaidListing($intent['id'], (int) $intent['amount_received'], (string) $intent['currency']);
        return sendResponse(['ad' => new AdDetailResource($ad)], 'Payment verified and ad published successfully');
    }
}
