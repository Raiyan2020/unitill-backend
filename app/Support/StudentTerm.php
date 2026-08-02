<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * UK academic-term reconfirmation deadlines. Students must reconfirm their
 * status by the end of each term: 30 September and 31 March. This computes the
 * next such deadline strictly after a given moment.
 */
class StudentTerm
{
    /**
     * The next reconfirmation deadline strictly after $from (default: now).
     */
    public static function nextDeadline(?Carbon $from = null): Carbon
    {
        $from = $from ? $from->copy() : Carbon::now();
        $year = $from->year;

        $candidates = [
            Carbon::create($year, 3, 31)->endOfDay(),
            Carbon::create($year, 9, 30)->endOfDay(),
            Carbon::create($year + 1, 3, 31)->endOfDay(),
        ];

        foreach ($candidates as $deadline) {
            if ($deadline->greaterThan($from)) {
                return $deadline;
            }
        }

        return Carbon::create($year + 1, 9, 30)->endOfDay();
    }
}
