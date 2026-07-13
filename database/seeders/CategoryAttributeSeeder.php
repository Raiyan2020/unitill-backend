<?php

namespace Database\Seeders;

use App\Models\CategoryAttributeDefinition;
use App\Models\CategoryAttributeDefinitionTranslation;
use App\Models\CategoryTranslation;
use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seeds the per-category filter/attribute definitions (business notes'
 * "Filters" section). Idempotent via the (category_id, slug) unique key.
 * These power both the browse filters and the dynamic post-ad attribute inputs.
 */
class CategoryAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $langs = Language::all()->keyBy('code');
        if ($langs->isEmpty()) {
            return;
        }

        $condition = [
            'slug' => 'condition',
            'label' => ['en' => 'Condition', 'ar' => 'الحالة'],
            'options' => ['New', 'Like new', 'Used'],
        ];

        $map = [
            'Accommodation' => [
                ['slug' => 'property_type', 'label' => ['en' => 'Property type', 'ar' => 'نوع العقار'], 'options' => ['Flat', 'House', 'Studio', 'Student accommodation', 'Other']],
                ['slug' => 'bedrooms', 'label' => ['en' => 'Bedrooms', 'ar' => 'غرف النوم'], 'options' => ['1', '2', '3', '4+']],
                ['slug' => 'bathrooms', 'label' => ['en' => 'Bathrooms', 'ar' => 'الحمامات'], 'options' => ['1', '2', '3+']],
                ['slug' => 'furnishing', 'label' => ['en' => 'Furnishing', 'ar' => 'الفرش'], 'options' => ['Furnished', 'Unfurnished', 'Part-furnished']],
                ['slug' => 'payment_term', 'label' => ['en' => 'Payment term', 'ar' => 'مدة الدفع'], 'options' => ['Per week', 'Per month']],
            ],
            'Cars' => [
                ['slug' => 'make', 'label' => ['en' => 'Make', 'ar' => 'الماركة'], 'options' => ['Toyota', 'BMW', 'Honda', 'Ford', 'Volkswagen', 'Audi', 'Mercedes', 'Nissan', 'Other']],
                ['slug' => 'fuel_type', 'label' => ['en' => 'Fuel type', 'ar' => 'نوع الوقود'], 'options' => ['Petrol', 'Diesel', 'Hybrid', 'Electric', 'Other']],
                ['slug' => 'transmission', 'label' => ['en' => 'Transmission', 'ar' => 'ناقل الحركة'], 'options' => ['Manual', 'Automatic', 'Other']],
                ['slug' => 'body_type', 'label' => ['en' => 'Body type', 'ar' => 'نوع الهيكل'], 'options' => ['Hatchback', 'Saloon', 'SUV', 'Estate', 'Coupe', 'Convertible', 'Pickup', 'Other']],
            ],
            'Furniture & Home' => [
                ['slug' => 'item_type', 'label' => ['en' => 'Item type', 'ar' => 'نوع القطعة'], 'options' => ['Beds', 'Desks', 'Chairs', 'Sofas', 'Storage', 'Kitchen items', 'Other']],
                $condition,
            ],
            'Electronics' => [
                ['slug' => 'item_type', 'label' => ['en' => 'Item type', 'ar' => 'النوع'], 'options' => ['Laptop', 'Mobile phone', 'Tablet', 'Accessories', 'Other']],
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'options' => ['Apple', 'Samsung', 'Dell', 'HP', 'Lenovo', 'Huawei', 'Other']],
                $condition,
                ['slug' => 'storage', 'label' => ['en' => 'Storage', 'ar' => 'التخزين'], 'options' => ['64GB', '128GB', '256GB', '512GB', '1TB+']],
            ],
            'Appliances' => [
                ['slug' => 'appliance_type', 'label' => ['en' => 'Appliance type', 'ar' => 'نوع الجهاز'], 'options' => ['Fridge', 'Washing machine', 'Microwave', 'Heaters and fans', 'Other']],
                $condition,
            ],
            'Bikes' => [
                ['slug' => 'bike_type', 'label' => ['en' => 'Type', 'ar' => 'النوع'], 'options' => ['Bicycle', 'Electric bike', 'Motorbike', 'Scooter', 'Other']],
                $condition,
            ],
            'Books & Study Materials' => [
                ['slug' => 'book_type', 'label' => ['en' => 'Type', 'ar' => 'النوع'], 'options' => ['Textbook', 'Notes and revision materials', 'Stationery', 'Other']],
                $condition,
            ],
            'Fashion & Personal Items' => [
                ['slug' => 'item_type', 'label' => ['en' => 'Type', 'ar' => 'النوع'], 'options' => ['Clothing', 'Shoes', 'Bags and accessories', 'Other']],
                ['slug' => 'gender', 'label' => ['en' => 'Gender', 'ar' => 'الفئة'], 'options' => ['Men', 'Women', 'Unisex']],
                $condition,
            ],
            'Services' => [
                ['slug' => 'service_type', 'label' => ['en' => 'Service type', 'ar' => 'نوع الخدمة'], 'options' => ['Tutoring', 'Moving help', 'Cleaning', 'IT or tech help', 'Freelance', 'Other']],
                ['slug' => 'price_type', 'label' => ['en' => 'Price type', 'ar' => 'نوع السعر'], 'options' => ['Fixed', 'Hourly']],
            ],
            'Others' => [
                $condition,
            ],
        ];

        foreach ($map as $categoryName => $definitions) {
            $categoryId = $this->mainCategoryIdByName($categoryName);
            if (! $categoryId) {
                continue;
            }

            $sort = 1;
            foreach ($definitions as $def) {
                $this->ensureDefinition($langs, $categoryId, $def, $sort);
                $sort++;
            }
        }
    }

    private function mainCategoryIdByName(string $englishName): ?int
    {
        return CategoryTranslation::query()
            ->where('name', $englishName)
            ->whereHas('category', fn ($q) => $q->whereNull('parent_id'))
            ->value('category_id');
    }

    private function ensureDefinition(
        Collection $langs,
        int $categoryId,
        array $def,
        int $sort
    ): void {
        $options = array_map(
            fn ($opt) => ['value' => $opt, 'label' => $opt],
            $def['options']
        );

        $definition = CategoryAttributeDefinition::updateOrCreate(
            ['category_id' => $categoryId, 'slug' => $def['slug']],
            [
                'input_type' => 'select',
                'options' => $options,
                'sort_order' => $sort,
                'is_required' => false,
                'is_active' => true,
            ]
        );

        foreach (['en' => $def['label']['en'], 'ar' => $def['label']['ar']] as $code => $label) {
            $lang = $langs->get($code);
            if ($lang) {
                CategoryAttributeDefinitionTranslation::updateOrCreate(
                    [
                        'category_attribute_definition_id' => $definition->id,
                        'language_id' => $lang->id,
                    ],
                    ['label' => $label]
                );
            }
        }
    }
}
