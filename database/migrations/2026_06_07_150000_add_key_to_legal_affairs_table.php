<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_affairs', function (Blueprint $table) {
            $table->string('key', 100)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('legal_affairs', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn('key');
        });
    }
};
