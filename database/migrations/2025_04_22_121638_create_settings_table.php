<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key_id')->unique();     // مفتاح الإعداد
            $table->string('title_en')->nullable(); // العنوان بالإنجليزي
            $table->string('title_ar')->nullable(); // العنوان بالعربي
            $table->text('value')->nullable();      // القيمة
            $table->string('set_group')->nullable(); // مجموعة الإعداد
            $table->boolean('is_object')->default(false); // هل القيمة JSON/كائن؟
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
