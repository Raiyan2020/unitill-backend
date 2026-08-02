<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails active students whose term reconfirmation deadline has passed, asking
 * them to re-verify their student status in the app. Runs daily; only notifies
 * each user once per deadline (tracked by reverify_notified_at). Messaging is
 * gated separately via User::needsReverification().
 */
class RequireStudentReverification extends Command
{
    protected $signature = 'students:require-reverification';

    protected $description = 'Notify students whose reconfirmation deadline has passed to re-verify';

    public function handle(): int
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
            try {
                Mail::raw(
                    "Your UniTill student status needs reconfirmation.\n\n".
                    "Please open the UniTill app and reconfirm your university email to keep messaging enabled.",
                    function ($message) use ($user) {
                        $message->to($user->student_email)
                            ->subject('Reconfirm your UniTill student status');
                    }
                );
            } catch (\Throwable $e) {
                Log::error('Reverification notice mail failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $user->forceFill(['reverify_notified_at' => now()])->save();
        }

        $this->info("Reverification notices sent: {$due->count()}");

        return self::SUCCESS;
    }
}
