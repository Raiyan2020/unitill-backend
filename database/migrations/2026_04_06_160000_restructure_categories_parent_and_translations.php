<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'sort')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->unsignedInteger('sort')->default(0)->after('id');
            });
        }

        if (! Schema::hasColumn('categories', 'parent_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('parent_id')->nullable()->after('sort')->constrained('categories')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('category_translations')) {
            Schema::create('category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['category_id', 'language_id']);
            });
        }

        if (Schema::hasColumn('categories', 'name_ar')) {
            $langs = DB::table('languages')->pluck('id', 'code');
            foreach (DB::table('categories')->get() as $row) {
                if (! empty($row->name_ar) && $langs->has('ar')) {
                    DB::table('category_translations')->insert([
                        'category_id' => $row->id,
                        'language_id' => $langs['ar'],
                        'name' => $row->name_ar,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                if (! empty($row->name_en) && $langs->has('en')) {
                    DB::table('category_translations')->insert([
                        'category_id' => $row->id,
                        'language_id' => $langs['en'],
                        'name' => $row->name_en,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn(['name_ar', 'name_en']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
        });

        Schema::dropIfExists('category_translations');

        if (Schema::hasColumn('categories', 'parent_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }

        if (Schema::hasColumn('categories', 'sort')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('sort');
            });
        }
    }
};
