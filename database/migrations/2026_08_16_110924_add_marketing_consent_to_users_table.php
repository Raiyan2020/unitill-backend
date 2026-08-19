<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing messages need their own opt-in, separate from the general
 * terms-of-use acceptance (terms_accepted_at) and from notify_system (which
 * covers transactional/system alerts the product does not allow disabling).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_marketing')->default(false)->after('notify_ads')->comment('رسائل التسويق (اشتراك اختياري)');
            $table->timestamp('marketing_consent_at')->nullable()->after('notify_marketing');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_marketing', 'marketing_consent_at']);
        });
    }
};
