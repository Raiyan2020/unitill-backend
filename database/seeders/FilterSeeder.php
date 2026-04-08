<?php

namespace Database\Seeders;

use App\Models\Filter;
use App\Models\FilterGroup;
use Illuminate\Database\Seeder;

class FilterSeeder extends Seeder
{
    public function run(): void
    {
        foreach (FilterGroup::all() as $group) {
            Filter::firstOrCreate(
                ['filter_group_id' => $group->id],
                [
                    'name_ar' => ($group->name_ar ?? 'فلتر').' — تجريبي',
                    'name_en' => ($group->name_en ?? 'Filter').' — sample',
                    'status' => 'active',
                ]
            );
        }
    }
}
