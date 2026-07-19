<?php

namespace Database\Seeders;

use App\Models\CategoryAttributeDefinition;
use App\Models\CategoryAttributeDefinitionTranslation;
use App\Models\CategoryTranslation;
use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class CategoryAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $langs = Language::all()->keyBy('code');
        if ($langs->isEmpty()) {
            return;
        }

        foreach ($this->map() as $categoryName => $definitions) {
            $categoryId = $this->mainCategoryIdByName($categoryName);
            if (! $categoryId) {
                continue;
            }

            $sort = 1;
            foreach ($definitions as $def) {
                $this->ensureDefinition($langs, $categoryId, $def, $sort);
                $sort++;
            }

            $this->pruneRemovedDefinitions($categoryId, array_column($definitions, 'slug'));
        }
    }

    /**
     * الحالة تختلف من قسم لآخر حسب المواصفات، لذلك لكل قسم قائمته الخاصة.
     */
    private function condition(array $options): array
    {
        return [
            'slug' => 'condition',
            'label' => ['en' => 'Condition', 'ar' => 'الحالة'],
            'options' => $options,
            'type' => 'select',
        ];
    }

    private function collectionOrDelivery(): array
    {
        return [
            'slug' => 'collection_or_delivery',
            'label' => ['en' => 'Collection or delivery', 'ar' => 'استلام أو توصيل'],
            'options' => ['Collection', 'Delivery', 'Both'],
            'type' => 'select',
        ];
    }

    private function map(): array
    {
        return [
            'Accommodation' => [
                ['slug' => 'contract_type', 'label' => ['en' => 'Contract type', 'ar' => 'نوع العقد'], 'options' => ['Short-term', 'Long-term'], 'type' => 'select'],
                ['slug' => 'property_type', 'label' => ['en' => 'Property type', 'ar' => 'نوع العقار'], 'options' => ['Flat', 'House', 'Studio', 'Student accommodation', 'Other'], 'type' => 'select'],
                ['slug' => 'bedrooms', 'label' => ['en' => 'Bedrooms', 'ar' => 'غرف النوم'], 'options' => ['1', '2', '3', '4+'], 'type' => 'select'],
                ['slug' => 'bathrooms', 'label' => ['en' => 'Bathrooms', 'ar' => 'الحمامات'], 'options' => ['1', '2', '3+'], 'type' => 'select'],
                ['slug' => 'payment_term', 'label' => ['en' => 'Payment term', 'ar' => 'مدة الدفع'], 'options' => ['Per week', 'Per month'], 'type' => 'select'],
                ['slug' => 'furnishing', 'label' => ['en' => 'Furnishing', 'ar' => 'الفرش'], 'options' => ['Furnished', 'Unfurnished', 'Part-furnished'], 'type' => 'select'],
                ['slug' => 'bills_included', 'label' => ['en' => 'Bills included', 'ar' => 'الفواتير مشمولة'], 'options' => ['Yes', 'No'], 'type' => 'select'],
                ['slug' => 'available_from', 'label' => ['en' => 'Available from', 'ar' => 'متاح ابتداءً من'], 'options' => [], 'type' => 'date'],
                ['slug' => 'features', 'label' => ['en' => 'Features', 'ar' => 'المميزات'], 'options' => ['Parking', 'Garden', 'Balcony'], 'type' => 'multiselect'],
            ],
            'Cars' => [
                ['slug' => 'listing_type', 'label' => ['en' => 'Listing type', 'ar' => 'نوع الإعلان'], 'options' => ['Car', 'Parts'], 'type' => 'select'],
                ['slug' => 'make', 'label' => ['en' => 'Make', 'ar' => 'الماركة'], 'options' => ['Toyota', 'BMW', 'Honda', 'Ford', 'Volkswagen', 'Audi', 'Mercedes', 'Nissan', 'Other'], 'type' => 'select'],
                ['slug' => 'model', 'label' => ['en' => 'Model', 'ar' => 'الموديل'], 'options' => [], 'type' => 'string'],
                ['slug' => 'year', 'label' => ['en' => 'Year', 'ar' => 'سنة الصنع'], 'options' => [], 'type' => 'number'],
                ['slug' => 'mileage', 'label' => ['en' => 'Mileage', 'ar' => 'المسافة المقطوعة'], 'options' => [], 'type' => 'number'],
                ['slug' => 'fuel_type', 'label' => ['en' => 'Fuel type', 'ar' => 'نوع الوقود'], 'options' => ['Petrol', 'Diesel', 'Hybrid', 'Electric', 'Other'], 'type' => 'select'],
                ['slug' => 'transmission', 'label' => ['en' => 'Transmission', 'ar' => 'ناقل الحركة'], 'options' => ['Manual', 'Automatic', 'Other'], 'type' => 'select'],
                ['slug' => 'body_type', 'label' => ['en' => 'Body type', 'ar' => 'نوع الهيكل'], 'options' => ['Hatchback', 'Saloon', 'SUV', 'Estate', 'Coupe', 'Convertible', 'Pickup', 'Other'], 'type' => 'select'],
                ['slug' => 'engine_size', 'label' => ['en' => 'Engine size', 'ar' => 'سعة المحرك'], 'options' => [], 'type' => 'number'],
                ['slug' => 'seats', 'label' => ['en' => 'Seats', 'ar' => 'عدد المقاعد'], 'options' => ['2', '4', '5', '7+'], 'type' => 'select'],
                ['slug' => 'colour', 'label' => ['en' => 'Colour', 'ar' => 'اللون'], 'options' => [], 'type' => 'string'],
            ],
            'Furniture & Home' => [
                ['slug' => 'item_type', 'label' => ['en' => 'Item type', 'ar' => 'نوع القطعة'], 'options' => ['Beds', 'Desks', 'Chairs', 'Sofas', 'Storage', 'Kitchen items', 'Other'], 'type' => 'select'],
                $this->condition(['New', 'Like new', 'Used']),
                $this->collectionOrDelivery(),
            ],
            'Electronics' => [
                ['slug' => 'item_type', 'label' => ['en' => 'Item type', 'ar' => 'النوع'], 'options' => ['Laptop', 'Mobile phone', 'Tablet', 'Accessories', 'Other'], 'type' => 'select'],
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'options' => ['Apple', 'Samsung', 'Dell', 'HP', 'Lenovo', 'Huawei', 'Other'], 'type' => 'select'],
                $this->condition(['New', 'Used', 'Refurbished']),
                ['slug' => 'warranty', 'label' => ['en' => 'Warranty', 'ar' => 'الضمان'], 'options' => ['Yes', 'No'], 'type' => 'select'],
                // المواصفات تفصل بين تخزين الهواتف/التابلت واللابتوب، والاتحاد بينهما مستخدم هنا
                // لأن الحقول مرتبطة بالقسم الرئيسي فقط. الفلترة حسب item_type تتم في الواجهة.
                ['slug' => 'storage', 'label' => ['en' => 'Storage', 'ar' => 'التخزين'], 'options' => ['64GB', '128GB', '256GB', '512GB', '1TB+'], 'type' => 'select'],
            ],
            'Appliances' => [
                ['slug' => 'appliance_type', 'label' => ['en' => 'Appliance type', 'ar' => 'نوع الجهاز'], 'options' => ['Fridge', 'Washing machine', 'Microwave', 'Heaters & fans', 'Other'], 'type' => 'select'],
                $this->condition(['New', 'Used']),
                $this->collectionOrDelivery(),
            ],
            'Bikes' => [
                ['slug' => 'bike_type', 'label' => ['en' => 'Type', 'ar' => 'النوع'], 'options' => ['Bicycle', 'Electric bike', 'Motorbike', 'Scooter', 'Other'], 'type' => 'select'],
                $this->condition(['New', 'Used']),
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'options' => [], 'type' => 'string'],
                ['slug' => 'electric', 'label' => ['en' => 'Electric', 'ar' => 'كهربائية'], 'options' => ['Yes', 'No'], 'type' => 'select'],
            ],
            'Books & Study Materials' => [
                ['slug' => 'book_type', 'label' => ['en' => 'Type', 'ar' => 'النوع'], 'options' => ['Textbook', 'Notes & revision materials', 'Stationery', 'Other'], 'type' => 'select'],
                ['slug' => 'subject_course', 'label' => ['en' => 'Subject or course', 'ar' => 'المادة أو المقرر'], 'options' => [], 'type' => 'string'],
                $this->condition(['New', 'Used']),
                ['slug' => 'format', 'label' => ['en' => 'Format', 'ar' => 'الصيغة'], 'options' => ['Physical', 'Digital notes'], 'type' => 'select'],
            ],
            'Fashion & Personal Items' => [
                ['slug' => 'item_type', 'label' => ['en' => 'Type', 'ar' => 'النوع'], 'options' => ['Clothing', 'Shoes', 'Bags & accessories', 'Other'], 'type' => 'select'],
                ['slug' => 'gender', 'label' => ['en' => 'Gender', 'ar' => 'الفئة'], 'options' => ['Men', 'Women', 'Unisex'], 'type' => 'select'],
                ['slug' => 'size', 'label' => ['en' => 'Size', 'ar' => 'المقاس'], 'options' => [], 'type' => 'string'],
                $this->condition(['New', 'Used']),
            ],
            'Services' => [
                ['slug' => 'service_type', 'label' => ['en' => 'Service type', 'ar' => 'نوع الخدمة'], 'options' => ['Tutoring', 'Moving help', 'Cleaning', 'IT/tech help', 'Freelance', 'Other'], 'type' => 'select'],
                ['slug' => 'delivery_mode', 'label' => ['en' => 'Delivery mode', 'ar' => 'طريقة التقديم'], 'options' => ['In-person', 'Online'], 'type' => 'select'],
                ['slug' => 'price_type', 'label' => ['en' => 'Price type', 'ar' => 'نوع السعر'], 'options' => ['Fixed', 'Hourly'], 'type' => 'select'],
                ['slug' => 'availability', 'label' => ['en' => 'Availability', 'ar' => 'أوقات التوفر'], 'options' => ['Weekdays', 'Weekends', 'Evenings'], 'type' => 'multiselect'],
            ],
            'Others' => [
                $this->condition(['New', 'Used']),
            ],
        ];
    }

    private function mainCategoryIdByName(string $englishName): ?int
    {
        return CategoryTranslation::query()
            ->where('name', $englishName)
            ->whereHas('category', fn ($q) => $q->whereNull('parent_id'))
            ->value('category_id');
    }

    /**
     * تحذف التعريفات التي لم تعد ضمن المواصفات فقط، بدل تفريغ الجدول بالكامل
     * حتى لا تُفقد قيم الإعلانات القائمة.
     */
    private function pruneRemovedDefinitions(int $categoryId, array $keptSlugs): void
    {
        CategoryAttributeDefinition::query()
            ->where('category_id', $categoryId)
            ->whereNotIn('slug', $keptSlugs)
            ->delete();
    }

    private function ensureDefinition(
        Collection $langs,
        int $categoryId,
        array $def,
        int $sort
    ): void {
        $type = $def['type'] ?? 'select';

        $options = [];
        if (in_array($type, ['select', 'multiselect'], true)) {
            $options = array_map(
                fn ($opt) => ['value' => $opt, 'label' => $opt],
                $def['options'] ?? []
            );
        }

        $definition = CategoryAttributeDefinition::updateOrCreate(
            ['category_id' => $categoryId, 'slug' => $def['slug']],
            [
                'input_type' => $type,
                'options' => $options,
                'sort_order' => $sort,
                'is_required' => false,
                'is_active' => true,
            ]
        );

        foreach (['en', 'ar'] as $code) {
            $lang = $langs->get($code);
            if ($lang && isset($def['label'][$code])) {
                CategoryAttributeDefinitionTranslation::updateOrCreate(
                    [
                        'category_attribute_definition_id' => $definition->id,
                        'language_id' => $lang->id,
                    ],
                    ['label' => $def['label'][$code]]
                );
            }
        }
    }
}
