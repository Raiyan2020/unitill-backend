<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitySeeder extends Seeder
{
    /**
     * England's officially city-status settlements (per the most recent civic
     * honours round). "City of London" and "Westminster" are merged into a
     * single practical "London" entry — the formal split isn't meaningful for
     * a marketplace location picker.
     *
     * name_ar / name_zh are transliterations; fr/es reuse the English spelling
     * (the real-world convention for UK place names), except London → Londres.
     */
    private const CITIES = [
        ['en' => 'Bath', 'ar' => 'باث', 'zh' => '巴斯'],
        ['en' => 'Birmingham', 'ar' => 'برمنغهام', 'zh' => '伯明翰'],
        ['en' => 'Bradford', 'ar' => 'برادفورد', 'zh' => '布拉德福德'],
        ['en' => 'Brighton & Hove', 'ar' => 'برايتون آند هوف', 'zh' => '布莱顿-霍夫'],
        ['en' => 'Bristol', 'ar' => 'بريستول', 'zh' => '布里斯托尔'],
        ['en' => 'Cambridge', 'ar' => 'كامبريدج', 'zh' => '剑桥'],
        ['en' => 'Canterbury', 'ar' => 'كانتربري', 'zh' => '坎特伯雷'],
        ['en' => 'Carlisle', 'ar' => 'كارلايل', 'zh' => '卡莱尔'],
        ['en' => 'Chelmsford', 'ar' => 'تشيلمسفورد', 'zh' => '切姆斯福德'],
        ['en' => 'Chester', 'ar' => 'تشيستر', 'zh' => '切斯特'],
        ['en' => 'Chichester', 'ar' => 'تشيتشستر', 'zh' => '奇切斯特'],
        ['en' => 'Colchester', 'ar' => 'كولتشستر', 'zh' => '科尔切斯特'],
        ['en' => 'Coventry', 'ar' => 'كوفنتري', 'zh' => '考文垂'],
        ['en' => 'Derby', 'ar' => 'ديربي', 'zh' => '德比'],
        ['en' => 'Doncaster', 'ar' => 'دونكاستر', 'zh' => '唐克斯特'],
        ['en' => 'Durham', 'ar' => 'دورهام', 'zh' => '达勒姆'],
        ['en' => 'Ely', 'ar' => 'إيلي', 'zh' => '伊利'],
        ['en' => 'Exeter', 'ar' => 'إكستر', 'zh' => '埃克塞特'],
        ['en' => 'Gloucester', 'ar' => 'غلوستر', 'zh' => '格洛斯特'],
        ['en' => 'Hereford', 'ar' => 'هيرفورد', 'zh' => '赫里福德'],
        ['en' => 'Kingston upon Hull', 'ar' => 'كينغستون أبون هل', 'zh' => '赫尔'],
        ['en' => 'Lancaster', 'ar' => 'لانكستر', 'zh' => '兰开斯特'],
        ['en' => 'Leeds', 'ar' => 'ليدز', 'zh' => '利兹'],
        ['en' => 'Leicester', 'ar' => 'ليستر', 'zh' => '莱斯特'],
        ['en' => 'Lichfield', 'ar' => 'ليتشفيلد', 'zh' => '利奇菲尔德'],
        ['en' => 'Lincoln', 'ar' => 'لينكولن', 'zh' => '林肯'],
        ['en' => 'Liverpool', 'ar' => 'ليفربول', 'zh' => '利物浦'],
        ['en' => 'London', 'ar' => 'لندن', 'zh' => '伦敦', 'fr' => 'Londres', 'es' => 'Londres'],
        ['en' => 'Manchester', 'ar' => 'مانشستر', 'zh' => '曼彻斯特'],
        ['en' => 'Milton Keynes', 'ar' => 'ميلتون كينز', 'zh' => '米尔顿凯恩斯'],
        ['en' => 'Newcastle upon Tyne', 'ar' => 'نيوكاسل أبون تاين', 'zh' => '纽卡斯尔'],
        ['en' => 'Norwich', 'ar' => 'نورويتش', 'zh' => '诺里奇'],
        ['en' => 'Nottingham', 'ar' => 'نوتنغهام', 'zh' => '诺丁汉'],
        ['en' => 'Oxford', 'ar' => 'أكسفورد', 'zh' => '牛津'],
        ['en' => 'Peterborough', 'ar' => 'بيتربره', 'zh' => '彼得伯勒'],
        ['en' => 'Plymouth', 'ar' => 'بليموث', 'zh' => '普利茅斯'],
        ['en' => 'Portsmouth', 'ar' => 'بورتسموث', 'zh' => '朴次茅斯'],
        ['en' => 'Preston', 'ar' => 'بريستون', 'zh' => '普雷斯顿'],
        ['en' => 'Ripon', 'ar' => 'ريبون', 'zh' => '里彭'],
        ['en' => 'Salford', 'ar' => 'سالفورد', 'zh' => '索尔福德'],
        ['en' => 'Salisbury', 'ar' => 'سالزبري', 'zh' => '索尔兹伯里'],
        ['en' => 'Sheffield', 'ar' => 'شيفيلد', 'zh' => '谢菲尔德'],
        ['en' => 'Southampton', 'ar' => 'ساوثهامبتون', 'zh' => '南安普顿'],
        ['en' => 'Southend-on-Sea', 'ar' => 'ساوثيند أون سي', 'zh' => '滨海绍森德'],
        ['en' => 'St Albans', 'ar' => 'سانت ألبانز', 'zh' => '圣奥尔本斯'],
        ['en' => 'Stoke-on-Trent', 'ar' => 'ستوك أون ترينت', 'zh' => '特伦特河畔斯托克'],
        ['en' => 'Sunderland', 'ar' => 'سندرلاند', 'zh' => '桑德兰'],
        ['en' => 'Truro', 'ar' => 'ترورو', 'zh' => '特鲁罗'],
        ['en' => 'Wakefield', 'ar' => 'ويكفيلد', 'zh' => '韦克菲尔德'],
        ['en' => 'Wells', 'ar' => 'ويلز', 'zh' => '韦尔斯'],
        ['en' => 'Winchester', 'ar' => 'وينشستر', 'zh' => '温切斯特'],
        ['en' => 'Wolverhampton', 'ar' => 'وولفرهامبتون', 'zh' => '伍尔弗汉普顿'],
        ['en' => 'Worcester', 'ar' => 'ووستر', 'zh' => '伍斯特'],
        ['en' => 'York', 'ar' => 'يورك', 'zh' => '约克'],
    ];

    public function run(): void
    {
        $languages = Language::query()->active()->get(['id', 'code'])->keyBy('code');
        if ($languages->isEmpty()) {
            return;
        }

        $country = Country::query()->updateOrCreate(
            ['country_code' => 'GB'],
            ['status' => 'active', 'sort' => 0]
        );

        $countryNames = ['en' => 'United Kingdom', 'ar' => 'المملكة المتحدة', 'fr' => 'Royaume-Uni', 'es' => 'Reino Unido', 'zh' => '英国'];
        foreach ($languages as $code => $language) {
            $country->translations()->updateOrCreate(
                ['language_id' => $language->id],
                ['name' => $countryNames[$code] ?? $countryNames['en']]
            );
        }

        // Cascades to city_translations; ads.city_id also cascades on delete,
        // so this intentionally clears any ads still pointing at the old rows.
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        City::query()->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        foreach (self::CITIES as $index => $names) {
            $city = City::create([
                'country_id' => $country->id,
                'country_code' => $country->country_code,
                'status' => 'active',
                'sort' => $index + 1,
            ]);

            foreach ($languages as $code => $language) {
                $name = $names[$code] ?? $names['en'];
                $city->translations()->updateOrCreate(
                    ['language_id' => $language->id],
                    ['name' => $name]
                );
            }
        }
    }
}
