<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class CategorySeeder extends Seeder
{
    /**
     * Canonical UniTill category tree (10 top-level categories + subcategories)
     * as defined by the business notes. Idempotent: categories are matched by
     * their English translation, so re-running only adds what is missing and
     * never duplicates.
     */
    public function run(): void
    {
        $langs = Language::all()->keyBy('code');
        if ($langs->isEmpty()) {
            return;
        }

        $categoryFees = [
            'Accommodation' => 2.99,
            'Cars' => 2.99,
        ];

        $tree = [
            [
                'en' => 'Accommodation', 'ar' => 'السكن',
                'children' => [
                    ['en' => 'Rooms for rent', 'ar' => 'غرف للإيجار'],
                    ['en' => 'Shared flats', 'ar' => 'شقق مشتركة'],
                    ['en' => 'Student accommodation', 'ar' => 'سكن طلابي'],
                    ['en' => 'Short-term lets', 'ar' => 'إيجارات قصيرة المدى'],
                    ['en' => 'Long-term lets', 'ar' => 'إيجارات طويلة المدى'],
                    ['en' => 'Others', 'ar' => 'أخرى'],
                ],
            ],
            [
                'en' => 'Cars', 'ar' => 'السيارات',
                'children' => [
                    ['en' => 'Cars', 'ar' => 'سيارات'],
                    ['en' => 'Parts', 'ar' => 'قطع غيار'],
                    ['en' => 'Others', 'ar' => 'أخرى'],
                ],
            ],
            [
                'en' => 'Furniture & Home', 'ar' => 'الأثاث والمنزل',
                'children' => [
                    ['en' => 'Beds & mattresses', 'ar' => 'أسِرّة ومراتب'],
                    ['en' => 'Desks & chairs', 'ar' => 'مكاتب وكراسي'],
                    ['en' => 'Sofas & storage', 'ar' => 'كنب وتخزين'],
                    ['en' => 'Kitchen items', 'ar' => 'أدوات المطبخ'],
                    ['en' => 'Others', 'ar' => 'أخرى'],
                ],
            ],
            [
                'en' => 'Electronics', 'ar' => 'الإلكترونيات',
                'children' => [
                    ['en' => 'Laptops', 'ar' => 'لابتوبات'],
                    ['en' => 'Mobile phones', 'ar' => 'هواتف محمولة'],
                    ['en' => 'Tablets', 'ar' => 'تابلت'],
                    ['en' => 'Accessories', 'ar' => 'إكسسوارات'],
                    ['en' => 'Others', 'ar' => 'أخرى'],
                ],
            ],
            [
                'en' => 'Appliances', 'ar' => 'الأجهزة المنزلية',
                'children' => [
                    ['en' => 'Fridges', 'ar' => 'ثلاجات'],
                    ['en' => 'Washing machines', 'ar' => 'غسالات'],
                    ['en' => 'Microwaves', 'ar' => 'ميكروويف'],
                    ['en' => 'Heaters & fans', 'ar' => 'دفايات ومراوح'],
                    ['en' => 'Others', 'ar' => 'أخرى'],
                ],
            ],
            [
                'en' => 'Bikes', 'ar' => 'الدراجات',
                'children' => [
                    ['en' => 'Bicycles', 'ar' => 'دراجات هوائية'],
                    ['en' => 'Electric bikes', 'ar' => 'دراجات كهربائية'],
                    ['en' => 'Motorbikes', 'ar' => 'دراجات نارية'],
                    ['en' => 'Scooters', 'ar' => 'سكوتر'],
                    ['en' => 'Others', 'ar' => 'أخرى'],
                ],
            ],
            [
                'en' => 'Books & Study Materials', 'ar' => 'الكتب والمواد الدراسية',
                'children' => [
                    ['en' => 'Textbooks', 'ar' => 'كتب دراسية'],
                    ['en' => 'Notes & revision materials', 'ar' => 'ملخصات ومراجعات'],
                    ['en' => 'Stationery', 'ar' => 'أدوات مكتبية'],
                    ['en' => 'Others', 'ar' => 'أخرى'],
                ],
            ],
            [
                'en' => 'Fashion & Personal Items', 'ar' => 'الأزياء والأغراض الشخصية',
                'children' => [
                    ['en' => 'Clothing', 'ar' => 'ملابس'],
                    ['en' => 'Shoes', 'ar' => 'أحذية'],
                    ['en' => 'Bags & accessories', 'ar' => 'حقائب وإكسسوارات'],
                    ['en' => 'Others', 'ar' => 'أخرى'],
                ],
            ],
            [
                'en' => 'Services', 'ar' => 'الخدمات',
                'children' => [
                    ['en' => 'Tutoring', 'ar' => 'دروس خصوصية'],
                    ['en' => 'Moving help', 'ar' => 'مساعدة في النقل'],
                    ['en' => 'Cleaning', 'ar' => 'تنظيف'],
                    ['en' => 'IT / tech help', 'ar' => 'دعم تقني'],
                    ['en' => 'Freelance / student services', 'ar' => 'خدمات طلابية / حرة'],
                    ['en' => 'Others', 'ar' => 'أخرى'],
                ],
            ],
            [
                'en' => 'Others', 'ar' => 'أخرى',
                'children' => [],
            ],
        ];

        $sort = 1;
        foreach ($tree as $node) {
            $main = $this->ensureCategory($langs, $node['en'], $node['ar'], null, $sort, $categoryFees[$node['en']] ?? null);
            $sort++;

            $childSort = 1;
            foreach ($node['children'] as $child) {
                $this->ensureCategory(
                    $langs,
                    $child['en'],
                    $child['ar'],
                    $main->id,
                    $childSort
                );
                $childSort++;
            }
        }
    }

    /**
     * Finds a category by its English translation (scoped to the same parent)
     * or creates it. Keeps the seeder safe to run repeatedly.
     */
    private function ensureCategory(
        Collection $langs,
        string $en,
        string $ar,
        ?int $parentId,
        int $sort,
        ?float $listingFee = null
    ): Category {
        $existingId = CategoryTranslation::query()
            ->where('name', $en)
            ->whereHas('category', fn ($q) => $parentId === null
                ? $q->whereNull('parent_id')
                : $q->where('parent_id', $parentId))
            ->value('category_id');

        if ($existingId) {
            return Category::find($existingId);
        }

        $category = Category::create([
            'parent_id' => $parentId,
            'image' => null,
            'status' => 'active',
            'filter_group_id' => null,
            'sort' => $sort,
            'listing_fee' => $listingFee,
        ]);

        foreach (['en' => $en, 'ar' => $ar] as $code => $name) {
            $lang = $langs->get($code);
            if ($lang) {
                CategoryTranslation::create([
                    'category_id' => $category->id,
                    'language_id' => $lang->id,
                    'name' => $name,
                ]);
            }
        }

        return $category;
    }
}
