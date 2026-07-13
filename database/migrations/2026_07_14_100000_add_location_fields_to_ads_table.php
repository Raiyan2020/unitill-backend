<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds UK-postcode based location fields to ads. The postcode resolves (via
 * postcodes.io on the client) to an approximate area name plus coordinates,
 * which power the "approximate location" display and distance sorting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->string('postcode', 12)->nullable()->after('city_id');
            $table->string('location_name')->nullable()->after('postcode');
            $table->decimal('latitude', 10, 7)->nullable()->after('location_name');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropColumn(['postcode', 'location_name', 'latitude', 'longitude']);
        });
    }
};
