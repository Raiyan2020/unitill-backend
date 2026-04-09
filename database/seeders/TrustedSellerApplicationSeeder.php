<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\TrustedSellerApplication;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrustedSellerApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::query()->whereNull('parent_id')->first();

        $users = [
            [
                'email' => 'trusted.pending1@unitill.local',
                'name' => 'Trusted Pending One',
                'first_name' => 'Trusted',
                'last_name' => 'Pending One',
                'phone' => '0591111111',
            ],
            [
                'email' => 'trusted.pending2@unitill.local',
                'name' => 'Trusted Pending Two',
                'first_name' => 'Trusted',
                'last_name' => 'Pending Two',
                'phone' => '0592222222',
            ],
            [
                'email' => 'trusted.pending3@unitill.local',
                'name' => 'Trusted Pending Three',
                'first_name' => 'Trusted',
                'last_name' => 'Pending Three',
                'phone' => '0593333333',
            ],
        ];

        foreach ($users as $i => $u) {
            $user = User::query()->firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'first_name' => $u['first_name'],
                    'last_name' => $u['last_name'],
                    'phone' => $u['phone'],
                    'country_code' => '+970',
                    'status' => '1',
                    'password' => '123456',
                ]
            );

            TrustedSellerApplication::query()->updateOrCreate(
                ['user_id' => $user->id, 'status' => 'pending'],
                [
                    'seller_type' => $i % 2 === 0 ? 'business' : 'service_provider',
                    'is_non_student_confirmed' => true,
                    'operations_city' => 'Gaza',
                    'primary_contact_name' => $u['name'],
                    'contact_email' => $u['email'],
                    'contact_phone' => $u['phone'],
                    'category_id' => $category?->id,
                    'offers_summary' => 'Trusted seller request seeded for dashboard review.',
                    'estimated_ads_volume' => 'multiple',
                    'preferred_student_contact_method' => 'phone',
                    'ack_review_discretion' => true,
                    'ack_unitill_manages_directory' => true,
                    'ack_no_app_access' => true,
                ]
            );
        }
    }
}

