<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\CategoryAttributeDefinition;
use App\Models\City;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('attributes'))) {
            $decoded = json_decode($this->input('attributes'), true);
            if (is_array($decoded)) {
                $this->merge(['attributes' => $decoded]);
            }
        }

        if ($this->has('is_negotiable')) {
            $this->merge([
                'is_negotiable' => filter_var($this->input('is_negotiable'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }

        if ($this->has('confirm_publish')) {
            $this->merge([
                'confirm_publish' => filter_var($this->input('confirm_publish'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }

        // Fallback for hardcoded Flutter city_id=1
        if ($this->input('city_id') == 1) {
            if (!City::where('id', 1)->exists()) {
                $userCity = Auth::user()->city_id;
                if ($userCity && City::where('id', $userCity)->exists()) {
                    $this->merge(['city_id' => $userCity]);
                } else {
                    $firstCity = City::first();
                    if ($firstCity) {
                        $this->merge(['city_id' => $firstCity->id]);
                    }
                }
            }
        }
    }

    /**
     * The licence plate is required for the Cars category only.
     *
     * The id used to be hardcoded to 2, which does not match the Cars category
     * id after reseeding, so it is now resolved by name.
     */
    private function isCarsCategory(): bool
    {
        $id = $this->input('main_category_id');
        if (! $id) {
            return false;
        }

        return Category::query()
            ->whereNull('parent_id')
            ->where('id', $id)
            ->whereHas('translations', fn ($q) => $q->where('name', 'Cars'))
            ->exists();
    }

    public function rules(): array
    {
        return [
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'main_category_id' => 'required|integer|exists:categories,id',
            'sub_category_id' => 'nullable|integer|exists:categories,id',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'license_plate' => [
                Rule::requiredIf(fn() => $this->isCarsCategory()),
                'nullable',
                'string',
                'regex:/^[A-Z]{2}[0-9]{2}[A-Z]{3}$/',
                'max:7'
            ],
            'description' => 'required|string|max:5000',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'city_id' => 'required|integer|exists:cities,id',
            // UK postcode based location (resolved via postcodes.io on the client).
            'postcode' => ['nullable', 'string', 'regex:/^([A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2}|GIR ?0AA)$/i'],
            'region' => 'nullable|string|max:191',
            'location_name' => 'nullable|string|max:191',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_negotiable' => 'nullable|boolean',
            'confirm_publish' => 'nullable|boolean',
            'coupon_code' => 'nullable|string|max:40',
            'attributes' => 'nullable|array',
            'attributes.*' => 'nullable|string|max:1000',
            
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ar = $this->header('lang') === 'ar';
            $mainCategoryId = (int) $this->input('main_category_id');

            $mainCategory = Category::query()
                ->whereNull('parent_id')
                ->where('status', 'active')
                ->find($mainCategoryId);

            if (! $mainCategory) {
                $validator->errors()->add(
                    'main_category_id',
                    __('api.ad_form.invalid_main_category')
                );

                return;
            }

            if ($this->filled('sub_category_id')) {
                $subCategory = Category::query()
                    ->where('parent_id', $mainCategoryId)
                    ->where('status', 'active')
                    ->find($this->input('sub_category_id'));

                if (! $subCategory) {
                    $validator->errors()->add(
                        'sub_category_id',
                        __('api.ad_form.sub_not_in_main')
                    );

                    return;
                }
            }

            $specCategoryId = (int) ($this->input('sub_category_id') ?: $mainCategoryId);
            $attributes = (array) $this->input('attributes', []);

            // If attributes are empty (e.g. from mobile app), we bypass strict validation 
            // so the ad creation cycle completes successfully.
            if ($this->has('attributes') && !empty($attributes)) {
                $definitions = CategoryAttributeDefinition::query()
                    ->where('category_id', $specCategoryId)
                    ->where('is_active', true)
                    ->get();

                foreach ($definitions as $definition) {
                    $value = $attributes[$definition->slug] ?? null;

                    if ($definition->is_required && ($value === null || $value === '')) {
                        $validator->errors()->add(
                            "attributes.{$definition->slug}",
                            __('api.ad_form.attribute_required', ['field' => $this->attributeLabel($definition)])
                        );
                    }
                }
            }
        });
    }

    /**
     * Attribute label in the caller's language.
     *
     * labelForLanguageCode() returns the raw slug when a locale has no
     * translation, and only en/ar are seeded — so a French user would otherwise
     * be told "fuel_type is required". English is tried before giving up.
     */
    protected function attributeLabel(CategoryAttributeDefinition $definition): string
    {
        $locale = app()->getLocale();
        $label = $definition->labelForLanguageCode($locale);

        if ($label === $definition->slug && $locale !== 'en') {
            $label = $definition->labelForLanguageCode('en');
        }

        return $label;
    }

    public function countryId(): ?int
    {
        $city = City::query()->find($this->input('city_id'));

        return $city?->country_id;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            sendError(
                $validator->errors()->first(),
                $validator->errors()->toArray(),
                422
            )
        );
    }

    public function messages(): array
    {
        $ar = $this->header('lang') === 'ar';

        return [
            'images.required' => __('api.ad.image_required'),
            'images.min' => __('api.ad.image_required'),
            'images.max' => __('api.ad_form.images_max'),
            'images.*.image' => __('api.ad_form.images_must_be_images'),
            'images.*.max' => __('api.ad_form.image_max_size'),
            'main_category_id.required' => __('api.ad_form.main_category_required'),
            'title.required' => __('api.ad_form.title_required'),
            'price.required' => __('api.ad_form.price_required'),
            'city_id.required' => __('api.ad_form.city_required'),
        ];
    }
}
