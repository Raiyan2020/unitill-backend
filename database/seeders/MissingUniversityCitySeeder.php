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
        'aberystwyth' => ['en' => 'Aberystwyth', 'ar' => 'أبيريستويث', 'zh' => '阿伯里斯特威斯'],
        'bangor' => ['en' => 'Bangor', 'ar' => 'بانجور', 'zh' => '班戈'],
        'bolton' => ['en' => 'Bolton', 'ar' => 'بولتون', 'zh' => '博尔顿'],
        'buckingham' => ['en' => 'Buckingham', 'ar' => 'بكنغهام', 'zh' => '白金汉'],
        'carmarthen' => ['en' => 'Carmarthen', 'ar' => 'كارمارثن', 'zh' => '卡马森'],
        'cheltenham' => ['en' => 'Cheltenham', 'ar' => 'تشلتنهام', 'zh' => '切尔滕纳姆'],
        'cirencester' => ['en' => 'Cirencester', 'ar' => 'سايرنسستر', 'zh' => '赛伦塞斯特'],
        'cranfield' => ['en' => 'Cranfield', 'ar' => 'كرانفيلد', 'zh' => '克兰菲尔德'],
        'egham' => ['en' => 'Egham', 'ar' => 'إيغهام', 'zh' => '埃格姆'],
        'falmouth' => ['en' => 'Falmouth', 'ar' => 'فالماوث', 'zh' => '法尔茅斯'],
        'hatfield' => ['en' => 'Hatfield', 'ar' => 'هاتفيلد', 'zh' => '哈特菲尔德'],
        'high wycombe' => ['en' => 'High Wycombe', 'ar' => 'هاي ويكومب', 'zh' => '高威科姆'],
        'huddersfield' => ['en' => 'Huddersfield', 'ar' => 'هدرسفيلد', 'zh' => '哈德斯菲尔德'],
        'hull' => ['en' => 'Hull', 'ar' => 'هال', 'zh' => '赫尔'],
        'inverness' => ['en' => 'Inverness', 'ar' => 'إنفرنس', 'zh' => '因弗内斯'],
        'ipswich' => ['en' => 'Ipswich', 'ar' => 'إبسويتش', 'zh' => '伊普斯维奇'],
        'keele' => ['en' => 'Keele', 'ar' => 'كيل', 'zh' => '基尔'],
        'kingston upon thames' => ['en' => 'Kingston upon Thames', 'ar' => 'كينغستون أبون تايمز', 'zh' => '泰晤士河畔金斯顿'],
        'luton' => ['en' => 'Luton', 'ar' => 'لوتون', 'zh' => '卢顿'],
        'middlesbrough' => ['en' => 'Middlesbrough', 'ar' => 'ميدلزبره', 'zh' => '米德尔斯堡'],
        'newport' => ['en' => 'Newport', 'ar' => 'نيوبورت', 'zh' => '纽波特'],
        'northampton' => ['en' => 'Northampton', 'ar' => 'نورثهامبتون', 'zh' => '北安普顿'],
        'ormskirk' => ['en' => 'Ormskirk', 'ar' => 'أورمسكيرك', 'zh' => '奥姆斯柯克'],
        'paisley' => ['en' => 'Paisley', 'ar' => 'بيزلي', 'zh' => '佩斯利'],
        'pontypridd' => ['en' => 'Pontypridd', 'ar' => 'بونتيبريد', 'zh' => '庞蒂普里德'],
        'poole' => ['en' => 'Poole', 'ar' => 'بول', 'zh' => '普尔'],
        'stirling' => ['en' => 'Stirling', 'ar' => 'ستيرلنغ', 'zh' => '斯特灵'],
        'swansea' => ['en' => 'Swansea', 'ar' => 'سوانزي', 'zh' => '斯旺西'],
        'uxbridge' => ['en' => 'Uxbridge', 'ar' => 'أكسبريدج', 'zh' => '阿克斯布里奇'],
        'wrexham' => ['en' => 'Wrexham', 'ar' => 'ريكسهام', 'zh' => '雷克瑟姆'],
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
