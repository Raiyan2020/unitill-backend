<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::truncate();

        Setting::create([
            'key_id' => 'post_price',
            'title_en'=>'Post Price',
            'title_ar'=>'سعر الاعلان',
            'value' => '100',
            'set_group' => 'app',
            'is_object' => '1',
        ]);
 
        Setting::create([
            'key_id'     => 'terms_conditions',
            'title_en'   => 'Terms & Conditions',
            'title_ar'   => 'الشروط والأحكام',
            'value'      => 'الشروط والأحكام',
            'set_group'  => 'app',
            'is_object'  => '1',
        ]);
        //terms_conditions_en
        Setting::create([
            'key_id'     => 'terms_conditions_en',
            'title_en'   => 'Terms & Conditions (EN)',
            'title_ar'   => 'الشروط والأحكام (إنجليزي)',
            'value'      => 'Terms & Conditions (EN)',
            'set_group'  => 'app',
            'is_object'  => '1',
        ]);

        Setting::create([
            'key_id'     => 'contact_email',
            'title_en'   => 'Contact Email',
            'title_ar'   => 'البريد الإلكتروني للتواصل',
            'value'      => 'example@mail.com',
            'set_group'  => 'app',
            'is_object'  => '0',
        ]);
        Setting::create([
            'key_id'     => 'contact_phone',
            'title_en'   => 'Contact Phone Number',
            'title_ar'   => 'رقم الهاتف للتواصل',
            'value'      => '00000000',
            'set_group'  => 'app',
            'is_object'  => '0',
        ]);



    }
}
