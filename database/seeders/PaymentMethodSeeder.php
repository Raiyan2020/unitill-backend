<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run()
    {
       //apple pay
        PaymentMethod::create([
            'name_ar' => 'دفع آبل',
            'name_en' => 'Apple Pay',
            'image' => 'path_to_image',  // اضف المسار المناسب للصورة
            'slug' => 'apple-pay',
            'status' => 'active',
        ]);
        //كي نت
        PaymentMethod::create([
            'name_ar' => 'كي نت',
            'name_en' => 'Knet',
            'image' => 'path_to_image',  // اضف المسار المناسب للصورة
            'slug' => 'knet',
            'status' => 'active',
        ]);
        //فيزا
        PaymentMethod::create([
            'name_ar' => 'فيزا',
            'name_en' => 'Visa',
            'image' => 'path_to_image',  // اضف المسار المناسب للصورة
            'slug' => 'visa',
            'status' => 'active',
        ]);


    }
}
