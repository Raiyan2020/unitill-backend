<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('universities', 'city_id')) {
            Schema::table('universities', function (Blueprint $table) {
                $table->foreignId('city_id')->nullable()->after('city')->constrained('cities')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('universities', 'city_id')) {
            Schema::table('universities', function (Blueprint $table) {
                $table->dropForeign(['city_id']);
                $table->dropColumn('city_id');
            });
        }
    }
};
