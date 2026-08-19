<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes permanent account deletion possible without destroying the records that
 * must outlive the account.
 *
 * Every foreign key pointing at `users` and `ads` was ON DELETE CASCADE, so
 * hard-deleting a user would also erase their orders, the reports filed about
 * them, the ratings they left on other people, and the other participant's copy
 * of every conversation. Those are the legal, safety and third-party records the
 * deletion policy carves out.
 *
 * The columns below therefore become nullable ON DELETE SET NULL: the record
 * survives, stripped of its link to the deleted person. Everything not listed
 * here keeps its CASCADE and is genuinely destroyed with the account — ads,
 * images, favourites, devices, tokens, notifications, support messages and
 * trusted-seller applications.
 */
return new class extends Migration
{
    /**
     * table => [columns => referenced table]
     */
    private const RETAINED = [
        // Financial records: required for tax and accounting.
        'orders' => ['buyer_id' => 'users', 'seller_id' => 'users', 'ad_id' => 'ads'],
        'coupon_redemptions' => ['user_id' => 'users'],

        // Moderation and safety records.
        'ad_reports' => ['user_id' => 'users', 'ad_id' => 'ads'],
        'chat_reports' => ['reporter_id' => 'users', 'reported_user_id' => 'users'],

        // Ratings the user LEFT stay, because they form another member's public
        // score. Ratings they RECEIVED are not listed, so they cascade away with
        // the account they describe.
        'user_ratings' => ['rater_id' => 'users'],

        // Security / fraud history.
        'user_login_logs' => ['user_id' => 'users'],

        // The other participant's conversation, and the evidence a chat report
        // points at. Messages already null their sender_id.
        'conversations' => ['buyer_id' => 'users', 'seller_id' => 'users', 'ad_id' => 'ads'],
    ];

    public function up(): void
    {
        foreach (self::RETAINED as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $referenced) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

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
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $referenced) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

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
