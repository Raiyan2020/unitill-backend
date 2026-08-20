<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use App\Models\University;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MissingUniversityCitySeeder extends Seeder
{
    /**
     * Names for the university cities that CitySeeder's England-only list
     * doesn't cover (Scotland/Wales/Northern Ireland, plus a few England
     * towns without formal city status). Purely additive — never touches
     * an existing city row, unlike CitySeeder.
     */
    private const NAMES = [
        'edinburgh' => ['en' => 'Edinburgh', 'ar' => 'إدنبرة', 'fr' => 'Édimbourg', 'es' => 'Edimburgo', 'zh' => '爱丁堡'],
        'glasgow' => ['en' => 'Glasgow', 'ar' => 'غلاسكو', 'zh' => '格拉斯哥'],
        'st andrews' => ['en' => 'St Andrews', 'ar' => 'سانت أندروز', 'zh' => '圣安德鲁斯'],
        'aberdeen' => ['en' => 'Aberdeen', 'ar' => 'أبردين', 'zh' => '阿伯丁'],
        'belfast' => ['en' => 'Belfast', 'ar' => 'بلفاست', 'zh' => '贝尔法斯特'],
        'dundee' => ['en' => 'Dundee', 'ar' => 'دندي', 'zh' => '邓迪'],
        'cardiff' => ['en' => 'Cardiff', 'ar' => 'كارديف', 'zh' => '卡迪夫'],
        'reading' => ['en' => 'Reading', 'ar' => 'ريدنغ', 'zh' => '雷丁'],
        'brighton' => ['en' => 'Brighton', 'ar' => 'برايتون', 'zh' => '布莱顿'],
        'loughborough' => ['en' => 'Loughborough', 'ar' => 'لوبره', 'zh' => '拉夫堡'],
        'guildford' => ['en' => 'Guildford', 'ar' => 'غيلدفورد', 'zh' => '吉尔福德'],
    ];

    public function run(): void
    {
        $languages = Language::query()->active()->get(['id', 'code'])->keyBy('code');
        if ($languages->isEmpty()) {
            return;
        }

        $existingByName = DB::table('city_translations')
            ->join('languages', 'languages.id', '=', 'city_translations.language_id')
            ->where('languages.code', 'en')
            ->select('city_translations.city_id', DB::raw('LOWER(TRIM(city_translations.name)) as name_key'))
            ->pluck('city_id', 'name_key');

        // Every distinct city string actually referenced by a university,
        // not just the known list above — anything new added to
        // UniversitySeeder later is picked up automatically instead of
        // silently staying unmatched.
        $neededNames = University::query()
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->pluck('city');

        $countryId = Country::query()->where('country_code', 'GB')->value('id');

        $created = [];
        $unresolved = [];

        foreach ($neededNames as $rawName) {
            $key = strtolower(trim($rawName));
            if (isset($existingByName[$key])) {
                continue; // already has a matching city row
            }

            $names = self::NAMES[$key] ?? null;
            if (! $names) {
                // No translation on file for this one — fall back to the
                // English string in every language rather than skipping it,
                // so it still resolves and can be corrected later via the
                // dashboard.
                $names = ['en' => $rawName];
                $unresolved[] = $rawName;
            }

            $city = City::create([
                'country_id' => $countryId,
                'country_code' => 'GB',
                'status' => 'active',
                'sort' => 0,
            ]);

            foreach ($languages as $code => $language) {
                $city->translations()->create([
                    'language_id' => $language->id,
                    'name' => $names[$code] ?? $names['en'],
                ]);
            }

            $existingByName[$key] = $city->id;
            $created[] = $names['en'];
        }

        // Re-link every university whose city_id is still null now that its
        // matching city row exists.
        University::query()->whereNull('city_id')->whereNotNull('city')->chunkById(200, function ($universities) use ($existingByName) {
            foreach ($universities as $university) {
                $cityId = $existingByName[strtolower(trim($university->city))] ?? null;
                if ($cityId) {
                    $university->forceFill(['city_id' => $cityId])->save();
                }
            }
        });

        if (! empty($created)) {
            $this->command?->info('Created cities: '.implode(', ', $created));
        }
        if (! empty($unresolved)) {
            $this->command?->warn('No translation on file for: '.implode(', ', $unresolved).' — added with English name in every language, fix via the dashboard.');
        }
    }
}
