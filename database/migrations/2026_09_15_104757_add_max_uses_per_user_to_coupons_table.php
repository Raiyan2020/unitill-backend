<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('max_uses_per_user')->nullable()->default(1)->after('max_redemptions');
        });

        Schema::table('coupon_redemptions', function (Blueprint $table) {
            $table->index('coupon_id');
            $table->dropUnique(['coupon_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table) {
            $table->unique(['coupon_id', 'user_id']);
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('max_uses_per_user');
        });
    }
};
