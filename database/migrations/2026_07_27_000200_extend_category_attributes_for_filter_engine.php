<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the dynamic attribute system into a typed filter/post engine:
 * - filter_control / post_control decide how each attribute renders in the
 *   filter panel vs the post-ad form (select, multiselect, range, stepper,
 *   toggle, date, radius, number...). Null falls back to input_type.
 * - config carries min/max/step/unit/radius_options metadata.
 * - is_filterable / is_postable gate where the attribute appears.
 *
 * Also replaces the (ad_id, definition_id) UNIQUE index with a plain one so a
 * multiselect attribute can store one row per selected value.
 *
 * Note on the index swap: adv_val_ad_cad_uniq is the leftmost-prefix index
 * backing the ad_id foreign key. Dropping it before another index covers ad_id
 * fails with errno 1553 ("needed in a foreign key constraint") — the Salman
 * tree hits exactly this and cannot migrate from scratch. Create the
 * replacement first, then drop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_attribute_definitions', function (Blueprint $table) {
            if (! Schema::hasColumn('category_attribute_definitions', 'filter_control')) {
                $table->string('filter_control', 20)->nullable()->after('input_type');
            }
            if (! Schema::hasColumn('category_attribute_definitions', 'post_control')) {
                $table->string('post_control', 20)->nullable()->after('filter_control');
            }
            if (! Schema::hasColumn('category_attribute_definitions', 'config')) {
                $table->json('config')->nullable()->after('options');
            }
            if (! Schema::hasColumn('category_attribute_definitions', 'is_filterable')) {
                $table->boolean('is_filterable')->default(true)->after('is_required');
            }
            if (! Schema::hasColumn('category_attribute_definitions', 'is_postable')) {
                $table->boolean('is_postable')->default(true)->after('is_filterable');
            }
        });

        if (! $this->indexExists('ad_attribute_values', 'adv_val_ad_cad_idx')) {
            Schema::table('ad_attribute_values', function (Blueprint $table) {
                $table->index(['ad_id', 'category_attribute_definition_id'], 'adv_val_ad_cad_idx');
            });
        }

        if ($this->indexExists('ad_attribute_values', 'adv_val_ad_cad_uniq')) {
            Schema::table('ad_attribute_values', function (Blueprint $table) {
                $table->dropUnique('adv_val_ad_cad_uniq');
            });
        }
    }

    public function down(): void
    {
        // Deliberately not restoring adv_val_ad_cad_uniq. Once any multiselect
        // attribute has stored more than one row for an ad, re-adding it throws
        // 1062 and aborts the rollback partway. Reversing this migration on a
        // database with real data is a manual, data-aware operation.
        Schema::table('category_attribute_definitions', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['filter_control', 'post_control', 'config', 'is_filterable', 'is_postable'],
                fn (string $c) => Schema::hasColumn('category_attribute_definitions', $c)
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(\DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($row) => $row->Key_name === $index);
    }
};
