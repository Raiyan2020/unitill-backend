<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\CategoryAttributeDefinition;
use App\Support\AdAvailabilityRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
                'is_negotiable' => filter_var(
                    $this->input('is_negotiable'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ) ?? false,
            ]);
        }
    }

    public function rules(): array
    {
        return array_merge([
            'main_category_id' => ['required', 'integer', 'exists:categories,id'],
            'sub_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'license_plate' => ['nullable', 'string', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z]{3}$/', 'max:7'],
            'description' => ['required', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'postcode' => ['nullable', 'string', 'regex:/^([A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2}|GIR ?0AA)$/i'],
            'region' => ['nullable', 'string', 'max:191'],
            'location_name' => ['nullable', 'string', 'max:191'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_negotiable' => ['nullable', 'boolean'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    foreach (is_array($value) ? $value : [$value] as $item) {
                        if (! is_scalar($item) || mb_strlen((string) $item) > 1000) {
                            $fail("The {$attribute} field must contain text values no longer than 1000 characters.");

                            return;
                        }
                    }
                },
            ],
        ], AdAvailabilityRules::rules());
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $mainCategoryId = (int) $this->input('main_category_id');
            $mainCategory = Category::query()
                ->whereNull('parent_id')
                ->where('status', 'active')
                ->find($mainCategoryId);

            if (! $mainCategory) {
                $validator->errors()->add('main_category_id', __('api.ad_form.invalid_main_category'));

                return;
            }

            if ($this->filled('sub_category_id') && ! Category::query()
                ->where('parent_id', $mainCategoryId)
                ->where('status', 'active')
                ->whereKey($this->input('sub_category_id'))
                ->exists()) {
                $validator->errors()->add('sub_category_id', __('api.ad_form.sub_not_in_main'));

                return;
            }

            $definitionCategoryIds = array_values(array_filter([
                $mainCategoryId,
                (int) $this->input('sub_category_id'),
            ]));
            $attributes = (array) $this->input('attributes', []);

            CategoryAttributeDefinition::query()
                ->whereIn('category_id', $definitionCategoryIds)
                ->where('is_active', true)
                ->where('is_required', true)
                ->with('translations')
                ->get()
                ->each(function (CategoryAttributeDefinition $definition) use ($attributes, $validator): void {
                    $value = $attributes[$definition->slug] ?? null;
                    if ($value === null || $value === '' || $value === []) {
                        $validator->errors()->add(
                            "attributes.{$definition->slug}",
                            __('api.ad_form.attribute_required', [
                                'field' => $definition->labelForLanguageCode(app()->getLocale()),
                            ])
                        );
                    }
                });
        });
    }

    public function messages(): array
    {
        return AdAvailabilityRules::messages();
    }
}
