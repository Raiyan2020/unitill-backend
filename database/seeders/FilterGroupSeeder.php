<?php

namespace Database\Seeders;

use App\Models\FilterGroup;
use Illuminate\Database\Seeder;

class FilterGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['name_ar' => 'مجموعة عامة', 'name_en' => 'General', 'status' => 'active'],
            ['name_ar' => 'مركبات', 'name_en' => 'Vehicles', 'status' => 'active'],
            ['name_ar' => 'هواتف وتابلت', 'name_en' => 'Mobile & Tablets', 'status' => 'active'],
            ['name_ar' => 'إلكترونيات', 'name_en' => 'Electronics', 'status' => 'active'],
            ['name_ar' => 'عقارات', 'name_en' => 'Properties', 'status' => 'active'],
        ];

        foreach ($groups as $row) {
            FilterGroup::updateOrCreate(
                ['name_en' => $row['name_en']],
                $row
            );
        }
    }
}
