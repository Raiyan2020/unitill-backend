<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            LanguageSeeder::class,
            ContactReasonSeeder::class,
            LegalAffairSeeder::class,
            AdminSeeder::class,
            CitySeeder::class,
            UniversitySeeder::class,
            SettingSeeder::class,
 
            CategorySeeder::class,
            CategoryAttributeSeeder::class,
            PaymentMethodSeeder::class,
            // Must run after the content seeders: it backfills their fr/es/zh
            // rows and the option label maps without touching existing data.
            MultilingualContentSeeder::class,
            AdSeeder::class,
            TrustedSellerApplicationSeeder::class,

        ]);
    }
}
