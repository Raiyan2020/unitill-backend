<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key_id' => 'listing_extension_price'],
            [
                'title_en' => 'Listing Extension Price',
                'title_ar' => 'سعر تمديد الإعلان',
                'value' => '0.99',
                'set_group' => 'app',
                'is_object' => '1',
            ]
        );
    }

    public function down(): void
    {
        Setting::where('key_id', 'listing_extension_price')->delete();
    }
};
