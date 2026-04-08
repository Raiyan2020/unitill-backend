<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('public_id')->unique()->comment('معرف الإعلان الظاهر للمستخدم');
            $table->string('title')->comment('عنوان رئيسي');
            $table->string('subtitle')->nullable()->comment('عنوان فرعي');
            $table->text('description')->nullable();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->foreignId('main_category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('sub_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('cover_image')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->boolean('is_negotiable')->default(false)->comment('قابل للتفاوض');
            $table->boolean('is_verified')->default(false)->comment('تم التحقق');
            $table->string('slug')->nullable()->unique();
            $table->enum('status', ['draft', 'pending', 'published', 'rejected', 'sold', 'expired'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['main_category_id', 'sub_category_id']);
            $table->index(['country_id', 'city_id']);
            $table->index('status');
        });

        Schema::create('ad_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('category_attribute_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete()->comment('القسم الذي تظهر فيه هذه الحقول (رئيسي أو فرعي)');
            $table->string('slug', 100)->comment('مفتاح ثابت للـ API والتطبيق');
            $table->enum('input_type', ['string', 'number', 'boolean', 'select'])->default('string');
            $table->json('options')->nullable()->comment('لـ select: قائمة الخيارات [{value,label},...]');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category_id', 'slug']);
        });

        Schema::create('category_attribute_definition_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_attribute_definition_id');
            $table->foreign('category_attribute_definition_id', 'fk_cad_trans_def')
                ->references('id')
                ->on('category_attribute_definitions')
                ->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete();
            $table->string('label')->comment('اسم الحقل للواجهة');
            $table->timestamps();

            $table->unique(['category_attribute_definition_id', 'language_id'], 'cad_trans_def_lang_uniq');
        });

        Schema::create('ad_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('category_attribute_definition_id');
            $table->foreign('category_attribute_definition_id', 'fk_adv_val_cad')
                ->references('id')
                ->on('category_attribute_definitions')
                ->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['ad_id', 'category_attribute_definition_id'], 'adv_val_ad_cad_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_attribute_values');
        Schema::dropIfExists('category_attribute_definition_translations');
        Schema::dropIfExists('category_attribute_definitions');
        Schema::dropIfExists('ad_images');
        Schema::dropIfExists('ads');
    }
};
