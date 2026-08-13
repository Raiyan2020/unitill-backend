<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\PushNotificationService;
use App\Support\MobileAuthTokenService;
use Illuminate\Console\Command;

/**
 * Notifies active students whose annual reconfirmation deadline has passed, asking
 * them to re-verify their student status in the app. Runs daily; only notifies
 * each user once per deadline (tracked by reverify_notified_at). Messaging is
 * gated separately after the 60-day grace period.
 */
class RequireStudentReverification extends Command
{
    protected $signature = 'students:require-reverification';

    protected $description = 'Notify students whose reconfirmation deadline has passed to re-verify';

    public function handle(
        PushNotificationService $pushNotifications,
        MobileAuthTokenService $tokens,
    ): int
    {
        $due = User::query()
            ->where('status', '1')
            ->whereNotNull('student_reverify_due_at')
            ->where('student_reverify_due_at', '<=', now())
            ->whereNotNull('student_email')
            ->where(function ($q) {
                $q->whereNull('reverify_notified_at')
                    ->orWhereColumn('reverify_notified_at', '<', 'student_reverify_due_at');
            })
            ->get();

        foreach ($due as $user) {
            $pushNotifications->notifyUser(
                $user,
                'Reconfirm your UniTill student status',
                'Please verify your university email within 60 days to keep student features active.',
                [
                    'type' => 'student_reverification',
                    'student_reverify_due_at' => $user->student_reverify_due_at?->toIso8601String(),
                ],
                UserNotification::TYPE_SYSTEM,
                'notify_system',
            );

            $user->forceFill(['reverify_notified_at' => now()])->save();
        }

        $graceCutoff = now()->subDays(
            (int) config('mobile_auth.student_reverification_grace_days', 60)
        );

        $expired = User::query()
            ->where('status', '1')
            ->whereNotNull('student_reverify_due_at')
            ->where('student_reverify_due_at', '<=', $graceCutoff)
            ->whereNotNull('student_email')
            ->get();

        foreach ($expired as $user) {
            // Status 2 reuses the existing "student verification required"
            // login path. All current sessions are revoked immediately.
            $user->forceFill(['status' => '2'])->save();
            $tokens->revokeAllForUser($user);
        }

        $this->info("Reverification notifications sent: {$due->count()}");
        $this->info("Students logged out after grace period: {$expired->count()}");

        return self::SUCCESS;
    }
}
