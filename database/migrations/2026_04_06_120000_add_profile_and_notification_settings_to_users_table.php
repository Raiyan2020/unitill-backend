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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_updated_at')->nullable()->after('password');
            $table->boolean('privacy_settings_enabled')->default(true)->comment('إعداد الخصوصية 0/1');
            $table->boolean('show_last_name')->default(true)->comment('عرض الاسم الأخير');
            $table->boolean('show_approximate_location')->default(false)->comment('عرض الموقع التقريبي');
            $table->boolean('trusted_users_only')->default(false)->comment('المستخدمون الموثوقون فقط');
            $table->boolean('notify_system')->default(true)->comment('تنبيهات النظام');
            $table->boolean('notify_chat')->default(true)->comment('رسائل الدردشة');
            $table->boolean('notify_ads')->default(true)->comment('تحديثات الإعلانات');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password_updated_at',
                'privacy_settings_enabled',
                'show_last_name',
                'show_approximate_location',
                'trusted_users_only',
                'notify_system',
                'notify_chat',
                'notify_ads',
            ]);
        });
    }
};
