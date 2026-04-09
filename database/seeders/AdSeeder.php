<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdSeeder extends Seeder
{
    public function run(): void
    {
        $country = Country::query()->first();
        $city = City::query()->first();
        $mainCategory = Category::query()->whereNull('parent_id')->first();

        if (! $country || ! $city || ! $mainCategory) {
            return;
        }

        $subCategory = Category::query()->where('parent_id', $mainCategory->id)->first();

        $user = User::query()->firstOrCreate(
            ['email' => 'demo.user@unitill.local'],
            [
                'name' => 'Demo User',
                'first_name' => 'Demo',
                'last_name' => 'User',
                'phone' => '0590000000',
                'country_code' => $country->country_code,
                'city_id' => $city->id,
                'status' => '1',
                'password' => '123456',
            ]
        );

        $ads = [
            [
                'public_id' => 'DEMOAD0001',
                'title' => 'iPhone 14 Pro Max - Excellent condition',
                'subtitle' => '128GB / Battery 92%',
                'description' => 'Single owner device, original box included, no repairs.',
                'price' => 850.00,
                'currency' => 'USD',
                'status' => 'published',
                'is_negotiable' => true,
                'is_verified' => true,
                'cover_image' => 'categories/demo-ad-1.jpg',
            ],
            [
                'public_id' => 'DEMOAD0002',
                'title' => 'Toyota Corolla 2018',
                'subtitle' => 'Automatic / Clean title',
                'description' => 'Well maintained, new tires, no accidents.',
                'price' => 11200.00,
                'currency' => 'USD',
                'status' => 'pending',
                'is_negotiable' => true,
                'is_verified' => false,
                'cover_image' => 'categories/demo-ad-2.jpg',
            ],
            [
                'public_id' => 'DEMOAD0003',
                'title' => 'Apartment for rent - City center',
                'subtitle' => '2 bedrooms / 120m',
                'description' => 'Modern apartment close to all services.',
                'price' => 600.00,
                'currency' => 'USD',
                'status' => 'draft',
                'is_negotiable' => false,
                'is_verified' => false,
                'cover_image' => 'categories/demo-ad-3.jpg',
            ],
        ];

        foreach ($ads as $row) {
            Ad::query()->updateOrCreate(
                ['public_id' => $row['public_id']],
                [
                    'user_id' => $user->id,
                    'title' => $row['title'],
                    'subtitle' => $row['subtitle'],
                    'description' => $row['description'],
                    'country_id' => $country->id,
                    'city_id' => $city->id,
                    'main_category_id' => $mainCategory->id,
                    'sub_category_id' => $subCategory?->id,
                    'cover_image' => $row['cover_image'],
                    'price' => $row['price'],
                    'currency' => $row['currency'],
                    'is_negotiable' => $row['is_negotiable'],
                    'is_verified' => $row['is_verified'],
                    'slug' => Str::slug($row['title'].'-'.$row['public_id']),
                    'status' => $row['status'],
                    'published_at' => $row['status'] === 'published' ? now() : null,
                ]
            );
        }
    }
}

