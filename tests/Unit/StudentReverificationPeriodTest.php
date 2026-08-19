<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\StudentVerificationPeriod;
use Carbon\Carbon;
use Tests\TestCase;

class StudentReverificationPeriodTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_due_date_is_twelve_months_after_the_users_verification_date(): void
    {
        config()->set('mobile_auth.student_reverification_months', 12);

        $verifiedAt = Carbon::parse('2026-01-31 10:30:00');

        $this->assertTrue(
            StudentVerificationPeriod::dueAt($verifiedAt)->equalTo('2027-01-31 10:30:00')
        );
    }

    public function test_user_is_not_blocked_during_the_sixty_day_grace_period(): void
    {
        config()->set('mobile_auth.student_reverification_grace_days', 60);
        Carbon::setTestNow('2027-02-15 12:00:00');

        $user = new User;
        $user->student_reverify_due_at = Carbon::parse('2027-01-31 10:30:00');

        $this->assertTrue($user->needsReverification());
        $this->assertTrue($user->isInReverificationGracePeriod());
        $this->assertFalse($user->isReverificationBlocked());
        $this->assertTrue(
            $user->reverificationGraceEndsAt()->equalTo('2027-04-01 10:30:00')
        );
    }

    public function test_user_is_blocked_when_the_grace_period_ends(): void
    {
        config()->set('mobile_auth.student_reverification_grace_days', 60);
        Carbon::setTestNow('2027-04-01 10:30:00');

        $user = new User;
        $user->student_reverify_due_at = Carbon::parse('2027-01-31 10:30:00');

        $this->assertTrue($user->needsReverification());
        $this->assertFalse($user->isInReverificationGracePeriod());
        $this->assertTrue($user->isReverificationBlocked());
    }
}
