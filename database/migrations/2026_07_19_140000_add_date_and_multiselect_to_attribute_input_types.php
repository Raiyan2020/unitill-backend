<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * الحقول الجديدة تحتاج نوعين إضافيين:
     * date        => تاريخ الإتاحة في قسم السكن
     * multiselect => المميزات (موقف سيارات / حديقة / شرفة)
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
        // أي تعريف يستخدم النوعين الجديدين يعود إلى select قبل تقليص الـ enum
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
