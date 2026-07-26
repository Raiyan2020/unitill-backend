<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restores the student re-verification lifecycle that the Salman tree carried
 * and this one lost.
 *
 * Salman shipped these columns inside a migration that also created
 * university_domains. That create is unguarded and this repository already owns
 * that table (2026_07_16_100000), so copying the file verbatim aborts halfway
 * with 1050 after the ALTER has already landed — and MySQL cannot roll back
 * DDL, so the retry then fails with 1060. Only the column block is carried over
 * here; the table stays as this repository defines it.
 *
 * Every add is guarded so the migration is safe on a database that has already
 * seen a partially-applied Salman run.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> column => the column it should follow
     */
    private array $columns = [
        'student_verified_at' => 'student_email',
        'student_reverify_due_at' => 'student_verified_at',
        'reverify_notified_at' => 'student_reverify_due_at',
    ];

    public function up(): void
    {
        foreach ($this->columns as $column => $after) {
            if (Schema::hasColumn('users', $column)) {
                continue;
            }

            Schema::table('users', function (Blueprint $table) use ($column, $after) {
                $table->timestamp($column)->nullable()->after($after);
            });
        }

        // Students who already verified their university email predate the
        // lifecycle columns. Treat an activated student email as verified at
        // account creation so they are not all flagged for re-verification on
        // the first scheduled run.
        if (Schema::hasColumn('users', 'student_verified_at')) {
            \DB::table('users')
                ->whereNull('student_verified_at')
                ->whereNotNull('student_email')
                ->where('status', '1')
                ->update(['student_verified_at' => \DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        $existing = array_values(array_filter(
            array_keys($this->columns),
            fn (string $column) => Schema::hasColumn('users', $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }
};
