<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Localized labels for select/multiselect option values: a value => label map
 * per language, beside the attribute label it belongs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_attribute_definition_translations', function (Blueprint $table) {
            $table->json('options')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('category_attribute_definition_translations', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
