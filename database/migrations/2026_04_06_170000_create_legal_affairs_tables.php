<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_affairs', function (Blueprint $table) {
            $table->id();
            $table->string('section')->nullable()->comment('تجميع للواجهة: مثل policies / data_management');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('legal_affair_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_affair_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete();
            $table->string('title')->comment('عنوان رئيسي');
            $table->string('subtitle')->nullable()->comment('عنوان فرعي');
            $table->text('description')->nullable()->comment('وصف / النص الكامل');
            $table->timestamps();

            $table->unique(['legal_affair_id', 'language_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_affair_translations');
        Schema::dropIfExists('legal_affairs');
    }
};
