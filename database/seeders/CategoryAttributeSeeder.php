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

        foreach ($this->map() as $categoryPath => $definitions) {
            $categoryId = $this->resolveCategoryId($categoryPath);
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
     * Condition options differ per category in the spec, so each passes its own list.
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

    private function map(): array
    {
        return [
            // Price, postcode, description and photos are columns on `ads`, so they
            // are deliberately absent here — seeding them as attributes too would
            // store every listing's price twice, free to disagree.
            'Accommodation' => [
                ['slug' => 'property_type', 'label' => ['en' => 'Property type', 'ar' => 'نوع العقار'], 'options' => ['Flat', 'Semi-detached', 'Detached', 'Studio', 'Student Accommodation', 'Shared Room', 'Other'], 'type' => 'select'],
                ['slug' => 'bedrooms', 'label' => ['en' => 'Bedrooms', 'ar' => 'غرف النوم'], 'options' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10+'], 'type' => 'select'],
                ['slug' => 'bathrooms', 'label' => ['en' => 'Bathrooms', 'ar' => 'الحمامات'], 'options' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10+'], 'type' => 'select'],
                ['slug' => 'furnishing', 'label' => ['en' => 'Furnished', 'ar' => 'الفرش'], 'options' => ['Furnished', 'Unfurnished', 'Part-furnished'], 'type' => 'select'],
                ['slug' => 'payment_term', 'label' => ['en' => 'Payment term', 'ar' => 'مدة الدفع'], 'options' => ['Per Month', 'Per Week'], 'type' => 'select'],
                ['slug' => 'available_from', 'label' => ['en' => 'Available from', 'ar' => 'متاح ابتداءً من'], 'options' => [], 'type' => 'date'],
                ['slug' => 'available_until', 'label' => ['en' => 'Available until', 'ar' => 'متاح حتى'], 'options' => [], 'type' => 'date'],
                // "Furnished" is omitted from this list on purpose: it is already the
                // dedicated `furnishing` field above, and a toggle that can contradict
                // a dropdown is a bug waiting to happen.
                ['slug' => 'features', 'label' => ['en' => 'Additional features', 'ar' => 'مميزات إضافية'], 'options' => ['Parking', 'Garden', 'Balcony', 'Bills Included'], 'type' => 'multiselect'],
            ],
            'Cars' => [
                // The registration number is the top-level `license_plate` column on
                // `ads` (it drives the DVLA lookup and needs masking on public ads),
                // so it is not duplicated as an attribute here.
                ['slug' => 'make', 'label' => ['en' => 'Make', 'ar' => 'الماركة'], 'options' => ['Toyota', 'BMW', 'Honda', 'Ford', 'Volkswagen', 'Audi', 'Mercedes', 'Nissan', 'Other'], 'type' => 'select'],
                ['slug' => 'model', 'label' => ['en' => 'Model', 'ar' => 'الموديل'], 'options' => [], 'type' => 'string'],
                ['slug' => 'year', 'label' => ['en' => 'Year', 'ar' => 'سنة الصنع'], 'options' => [], 'type' => 'number'],
                ['slug' => 'mileage', 'label' => ['en' => 'Mileage', 'ar' => 'المسافة المقطوعة'], 'options' => [], 'type' => 'number'],
                ['slug' => 'transmission', 'label' => ['en' => 'Transmission', 'ar' => 'ناقل الحركة'], 'options' => ['Manual', 'Automatic'], 'type' => 'select'],
                ['slug' => 'fuel_type', 'label' => ['en' => 'Fuel type', 'ar' => 'نوع الوقود'], 'options' => ['Petrol', 'Diesel', 'Hybrid', 'Electric', 'Other'], 'type' => 'select'],
                $this->condition(['Excellent', 'Very Good', 'Good']),
            ],
            'Furniture & Home' => [
                ['slug' => 'furniture_type', 'label' => ['en' => 'Furniture type', 'ar' => 'نوع الأثاث'], 'options' => ['Sofa', 'Bed', 'Wardrobe', 'Table', 'Chair', 'Desk', 'Dining Set', 'Other'], 'type' => 'select'],
                $this->condition(['New', 'Like New', 'Used']),
            ],
            'Appliances' => [
                ['slug' => 'appliance_type', 'label' => ['en' => 'Appliance type', 'ar' => 'نوع الجهاز'], 'options' => ['Refrigerator', 'Washing Machine', 'Dryer', 'Microwave', 'Vacuum', 'Iron', 'Air Conditioner', 'Heater', 'Other'], 'type' => 'select'],
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'options' => [], 'type' => 'string'],
                $this->condition(['New', 'Like New', 'Used']),
            ],
            // Electronics keeps a broad set of its own, used when a seller picks the
            // main category or the Accessories / Others subcategories; the four
            // subcategories below override it with their specific fields.
            'Electronics' => [
                ['slug' => 'item_type', 'label' => ['en' => 'Item type', 'ar' => 'النوع'], 'options' => ['Laptop', 'Mobile phone', 'Tablet', 'Computer', 'Home electronics', 'Accessories', 'Other'], 'type' => 'select'],
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'options' => ['Apple', 'Samsung', 'Dell', 'HP', 'Lenovo', 'Huawei', 'Other'], 'type' => 'select'],
                $this->condition(['New', 'Like New', 'Used']),
            ],
            'Electronics > Home Electronics' => [
                ['slug' => 'product_type', 'label' => ['en' => 'Product type', 'ar' => 'نوع المنتج'], 'options' => ['TV', 'Audio System', 'Speaker', 'Projector', 'Home Theatre', 'Smart Device', 'Other'], 'type' => 'select'],
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'options' => ['Samsung', 'LG', 'Sony', 'Philips', 'Panasonic', 'Hisense', 'Bose', 'JBL', 'Amazon', 'Apple', 'Other'], 'type' => 'select'],
                ['slug' => 'model_name', 'label' => ['en' => 'Model name/number', 'ar' => 'اسم أو رقم الموديل'], 'options' => [], 'type' => 'string'],
                ['slug' => 'screen_size', 'label' => ['en' => 'Screen size', 'ar' => 'حجم الشاشة'], 'options' => ['24"', '32"', '40"', '43"', '50"', '55"', '65"', '75"', '85"+'], 'type' => 'select'],
                ['slug' => 'display_type', 'label' => ['en' => 'Display type', 'ar' => 'نوع الشاشة'], 'options' => ['LED', 'OLED', 'QLED', 'LCD', 'Other'], 'type' => 'select'],
                ['slug' => 'resolution', 'label' => ['en' => 'Resolution', 'ar' => 'الدقة'], 'options' => ['HD', 'Full HD', '4K UHD', '8K UHD', 'Other'], 'type' => 'select'],
                ['slug' => 'smart_functionality', 'label' => ['en' => 'Smart functionality', 'ar' => 'خاصية ذكية'], 'options' => ['Yes', 'No'], 'type' => 'select'],
                $this->condition(['New', 'Like New', 'Used']),
            ],
            'Electronics > Laptops' => [
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'options' => ['Apple', 'Dell', 'HP', 'Lenovo', 'ASUS', 'Acer', 'Microsoft', 'Other'], 'type' => 'select'],
                ['slug' => 'processor', 'label' => ['en' => 'Processor', 'ar' => 'المعالج'], 'options' => ['Intel i3', 'Intel i5', 'Intel i7', 'Intel i9', 'AMD Ryzen', 'Apple M1', 'Apple M2', 'Other'], 'type' => 'select'],
                ['slug' => 'ram', 'label' => ['en' => 'RAM', 'ar' => 'الذاكرة'], 'options' => ['4GB', '8GB', '16GB', '32GB+'], 'type' => 'select'],
                ['slug' => 'storage', 'label' => ['en' => 'Storage', 'ar' => 'التخزين'], 'options' => ['128GB', '256GB', '512GB', '1TB+'], 'type' => 'select'],
                $this->condition(['New', 'Like New', 'Used']),
            ],
            'Electronics > Tablets' => [
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'options' => ['Apple', 'Samsung', 'Huawei', 'Lenovo', 'Amazon', 'Other'], 'type' => 'select'],
                ['slug' => 'screen_size', 'label' => ['en' => 'Screen size', 'ar' => 'حجم الشاشة'], 'options' => ['7-8"', '9-10"', '11-12.9"', '13"+'], 'type' => 'select'],
                ['slug' => 'storage', 'label' => ['en' => 'Storage capacity', 'ar' => 'سعة التخزين'], 'options' => ['32GB', '64GB', '128GB', '256GB', '512GB+'], 'type' => 'select'],
                ['slug' => 'connectivity', 'label' => ['en' => 'Connectivity', 'ar' => 'الاتصال'], 'options' => ['Wi-Fi', 'Wi-Fi + Cellular'], 'type' => 'select'],
                $this->condition(['New', 'Like New', 'Used']),
            ],
            'Electronics > Mobile phones' => [
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'options' => ['Apple', 'Samsung', 'Huawei', 'Xiaomi', 'OnePlus', 'Oppo', 'Other'], 'type' => 'select'],
                ['slug' => 'storage', 'label' => ['en' => 'Storage capacity', 'ar' => 'سعة التخزين'], 'options' => ['32GB', '64GB', '128GB', '256GB', '512GB', '1TB'], 'type' => 'select'],
                ['slug' => 'lock_status', 'label' => ['en' => 'Unlocked/Locked', 'ar' => 'مفتوح أو مقفل'], 'options' => ['Unlocked', 'Locked'], 'type' => 'select'],
                $this->condition(['New', 'Like New', 'Used']),
            ],
            'Electronics > Computers' => [
                ['slug' => 'computer_type', 'label' => ['en' => 'Type', 'ar' => 'النوع'], 'options' => ['Desktop', 'Monitor', 'Accessories', 'Components', 'Other'], 'type' => 'select'],
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'options' => [], 'type' => 'string'],
                ['slug' => 'processor', 'label' => ['en' => 'Processor', 'ar' => 'المعالج'], 'options' => ['Intel', 'AMD', 'Apple'], 'type' => 'select'],
                ['slug' => 'ram', 'label' => ['en' => 'RAM', 'ar' => 'الذاكرة'], 'options' => ['4GB', '8GB', '16GB', '32GB+'], 'type' => 'select'],
                ['slug' => 'storage', 'label' => ['en' => 'Storage', 'ar' => 'التخزين'], 'options' => ['128GB', '256GB', '512GB', '1TB+'], 'type' => 'select'],
                $this->condition(['New', 'Like New', 'Used']),
            ],
            'Bikes' => [
                ['slug' => 'bike_type', 'label' => ['en' => 'Type', 'ar' => 'النوع'], 'options' => ['Road', 'Mountain', 'Hybrid', 'Electric', 'Folding', 'Kids', 'Other'], 'type' => 'select'],
                ['slug' => 'brand', 'label' => ['en' => 'Brand', 'ar' => 'العلامة التجارية'], 'options' => [], 'type' => 'string'],
                ['slug' => 'frame_size', 'label' => ['en' => 'Frame size', 'ar' => 'مقاس الهيكل'], 'options' => ['Small', 'Medium', 'Large'], 'type' => 'select'],
                $this->condition(['New', 'Used']),
            ],
            'Books & Study Materials' => [
                ['slug' => 'book_type', 'label' => ['en' => 'Book type', 'ar' => 'نوع الكتاب'], 'options' => ['Textbook', 'Novel', 'Academic Reference', 'Workbook', 'Other'], 'type' => 'select'],
                ['slug' => 'subject_field', 'label' => ['en' => 'Subject/Field', 'ar' => 'المادة أو التخصص'], 'options' => ['Accounting', 'Engineering', 'Medicine', 'Law', 'Literature', 'Other'], 'type' => 'select'],
                ['slug' => 'language', 'label' => ['en' => 'Language', 'ar' => 'اللغة'], 'options' => ['English', 'Arabic', 'Other'], 'type' => 'select'],
                $this->condition(['New', 'Like New', 'Used']),
            ],
            'Fashion & Personal Items' => [
                ['slug' => 'item_type', 'label' => ['en' => 'Type', 'ar' => 'النوع'], 'options' => ['Clothing', 'Shoes', 'Bags & accessories', 'Other'], 'type' => 'select'],
                ['slug' => 'gender', 'label' => ['en' => 'Gender', 'ar' => 'الفئة'], 'options' => ['Men', 'Women', 'Unisex'], 'type' => 'select'],
                ['slug' => 'size', 'label' => ['en' => 'Size', 'ar' => 'المقاس'], 'options' => [], 'type' => 'string'],
                $this->condition(['New', 'Used']),
            ],
            'Services' => [
                ['slug' => 'service_type', 'label' => ['en' => 'Service type', 'ar' => 'نوع الخدمة'], 'options' => ['Tutoring', 'Transport', 'Cleaning', 'Moving', 'Repair', 'IT Support', 'Event Services', 'Photography', 'Other'], 'type' => 'select'],
                ['slug' => 'availability', 'label' => ['en' => 'Availability', 'ar' => 'التوفر'], 'options' => ['One-Time', 'Recurring'], 'type' => 'select'],
                ['slug' => 'rate_basis', 'label' => ['en' => 'Rate basis', 'ar' => 'أساس التسعير'], 'options' => ['Per Hour', 'Per Day', 'Per Task'], 'type' => 'select'],
            ],
            'Others' => [
                ['slug' => 'subcategory_type', 'label' => ['en' => 'Subcategory', 'ar' => 'التصنيف الفرعي'], 'options' => ['General Goods', 'Accessories', 'Tools', 'Collectibles', 'Miscellaneous'], 'type' => 'select'],
                $this->condition(['New', 'Like New', 'Used']),
            ],
        ];
    }

    /**
     * Resolves either a main category ("Cars") or a subcategory addressed by
     * path ("Electronics > Laptops"). Subcategories carry their own attributes
     * where the spec gives them distinct fields — laptop RAM has no business
     * appearing on a mobile phone listing.
     */
    private function resolveCategoryId(string $path): ?int
    {
        $segments = array_map('trim', explode('>', $path));

        $parentId = null;
        $categoryId = null;

        foreach ($segments as $name) {
            $categoryId = CategoryTranslation::query()
                ->where('name', $name)
                ->whereHas('category', fn ($q) => $parentId === null
                    ? $q->whereNull('parent_id')
                    : $q->where('parent_id', $parentId))
                ->value('category_id');

            if (! $categoryId) {
                return null;
            }

            $parentId = $categoryId;
        }

        return $categoryId ? (int) $categoryId : null;
    }

    /**
     * Deletes only definitions no longer in the spec, instead of truncating the
     * whole table, so values on existing ads survive.
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
