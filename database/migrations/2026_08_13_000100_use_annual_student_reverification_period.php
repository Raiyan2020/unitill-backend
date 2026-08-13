<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'student_verified_at') ||
            ! Schema::hasColumn('users', 'student_reverify_due_at')) {
            return;
        }

        $rolloutAt = Carbon::now();

        DB::table('users')
            ->whereNotNull('student_email')
            ->where('status', '1')
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($rolloutAt) {
                foreach ($users as $user) {
                    $verifiedAt = $user->student_verified_at
                        ? Carbon::parse($user->student_verified_at)
                        : Carbon::parse($user->created_at);

                    $dueAt = $verifiedAt->copy()->addMonthsNoOverflow(12);

                    // Do not immediately log out legacy users whose calculated
                    // annual date is already in the past. Their full grace
                    // period starts when this lifecycle is deployed.
                    if ($dueAt->lessThanOrEqualTo($rolloutAt)) {
                        $dueAt = $rolloutAt->copy();
                    }

                    DB::table('users')->where('id', $user->id)->update([
                        'student_verified_at' => $verifiedAt,
                        'student_reverify_due_at' => $dueAt,
                        'reverify_notified_at' => null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // The previous shared March/September deadlines cannot be reconstructed
        // reliably from per-user verification history.
    }
};
