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
        if (! Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->string('country_code', 2)->unique()->comment('ISO 3166-1 alpha-2');
                //status
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
                $table->string('country_code', 2)->nullable()->comment('للتوافق مع البيانات القديمة');
                $table->string('image')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->string('code')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('cities', 'country_id')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->foreignId('country_id')->nullable()->after('id')->constrained('countries')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('country_translations')) {
            Schema::create('country_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->constrained()->cascadeOnDelete();
                $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();

                $table->unique(['country_id', 'language_id']);
            });
        }

        if (! Schema::hasTable('city_translations')) {
            Schema::create('city_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('city_id')->constrained()->cascadeOnDelete();
                $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();

                $table->unique(['city_id', 'language_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('city_translations');
        Schema::dropIfExists('country_translations');

        if (Schema::hasTable('cities') && Schema::hasColumn('cities', 'country_id')) {
            Schema::table('cities', function (Blueprint $table) {
                $table->dropForeign(['country_id']);
                $table->dropColumn('country_id');
            });
        }
    }
};
