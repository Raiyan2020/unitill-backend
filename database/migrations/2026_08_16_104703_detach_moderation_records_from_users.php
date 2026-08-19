<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * user_moderation_actions and moderation_appeals were left off the
 * 2026_08_11_000000 retained-records list, so they still CASCADE DELETE with
 * the account. That contradicts the moderation retention policy (3 years
 * after case closure, independent of whether the account still exists) —
 * without this, hard-deleting an account erases its own suspension/warning
 * history and any appeal filed against it. Same nullOnDelete treatment as
 * ad_reports/chat_reports there.
 */
return new class extends Migration
{
    private const RETAINED = [
        'user_moderation_actions' => ['user_id' => 'users'],
        'moderation_appeals' => ['user_id' => 'users'],
    ];

    public function up(): void
    {
        foreach (self::RETAINED as $table => $columns) {
            foreach ($columns as $column => $referenced) {
                Schema::table($table, function (Blueprint $blueprint) use ($table, $column, $referenced) {
                    $blueprint->dropForeign("{$table}_{$column}_foreign");
                    $blueprint->unsignedBigInteger($column)->nullable()->change();
                    $blueprint->foreign($column)->references('id')->on($referenced)->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::RETAINED as $table => $columns) {
            foreach ($columns as $column => $referenced) {
                // Rows detached by a purge have no owner to point back at, so
                // they must go before the column can be NOT NULL again.
                DB::table($table)->whereNull($column)->delete();

                Schema::table($table, function (Blueprint $blueprint) use ($table, $column, $referenced) {
                    $blueprint->dropForeign("{$table}_{$column}_foreign");
                    $blueprint->unsignedBigInteger($column)->nullable(false)->change();
                    $blueprint->foreign($column)->references('id')->on($referenced)->cascadeOnDelete();
                });
            }
        }
    }
};
