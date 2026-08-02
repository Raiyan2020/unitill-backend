<?php

/**
 * Deterministic fixture builder, run identically in the Salman reference tree
 * and the Takwa tree so captured responses are directly comparable.
 *
 * Only touches columns that exist in BOTH trees (the shared base migrations),
 * so it must never reference payment_status, region, login_otp, deleted_at etc.
 * Every id and timestamp is fixed; nothing is randomised.
 *
 * Usage:  php artisan tinker --execute="require '/path/to/fixtures.php';"
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

const FIXED_NOW = '2026-06-01 12:00:00';

DB::statement('SET FOREIGN_KEY_CHECKS=0');

// ------------------------------------------------------------- country
DB::table('countries')->updateOrInsert(['id' => 9001], [
    'country_code' => 'GB',
    'status' => 'active',
    'sort' => 1,
    'created_at' => FIXED_NOW,
    'updated_at' => FIXED_NOW,
]);

// ---------------------------------------------------------------- city
// Cities are translated, so the display name lives in city_translations.
DB::table('cities')->updateOrInsert(['id' => 9001], [
    'country_id' => 9001,
    'country_code' => 'GB',
    'status' => 'active',
    'code' => 'LDS',
    'sort' => 1,
    'created_at' => FIXED_NOW,
    'updated_at' => FIXED_NOW,
]);

foreach (['en' => 'Leeds', 'ar' => 'ليدز'] as $code => $cityName) {
    $languageId = DB::table('languages')->where('code', $code)->value('id');
    if (! $languageId) {
        continue;
    }
    DB::table('city_translations')->updateOrInsert(
        ['city_id' => 9001, 'language_id' => $languageId],
        ['name' => $cityName, 'created_at' => FIXED_NOW, 'updated_at' => FIXED_NOW]
    );
}

// ---------------------------------------------------------------- users
$users = [
    // seller
    9001 => ['Ada Lovelace', 'Ada', 'Lovelace', 'seller@example.com', 'seller@leeds.ac.uk'],
    // buyer
    9002 => ['Alan Turing', 'Alan', 'Turing', 'buyer@example.com', 'buyer@leeds.ac.uk'],
];

foreach ($users as $id => [$name, $first, $last, $email, $studentEmail]) {
    DB::table('users')->updateOrInsert(['id' => $id], [
        'name' => $name,
        'first_name' => $first,
        'last_name' => $last,
        'email' => $email,
        'student_email' => $studentEmail,
        'password' => Hash::make('password123'),
        'phone' => '+4471234567'.($id - 9000),
        'country_code' => 'GB',
        'city_id' => 9001,
        'status' => '1',
        'is_trusted_seller' => $id === 9001 ? 1 : 0,
        'terms_accepted_at' => FIXED_NOW,
        'created_at' => FIXED_NOW,
        'updated_at' => FIXED_NOW,
    ]);
}

// ------------------------------------------------- categories in use
$main = DB::table('categories')->whereNull('parent_id')->orderBy('id')->first();
$sub = DB::table('categories')->where('parent_id', $main->id)->orderBy('id')->first();

// ---------------------------------------------------------------- ads
$ads = [
    // id,   status,      published_at,          expires_at,            price
    [9001, 'published', '2026-05-01 09:00:00', '2026-12-01 09:00:00', 250.00],
    [9002, 'published', null,                  '2026-12-01 09:00:00', 75.50],
    [9003, 'draft',     null,                  null,                  10.00],
    [9004, 'sold',      '2026-04-01 09:00:00', '2026-11-01 09:00:00', 500.00],
];

foreach ($ads as [$id, $status, $publishedAt, $expiresAt, $price]) {
    DB::table('ads')->updateOrInsert(['id' => $id], [
        'user_id' => 9001,
        'country_id' => 9001,
        'public_id' => 'FIX'.$id,
        'title' => 'Fixture ad '.$id,
        'subtitle' => 'Subtitle '.$id,
        'description' => 'Deterministic fixture description for ad '.$id.'.',
        'city_id' => 9001,
        'postcode' => 'LS2 9JT',
        'location_name' => 'Leeds city centre',
        'latitude' => 53.8007554,
        'longitude' => -1.5490774,
        'main_category_id' => $main->id,
        'sub_category_id' => $sub?->id,
        'price' => $price,
        'currency' => 'GBP',
        'is_negotiable' => 1,
        'is_verified' => 1,
        'slug' => 'fixture-ad-'.$id,
        'status' => $status,
        'published_at' => $publishedAt,
        'expires_at' => $expiresAt,
        'created_at' => FIXED_NOW,
        'updated_at' => FIXED_NOW,
    ]);
}

// ---------------------------------------------- remove seeder cities
// Same reasoning as the ads below: the trees ship different CitySeeder demo
// rows. Repoint anything referencing them at the fixture city first so the
// delete cannot orphan a foreign key.
DB::table('users')->where('city_id', '!=', 9001)->update(['city_id' => 9001]);
DB::table('ads')->where('city_id', '!=', 9001)->update(['city_id' => 9001]);
DB::table('city_translations')->where('city_id', '!=', 9001)->delete();
DB::table('cities')->where('id', '!=', 9001)->delete();

// ------------------------------------------------- remove seeder ads
// The two trees ship different AdSeeder demo data, which would otherwise show
// up as contract differences. The fixture set defines the world, so drop any
// ad the fixtures did not create.
$fixtureAdIds = array_column($ads, 0);
DB::table('ad_attribute_values')->whereNotIn('ad_id', $fixtureAdIds)->delete();
DB::table('ad_images')->whereNotIn('ad_id', $fixtureAdIds)->delete();
DB::table('ad_favorites')->whereNotIn('ad_id', $fixtureAdIds)->delete();
DB::table('ads')->whereNotIn('id', $fixtureAdIds)->delete();

// ------------------------------------------------------------ coupons
// Column sets diverge between the trees, so build the row from whatever the
// live schema actually has rather than assuming either shape.
$couponCols = collect(DB::select('SHOW COLUMNS FROM coupons'))->pluck('Field')->all();
$coupon = ['code' => 'FIXTURE10', 'value' => 10.00, 'is_active' => 1,
           'created_at' => FIXED_NOW, 'updated_at' => FIXED_NOW];
$coupon['type'] = in_array('type', $couponCols, true)
    ? (str_contains(strtolower((string) collect(DB::select("SHOW COLUMNS FROM coupons LIKE 'type'"))->first()->Type), 'percentage')
        ? 'percentage' : 'percent')
    : 'percent';
foreach (['max_uses' => 100, 'max_uses_per_user' => 1, 'used_count' => 0,
          'max_redemptions' => 100, 'redemptions_count' => 0,
          'min_amount' => null, 'max_discount' => null] as $col => $val) {
    if (in_array($col, $couponCols, true)) {
        $coupon[$col] = $val;
    }
}
DB::table('coupons')->updateOrInsert(['code' => 'FIXTURE10'], $coupon);

// ------------------------------------------------------------ favourite
DB::table('ad_favorites')->updateOrInsert(
    ['user_id' => 9002, 'ad_id' => 9001],
    ['created_at' => FIXED_NOW, 'updated_at' => FIXED_NOW]
);

DB::statement('SET FOREIGN_KEY_CHECKS=1');

echo "fixtures: ok\n";
