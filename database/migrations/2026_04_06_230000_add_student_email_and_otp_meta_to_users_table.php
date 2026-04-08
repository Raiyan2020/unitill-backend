<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'student_email')) {
                $table->string('student_email')->nullable()->unique()->after('email');
            }
            if (! Schema::hasColumn('users', 'activation_code_expires_at')) {
                $table->timestamp('activation_code_expires_at')->nullable()->after('activation_code');
            }
            if (! Schema::hasColumn('users', 'activation_sent_at')) {
                $table->timestamp('activation_sent_at')->nullable()->after('activation_code_expires_at');
            }
            if (! Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'reset_code')) {
                $table->string('reset_code')->nullable();
            }
            if (! Schema::hasColumn('users', 'reset_code_expire')) {
                $table->timestamp('reset_code_expire')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = ['student_email', 'activation_code_expires_at', 'activation_sent_at', 'terms_accepted_at', 'reset_code', 'reset_code_expire'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
