<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('device_name')->nullable()->after('name');
            $table->string('device_identifier')->nullable()->after('device_name');
            $table->string('city_name')->nullable()->after('device_identifier');
            $table->string('country_code', 2)->nullable()->after('city_name');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['device_name', 'device_identifier', 'city_name', 'country_code']);
        });
    }
};
