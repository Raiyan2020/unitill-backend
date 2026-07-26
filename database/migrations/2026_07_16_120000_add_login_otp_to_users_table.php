<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'login_otp')) {
                $table->string('login_otp')->nullable()->after('activation_sent_at');
            }
            if (! Schema::hasColumn('users', 'login_otp_expires_at')) {
                $table->timestamp('login_otp_expires_at')->nullable()->after('login_otp');
            }
            if (! Schema::hasColumn('users', 'login_otp_sent_at')) {
                $table->timestamp('login_otp_sent_at')->nullable()->after('login_otp_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['login_otp', 'login_otp_expires_at', 'login_otp_sent_at'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
