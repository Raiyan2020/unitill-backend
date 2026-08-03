<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records whether the support notification actually left the server, so a
 * stored-but-never-mailed message is distinguishable from a delivered one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_us_messages', function (Blueprint $table) {
            $table->timestamp('mail_sent_at')->nullable()->after('message');
            $table->text('mail_error')->nullable()->after('mail_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('contact_us_messages', function (Blueprint $table) {
            $table->dropColumn(['mail_sent_at', 'mail_error']);
        });
    }
};
