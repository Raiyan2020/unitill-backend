<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'slug')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->string('slug', 160)->nullable()->after('parent_id');
            });
        }

        $englishLanguageId = DB::table('languages')->where('code', 'en')->value('id');
        $categories = DB::table('categories')->orderByRaw('parent_id IS NOT NULL')->orderBy('id')->get();
        $used = [];
        $slugs = [];

        foreach ($categories as $category) {
            $name = $englishLanguageId
                ? DB::table('category_translations')
                    ->where('category_id', $category->id)
                    ->where('language_id', $englishLanguageId)
                    ->value('name')
                : null;
            $base = Str::slug((string) ($name ?: 'category-'.$category->id));
            $parentSlug = $category->parent_id ? ($slugs[$category->parent_id] ?? null) : null;
            $candidate = $parentSlug ? $parentSlug.'-'.$base : $base;
            $slug = $candidate;
            $suffix = 2;

            while (isset($used[$slug])) {
                $slug = $candidate.'-'.$suffix++;
            }

            DB::table('categories')->where('id', $category->id)->update(['slug' => $slug]);
            $used[$slug] = true;
            $slugs[$category->id] = $slug;
        }

        if (! Schema::hasIndex('categories', 'categories_slug_unique')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->unique('slug');
            });
        }

        $accommodationId = DB::table('category_translations')
            ->when($englishLanguageId, fn ($query) => $query->where('language_id', $englishLanguageId))
            ->where('name', 'Accommodation')
            ->value('category_id');

        if (! $accommodationId) {
            return;
        }

        DB::table('category_attribute_definitions')
            ->where('category_id', $accommodationId)
            ->where('slug', 'availability_from')
            ->update(['input_type' => 'date', 'filter_control' => 'date', 'post_control' => 'date']);

        $from = DB::table('category_attribute_definitions')
            ->where('category_id', $accommodationId)
            ->where('slug', 'availability_from')
            ->first();

        $untilId = DB::table('category_attribute_definitions')
            ->where('category_id', $accommodationId)
            ->where('slug', 'availability_until')
            ->value('id');

        $untilValues = [
            'category_id' => $accommodationId,
            'slug' => 'availability_until',
            'input_type' => 'date',
            'filter_control' => 'date',
            'post_control' => 'date',
            'options' => json_encode([]),
            'config' => null,
            'sort_order' => $from ? ((int) $from->sort_order + 1) : 99,
            'is_required' => false,
            'is_filterable' => true,
            'is_postable' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($untilId) {
            DB::table('category_attribute_definitions')->where('id', $untilId)->update([
                'input_type' => 'date',
                'filter_control' => 'date',
                'post_control' => 'date',
                'is_active' => true,
                'updated_at' => now(),
            ]);
        } else {
            $untilId = DB::table('category_attribute_definitions')->insertGetId($untilValues);
        }

        $labels = [
            'en' => 'Available until',
            'ar' => 'متاح حتى',
            'fr' => "Disponible jusqu'au",
            'es' => 'Disponible hasta',
            'zh' => '可住至',
        ];

        foreach ($labels as $code => $label) {
            $languageId = DB::table('languages')->where('code', $code)->value('id');
            if ($languageId) {
                DB::table('category_attribute_definition_translations')->updateOrInsert([
                    'category_attribute_definition_id' => $untilId,
                    'language_id' => $languageId,
                ], [
                    'category_attribute_definition_id' => $untilId,
                    'language_id' => $languageId,
                    'label' => $label,
                    'options' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $accommodationId = DB::table('category_translations')->where('name', 'Accommodation')->value('category_id');
        if ($accommodationId) {
            DB::table('category_attribute_definitions')
                ->where('category_id', $accommodationId)
                ->where('slug', 'availability_until')
                ->delete();
        }

        if (Schema::hasColumn('categories', 'slug')) {
            Schema::table('categories', function (Blueprint $table): void {
                if (Schema::hasIndex('categories', 'categories_slug_unique')) {
                    $table->dropUnique(['slug']);
                }
                $table->dropColumn('slug');
            });
        }
    }
};
