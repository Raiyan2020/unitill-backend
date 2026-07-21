<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The dashboard settings screen is data-driven — it renders whatever rows
     * exist in `settings` — so a migration is used rather than only the seeder,
     * which would require a reseed to reach an existing environment.
     */
    public function up(): void
    {
        Setting::query()->updateOrCreate(
            ['key_id' => 'app_url'],
            [
                'title_en' => 'App URL',
                'title_ar' => 'رابط التطبيق',
                'value' => config('app.url'),
                'set_group' => 'app',
                'is_object' => '0',
            ]
        );
    }

    public function down(): void
    {
        Setting::query()->where('key_id', 'app_url')->delete();
    }
};
