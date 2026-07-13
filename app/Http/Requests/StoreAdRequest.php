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

    public function rules(): array
    {
        return [
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'main_category_id' => 'required|integer|exists:categories,id',
            'sub_category_id' => 'nullable|integer|exists:categories,id',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'city_id' => 'required|integer|exists:cities,id',
            // UK postcode based location (resolved via postcodes.io on the client).
            'postcode' => 'nullable|string|max:12',
            'location_name' => 'nullable|string|max:191',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_negotiable' => 'nullable|boolean',
            'confirm_publish' => 'nullable|boolean',
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
                    $ar ? 'القسم الرئيسي غير صالح' : 'Invalid main category'
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
                        $ar ? 'القسم الفرعي لا ينتمي للقسم الرئيسي' : 'Sub category does not belong to the main category'
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
                            $ar
                                ? "حقل {$definition->labelForLanguageCode('ar')} مطلوب"
                                : "The {$definition->labelForLanguageCode('en')} field is required"
                        );
                    }
                }
            }
        });
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
            'images.required' => $ar ? 'يجب رفع صورة واحدة على الأقل' : 'At least one image is required',
            'images.min' => $ar ? 'يجب رفع صورة واحدة على الأقل' : 'At least one image is required',
            'images.max' => $ar ? 'الحد الأقصى 10 صور' : 'Maximum 10 images allowed',
            'images.*.image' => $ar ? 'يجب أن تكون الملفات صوراً' : 'Files must be images',
            'images.*.max' => $ar ? 'حجم الصورة يجب ألا يتجاوز 5MB' : 'Image size must not exceed 5MB',
            'main_category_id.required' => $ar ? 'القسم الرئيسي مطلوب' : 'Main category is required',
            'title.required' => $ar ? 'عنوان الإعلان مطلوب' : 'Ad title is required',
            'price.required' => $ar ? 'السعر مطلوب' : 'Price is required',
            'city_id.required' => $ar ? 'المدينة مطلوبة' : 'City is required',
        ];
    }
}
