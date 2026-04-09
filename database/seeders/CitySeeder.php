<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $country = Country::query()->first();
        if (! $country) {
            return;
        }

        $city = City::firstOrCreate(
            ['code' => 'DEMO'],
            [
                'country_id' => $country->id,
                'country_code' => $country->country_code,
                'status' => 'active',
                'sort' => 0,
            ]
        );

        $languages = Language::query()->active()->get(['id', 'code']);
        foreach ($languages as $language) {
            $name = $language->code === 'ar' ? 'مدينة تجريبية' : 'Demo City';
            $city->translations()->updateOrCreate(
                ['language_id' => $language->id],
                ['name' => $name]
            );
        }
    }
}
