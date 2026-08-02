<?php

namespace Database\Seeders;

use App\Models\CategoryAttributeDefinition;
use App\Models\CategoryAttributeDefinitionTranslation;
use App\Models\CategoryTranslation;
use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seeds the per-category filter/attribute definitions (checklist pages 9 & 11 +
 * the client Notes). Each definition declares how it renders in the filter panel
 * (filter_control) and the post-ad form (post_control), plus config for ranges.
 * Idempotent via the (category_id, slug) unique key.
 */
class CategoryAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $langs = Language::all()->keyBy('code');
        if ($langs->isEmpty()) {
            return;
        }

        // Condition now includes "Refurbished" (checklist requirement).
        $condition = [
            'slug' => 'condition',
            'label' => ['en' => 'Condition', 'ar' => 'الحالة'],
            'options' => ['New', 'Like new', 'Refurbished', 'Used'],
        ];
        $collection = [
            'slug' => 'collection_delivery',
            'label' => ['en' => 'Collection or delivery', 'ar' => 'الاستلام أو التوصيل'],
            'options' => ['Collection', 'Delivery', 'Both'],
        ];

        $map = [
            'Accommodation' => [
                ['slug' => 'contract_type', 'label' => ['en' => 'Contract type', 'ar' => 'نوع العقد'], 'options' => ['Short-term', 'Long-term']],
                ['slug' => 'property_type', 'label' => ['en' => 'Property type', 'ar' => 'نوع العقار'], 'options' => ['Flat', 'House', 'Studio', 'Student accommodation', 'Shared room', 'Other']],
                ['slug' => 'bedrooms', 'label' => ['en' => 'Bedrooms', 'ar' => 'غرف النوم'], 'options' => ['1', '2', '3', '4+']],
                ['slug' => 'bathrooms', 'label' => ['en' => 'Bathrooms', 'ar' => 'الحمامات'], 'options' => ['1', '2', '3+']],
                ['slug' => 'payment_term', 'label' => ['en' => 'Payment term', 'ar' => 'مدة الدفع'], 'options' => ['Per week', 'Per month']],
                ['slug' => 'furnishing', 'label' => ['en' => 'Furnishing', 'ar' => 'الفرش'], 'options' => ['Furnished', 'Unfurnished', 'Part-furnished']],
                ['slug' => 'bills_included', 'label' => ['en' => 'Bills included', 'ar' => 'الفواتير مشمولة'], 'input_type' => 'boolean', 'filter_control' => 'toggle', 'post_control' => 'toggle'],
                ['slug' => 'availability_from', 'label' => ['en' => 'Available from', 'ar' => 'متاح من'], 'input_type' => 'string', 'filter_control' => 'date', 'post_control' => 'date'],
                ['slug' => 'features', 'label' => ['en' => 'Features', 'ar' => 'المميزات'], 'filter_control' => 'multiselect', 'post_control' => 'multiselect', 'options' => ['Parking', 'Garden', 'Balcony']],
            ],
            'Cars' => [
                ['slug' => 'listing_type', 'label' => ['en' => 'Listing type', 'ar' => 'نوع الإعلان'], 'options' => ['Car', 'Parts']],
                ['slug' => 'make', 'label' => ['en' => 'Make', 'ar' => 'الماركة'], 'options' => ['Toyota', 'BMW', 'Honda', 'Ford', 'Volkswagen', 'Audi', 'Mercedes', 'Nissan', 'Vauxhall', 'Other']],
                ['slug' => 'model', 'label' => ['en' => 'Model', 'ar' => 'الموديل'], 'input_type' => 'string', 'post_control' => 'text', 'is_filterable' => false],
                ['slug' => 'year', 'label' => ['en' => 'Year', 'ar' => 'السنة'], 'input_type' => 'number', 'filter_control' => 'range', 'post_control' => 'number', 'config' => ['min' => 1990, 'max' => 2026, 'step' => 1]],
                ['slug' => 'mileage', 'label' => ['en' => 'Mileage', 'ar' => 'المسافة المقطوعة'], 'input_type' => 'number', 'filter_control' => 'range', 'post_control' => 'number', 'config' => ['min' => 0, 'max' => 300000, 'step' => 1000, 'unit' => 'mi']],
                ['slug' => 'fuel_type', 'label' => ['en' => 'Fuel type', 'ar' => 'نوع الوقود'], 'filter_control' => 'multiselect', 'post_control' => 'select', 'options' => ['Petrol', 'Diesel', 'Hybrid', 'Electric', 'Other']],
                ['slug' => 'transmission', 'label' => ['en' => 'Transmission', 'ar' => 'ناقل الحركة'], 'options' => ['Manual', 'Automatic', 'Other']],
                ['slug' => 'body_type', 'label' => ['en' => 'Body type', 'ar' => 'نوع الهيكل'], 'options' => ['Hatchback', 'Saloon', 'SUV', 'Estate', 'Coupe', 'Convertible', 'Pickup', 'Other']],
                ['slug' => 'engine_size', 'label' => ['en' => 'Engine size', 'ar' => 'سعة المحرك'], 'input_type' => 'number', 'filter_control' => 'range', 'post_control' => 'number', 'config' => ['min' => 0, 'max' => 8, 'step' => 0.1, 'unit' => 'L', 'decimals' => 1]],
                ['slug' => 'seats', 'label' => ['en' => 'Seats', 'ar' => 'المقاعد'], 'options' => ['2', '4', '5', '7+']],
                ['slug' => 'colour', 'label' => ['en' => 'Colour', 'ar' => 'اللون'], 'options' => ['Black', 'White', 'Silver', 'Grey', 'Blue', 'Red', 'Green', 'Other']],
                ['slug' => 'condition', 'label' => ['en' => 'Condition', 'ar' => 'الحالة'], 'options' => ['Excellent', 'Very good', 'Good']],
            ],
            'Furniture & Home' => [
                ['slug' => 'item_type', 'label' => ['en' => 'Item type', 'ar' => 'نوع القطعة'], 'options' => ['Beds & mattresses', 'Desks & chairs', 'Sofas & storage', 'Kitchen items', 'Other']],
                $condition,
                $collection,
            ],
            'Electronics' => [
                ['slug' => 'item_type', 'label' => ['en' => 'Item type', 'ar' => 'النوع'], 'options' => ['Laptop', 'Mobile phone', 'Tablet', 'Accessories', 'Other']],
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'options' => ['Apple', 'Samsung', 'Dell', 'HP', 'Lenovo', 'Huawei', 'Sony', 'Other']],
                $condition,
                ['slug' => 'storage', 'label' => ['en' => 'Storage', 'ar' => 'التخزين'], 'options' => ['64GB', '128GB', '256GB', '512GB', '1TB+']],
                ['slug' => 'warranty', 'label' => ['en' => 'Warranty', 'ar' => 'الضمان'], 'input_type' => 'boolean', 'filter_control' => 'toggle', 'post_control' => 'toggle'],
            ],
            'Appliances' => [
                ['slug' => 'appliance_type', 'label' => ['en' => 'Appliance type', 'ar' => 'نوع الجهاز'], 'options' => ['Fridge', 'Washing machine', 'Microwave', 'Heaters & fans', 'Other']],
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'input_type' => 'string', 'post_control' => 'text', 'is_filterable' => false],
                $condition,
                $collection,
            ],
            'Bikes' => [
                ['slug' => 'bike_type', 'label' => ['en' => 'Type', 'ar' => 'النوع'], 'options' => ['Bicycle', 'Electric bike', 'Motorbike', 'Scooter', 'Other']],
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'input_type' => 'string', 'post_control' => 'text', 'is_filterable' => false],
                $condition,
                ['slug' => 'electric', 'label' => ['en' => 'Electric', 'ar' => 'كهربائية'], 'input_type' => 'boolean', 'filter_control' => 'toggle', 'post_control' => 'toggle'],
            ],
            'Books & Study Materials' => [
                ['slug' => 'book_type', 'label' => ['en' => 'Type', 'ar' => 'النوع'], 'options' => ['Textbook', 'Notes & revision materials', 'Stationery', 'Other']],
                ['slug' => 'subject', 'label' => ['en' => 'Subject or course', 'ar' => 'المادة أو المقرر'], 'input_type' => 'string', 'post_control' => 'text', 'is_filterable' => false],
                $condition,
                ['slug' => 'format', 'label' => ['en' => 'Format', 'ar' => 'الصيغة'], 'options' => ['Physical', 'Digital notes']],
            ],
            'Fashion & Personal Items' => [
                ['slug' => 'item_type', 'label' => ['en' => 'Type', 'ar' => 'النوع'], 'options' => ['Clothing', 'Shoes', 'Bags & accessories', 'Other']],
                ['slug' => 'gender', 'label' => ['en' => 'Gender', 'ar' => 'الفئة'], 'options' => ['Men', 'Women', 'Unisex']],
                ['slug' => 'size', 'label' => ['en' => 'Size', 'ar' => 'المقاس'], 'input_type' => 'string', 'post_control' => 'text', 'is_filterable' => false],
                $condition,
            ],
            'Services' => [
                ['slug' => 'service_type', 'label' => ['en' => 'Service type', 'ar' => 'نوع الخدمة'], 'options' => ['Tutoring', 'Moving help', 'Cleaning', 'IT / tech help', 'Freelance', 'Other']],
                ['slug' => 'delivery_mode', 'label' => ['en' => 'Delivery mode', 'ar' => 'طريقة التقديم'], 'options' => ['In-person', 'Online']],
                ['slug' => 'price_type', 'label' => ['en' => 'Price type', 'ar' => 'نوع السعر'], 'options' => ['Fixed', 'Hourly']],
                ['slug' => 'availability', 'label' => ['en' => 'Availability', 'ar' => 'التوفر'], 'filter_control' => 'multiselect', 'post_control' => 'multiselect', 'options' => ['Weekdays', 'Weekends', 'Evenings']],
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
        $inputType = $def['input_type'] ?? 'select';
        $options = array_map(
            fn ($opt) => ['value' => $opt, 'label' => $opt],
            $def['options'] ?? []
        );

        $definition = CategoryAttributeDefinition::updateOrCreate(
            ['category_id' => $categoryId, 'slug' => $def['slug']],
            [
                'input_type' => $inputType,
                'filter_control' => $def['filter_control'] ?? null,
                'post_control' => $def['post_control'] ?? null,
                'options' => $options,
                'config' => $def['config'] ?? null,
                'sort_order' => $sort,
                'is_required' => $def['is_required'] ?? false,
                'is_filterable' => $def['is_filterable'] ?? true,
                'is_postable' => $def['is_postable'] ?? true,
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
