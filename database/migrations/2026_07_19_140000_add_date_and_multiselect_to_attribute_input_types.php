<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The new attributes need two extra input types:
     * date        => Accommodation "available from"
     * multiselect => Features (Parking / Garden / Balcony)
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE category_attribute_definitions
             MODIFY COLUMN input_type
             ENUM('string', 'number', 'boolean', 'select', 'date', 'multiselect')
             NOT NULL DEFAULT 'string'"
        );
    }

    public function down(): void
    {
        // Definitions using the new types fall back to select before shrinking the enum
        DB::table('category_attribute_definitions')
            ->whereIn('input_type', ['date', 'multiselect'])
            ->update(['input_type' => 'select']);

        DB::statement(
            "ALTER TABLE category_attribute_definitions
             MODIFY COLUMN input_type
             ENUM('string', 'number', 'boolean', 'select')
             NOT NULL DEFAULT 'string'"
        );
    }
};
