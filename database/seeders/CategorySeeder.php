<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Language;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $langs = Language::all()->keyBy('code');
        if ($langs->isEmpty()) {
            return;
        }

        $mains = [
            ['sort' => 1, 'filter' => 5, 'image' => 'https://example.com/images/properties.jpg', 'names' => ['ar' => 'عقارات', 'en' => 'Properties']],
            ['sort' => 2, 'filter' => 2, 'image' => 'https://example.com/images/vehicles.jpg', 'names' => ['ar' => 'مركبات', 'en' => 'Vehicles']],
            ['sort' => 3, 'filter' => 3, 'image' => 'https://example.com/images/mobile_tabs.jpg', 'names' => ['ar' => 'هواتف و تابلت', 'en' => 'Mobile & Tabs']],
            ['sort' => 4, 'filter' => 4, 'image' => 'https://example.com/images/electronics.jpg', 'names' => ['ar' => 'الكترونيات', 'en' => 'Electronics']],
        ];

        foreach ($mains as $row) {
            $cat = Category::create([
                'parent_id' => null,
                'image' => $row['image'],
                'status' => 'active',
                'filter_group_id' => $row['filter'],
                'sort' => $row['sort'],
            ]);
            foreach ($row['names'] as $code => $name) {
                $lang = $langs->get($code);
                if ($lang) {
                    CategoryTranslation::create([
                        'category_id' => $cat->id,
                        'language_id' => $lang->id,
                        'name' => $name,
                    ]);
                }
            }
        }

        $firstMain = Category::whereNull('parent_id')->orderBy('sort')->first();
        if ($firstMain) {
            $sub = Category::create([
                'parent_id' => $firstMain->id,
                'image' => null,
                'status' => 'active',
                'filter_group_id' => null,
                'sort' => 0,
            ]);
            foreach (['ar' => 'مثال قسم فرعي', 'en' => 'Sample subcategory'] as $code => $name) {
                $lang = $langs->get($code);
                if ($lang) {
                    CategoryTranslation::create([
                        'category_id' => $sub->id,
                        'language_id' => $lang->id,
                        'name' => $name,
                    ]);
                }
            }
        }
    }
}
