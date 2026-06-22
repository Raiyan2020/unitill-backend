<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdRequest;
use App\Http\Resources\AdDetailResource;
use App\Http\Resources\AdResource;
use App\Models\Ad;
use App\Models\AdAttributeValue;
use App\Models\AdImage;
use App\Models\CategoryAttributeDefinition;
use App\Support\AdFilters;
use App\Support\AdSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdController extends Controller
{
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
        AdSort::apply($query, $sort);

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

        $filterCategoryId = $subCategoryId ?: ($mainCategoryId ?: null);
        if ($filterCategoryId) {
            $response['filter_options'] = $this->filterOptionsForCategory((int) $filterCategoryId, $lang);
        }

        return sendResponse($response);
    }

    public function show(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';

        $ad = Ad::query()
            ->published()
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
                        ->withAvg('ratingsReceived as average_rating_received', 'score');
                },
            ])
            ->first();

        if (! $ad) {
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
        $specCategoryId = (int) ($data['sub_category_id'] ?? $data['main_category_id']);

        $ad = DB::transaction(function () use ($request, $user, $data, $confirmPublish, $attributes, $specCategoryId) {
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

            $publishedAt = $confirmPublish ? now() : null;
            $durationDays = (int) (setting('post_duration') ?? 30);

            $ad = Ad::create([
                'user_id' => $user->id,
                'public_id' => $publicId,
                'title' => $title,
                'subtitle' => $data['subtitle'] ?? null,
                'description' => $data['description'] ?? null,
                'country_id' => $request->countryId(),
                'city_id' => $data['city_id'],
                'main_category_id' => $data['main_category_id'],
                'sub_category_id' => $data['sub_category_id'] ?? null,
                'cover_image' => $coverPath,
                'price' => $data['price'],
                'currency' => strtoupper($data['currency'] ?? 'GBP'),
                'is_negotiable' => (bool) ($data['is_negotiable'] ?? false),
                'is_verified' => false,
                'slug' => Str::slug($title.'-'.$publicId),
                'status' => $confirmPublish ? 'published' : 'pending',
                'published_at' => $publishedAt,
                'expires_at' => $publishedAt ? $publishedAt->copy()->addDays($durationDays) : null,
            ]);

            foreach ($imagePaths as $row) {
                AdImage::create([
                    'ad_id' => $ad->id,
                    'path' => ltrim(str_replace('/storage/', '', $row['path']), '/'),
                    'sort_order' => $row['sort_order'],
                ]);
            }

            $definitions = CategoryAttributeDefinition::query()
                ->where('category_id', $specCategoryId)
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

        $listingFee = (float) (setting('post_price') ?? 0);

        return sendResponse([
            'ad' => new AdDetailResource($ad),
            'listing_fee' => $listingFee,
            'formatted_listing_fee' => '£'.number_format($listingFee, 2),
            'total_amount' => $confirmPublish ? $listingFee : 0,
            'formatted_total_amount' => '£'.number_format($confirmPublish ? $listingFee : 0, 2),
        ], $lang ? 'تم إنشاء الإعلان بنجاح' : 'Ad created successfully');
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
            'description' => 'nullable|string|max:5000',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'is_negotiable' => 'nullable|boolean',
            'attributes' => 'nullable|array',
            'attributes.*' => 'nullable|string|max:1000',
        ]);

        $attributes = (array) ($validated['attributes'] ?? []);
        $specCategoryId = (int) ($validated['sub_category_id'] ?? $validated['main_category_id']);

        $ad = DB::transaction(function () use ($request, $user, $validated, $attributes, $specCategoryId, $cityId) {
            $title = $validated['title'];
            $publicId = strtoupper(Str::random(10));

            $ad = Ad::create([
                'user_id' => $user->id,
                'public_id' => $publicId,
                'title' => $title,
                'subtitle' => $validated['subtitle'] ?? null,
                'description' => $validated['description'] ?? null,
                'country_id' => \App\Models\City::find($cityId)?->country_id ?? 1,
                'city_id' => $cityId,
                'main_category_id' => $validated['main_category_id'],
                'sub_category_id' => $validated['sub_category_id'] ?? null,
                'price' => $validated['price'],
                'currency' => strtoupper($validated['currency'] ?? 'GBP'),
                'is_negotiable' => (bool) ($validated['is_negotiable'] ?? false),
                'is_verified' => false,
                'slug' => Str::slug($title.'-'.$publicId),
                'status' => 'draft',
            ]);

            $definitions = CategoryAttributeDefinition::query()
                ->where('category_id', $specCategoryId)
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

        $publishedAt = now();
        $durationDays = (int) (setting('post_duration') ?? 30);

        $ad->update([
            'status' => 'pending', // or 'published' based on your workflow
            'published_at' => $publishedAt,
            'expires_at' => $publishedAt->copy()->addDays($durationDays),
        ]);

        $ad->load([
            'images',
            'city.translations',
            'mainCategory.translations',
            'subCategory.translations',
            'attributeValues.definition.translations',
        ]);

        $listingFee = (float) (setting('post_price') ?? 0);

        return sendResponse([
            'ad' => new AdDetailResource($ad),
            'listing_fee' => $listingFee,
            'formatted_listing_fee' => '£'.number_format($listingFee, 2),
            'total_amount' => $listingFee,
            'formatted_total_amount' => '£'.number_format($listingFee, 2),
        ], $lang ? 'تم نشر الإعلان بنجاح' : 'Ad published successfully');
    }
}
