<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('published_at');
            $table->timestamp('paused_at')->nullable()->after('expires_at');
            $table->timestamp('sold_at')->nullable()->after('paused_at');
            $table->foreignId('sold_to_user_id')->nullable()->after('sold_at')->constrained('users')->nullOnDelete();
            $table->boolean('is_sold_outside')->default(false)->after('sold_to_user_id');
            $table->string('inactive_reason', 50)->nullable()->after('is_sold_outside');
        });

        DB::statement("ALTER TABLE ads MODIFY COLUMN status ENUM('draft', 'pending', 'published', 'rejected', 'sold', 'expired', 'paused') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sold_to_user_id');
            $table->dropColumn([
                'expires_at',
                'paused_at',
                'sold_at',
                'is_sold_outside',
                'inactive_reason',
            ]);
        });

        DB::statement("ALTER TABLE ads MODIFY COLUMN status ENUM('draft', 'pending', 'published', 'rejected', 'sold', 'expired') NOT NULL DEFAULT 'draft'");
    }
};
