<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserModerationService;
use Illuminate\Console\Command;

/**
 * Reactivates users whose temporary suspension has passed its suspended_until.
 * Permanent suspensions have no suspended_until and are untouched — an admin
 * (or an accepted appeal) must reactivate those explicitly.
 */
class ExpireModerationSuspensions extends Command
{
    protected $signature = 'moderation:expire-suspensions';

    protected $description = 'Reactivate users whose temporary suspension period has ended';

    public function handle(UserModerationService $moderation): int
    {
        $due = User::query()
            ->where('status', '3')
            ->whereNotNull('suspended_until')
            ->where('suspended_until', '<=', now())
            ->get();

        $count = 0;
        foreach ($due as $user) {
            if ($moderation->restoreExpiredSuspension($user)) {
                $count++;
            }
        }

        $this->info("Reactivated {$count} user(s) after their suspension expired.");

        return self::SUCCESS;
    }
}
