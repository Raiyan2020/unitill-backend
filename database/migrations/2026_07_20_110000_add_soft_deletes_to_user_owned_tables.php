<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting an account soft-deletes the ads the user owns, so they disappear
 * from every query at once instead of each call site having to remember to
 * exclude them. Reactivating restores the same rows.
 *
 * Conversations are NOT included: they are shared with another participant and
 * already carry per-side buyer_deleted_at / seller_deleted_at columns, so only
 * the leaving user's copy is hidden.
 *
 * Reports, ratings, orders and login logs are deliberately excluded too: they
 * are moderation or counterparty records that must survive an account closing.
 */
return new class extends Migration
{
    private array $tables = ['ads'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes()->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
