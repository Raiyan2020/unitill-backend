<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $country = Country::query()->first();
        if (! $country) {
            return;
        }

        City::firstOrCreate(
            ['code' => 'DEMO'],
            [
                'country_id' => $country->id,
                'country_code' => $country->country_code,
                'name_ar' => 'مدينة تجريبية',
                'name_en' => 'Demo City',
                'status' => 'active',
                'sort' => 0,
            ]
        );
    }
}
