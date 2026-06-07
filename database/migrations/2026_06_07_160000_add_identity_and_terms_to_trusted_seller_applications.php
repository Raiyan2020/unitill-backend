<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trusted_seller_applications', function (Blueprint $table) {
            $table->boolean('identity_confirmed_by_others')->default(false)->after('is_non_student_confirmed');
            $table->boolean('ack_terms_accepted')->default(false)->after('ack_no_app_access');
        });
    }

    public function down(): void
    {
        Schema::table('trusted_seller_applications', function (Blueprint $table) {
            $table->dropColumn(['identity_confirmed_by_others', 'ack_terms_accepted']);
        });
    }
};
