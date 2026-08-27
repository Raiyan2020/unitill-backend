<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_us_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        DB::statement('ALTER TABLE contact_us_messages MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('contact_us_messages', function (Blueprint $table) {
            // A user account deletion should not erase the support thread —
            // just detach it, the way a guest submission looks already.
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_email')->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('contact_us_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['guest_name', 'guest_email']);
        });

        DB::statement('ALTER TABLE contact_us_messages MODIFY user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('contact_us_messages', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
