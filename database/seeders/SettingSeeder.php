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
            'value' => '5.00',
            'set_group' => 'app',
            'is_object' => '1',
        ]);

        Setting::create([
            'key_id' => 'free_ads_per_user',
            'title_en' => 'Free Ads Per User',
            'title_ar' => 'عدد الإعلانات المجانية لكل مستخدم',
            'value' => '2',
            'set_group' => 'app',
            'is_object' => '0',
        ]);

        Setting::create([
            'key_id' => 'post_duration',
            'title_en' => 'Post Duration (days)',
            'title_ar' => 'مدة الإعلان (أيام)',
            'value' => '30',
            'set_group' => 'app',
            'is_object' => '1',
        ]);

        Setting::create([
            'key_id' => 'app_name',
            'title_en' => 'App Name',
            'title_ar' => 'اسم التطبيق',
            'value' => 'UniTill',
            'set_group' => 'app',
            'is_object' => '0',
        ]);

        Setting::create([
            'key_id' => 'app_slogan',
            'title_en' => 'App Slogan',
            'title_ar' => 'شعار التطبيق',
            'value' => 'BUY, SELL, REPEAT!',
            'set_group' => 'app',
            'is_object' => '0',
        ]);

        Setting::create([
            'key_id' => 'app_url',
            'title_en' => 'App URL',
            'title_ar' => 'رابط التطبيق',
            'value' => config('app.url'),
            'set_group' => 'app',
            'is_object' => '0',
        ]);

        Setting::create([
            'key_id' => 'app_logo',
            'title_en' => 'App Logo',
            'title_ar' => 'شعار التطبيق (صورة)',
            'value' => '',
            'set_group' => 'app',
            'is_object' => '0',
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

        Setting::create([
            'key_id' => 'login_popup_image',
            'title_en' => 'Login Popup Image',
            'title_ar' => 'صورة البوب أب عند تسجيل الدخول',
            'value' => '',
            'set_group' => 'app',
            'is_object' => '0',
        ]);

        Setting::create([
            'key_id' => 'enable_login_popup_image',
            'title_en' => 'Enable Login Popup Image',
            'title_ar' => 'تفعيل صورة البوب أب',
            'value' => '0',
            'set_group' => 'app',
            'is_object' => '0',
        ]);



    }
}
