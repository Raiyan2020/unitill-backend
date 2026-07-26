<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->decimal('listing_fee', 10, 2)->nullable()->after('price');
            $table->string('payment_status')->default('not_required')->after('listing_fee');
            $table->string('stripe_payment_intent_id')->nullable()->unique()->after('payment_status');
            $table->boolean('is_free_listing')->default(false)->after('stripe_payment_intent_id');
        });

        DB::table('settings')->updateOrInsert(
            ['key_id' => 'free_ads_per_user'],
            ['title_en' => 'Free Ads Per User', 'title_ar' => 'عدد الإعلانات المجانية لكل مستخدم', 'value' => '0', 'set_group' => 'app', 'is_object' => false, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropUnique(['stripe_payment_intent_id']);
            $table->dropColumn(['listing_fee', 'payment_status', 'stripe_payment_intent_id', 'is_free_listing']);
        });
    }
};
