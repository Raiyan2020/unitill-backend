<?php

namespace App\Support;

use Carbon\Carbon;

class StudentVerificationPeriod
{
    public static function dueAt(?Carbon $verifiedAt = null): Carbon
    {
        $verifiedAt = $verifiedAt ? $verifiedAt->copy() : Carbon::now();

        return $verifiedAt->addMonthsNoOverflow(
            (int) config('mobile_auth.student_reverification_months', 12)
        );
    }

    public static function graceEndsAt(Carbon $dueAt): Carbon
    {
        return $dueAt->copy()->addDays(
            (int) config('mobile_auth.student_reverification_grace_days', 60)
        );
    }
}
