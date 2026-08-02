<?php

namespace App\Console\Commands;

use App\Models\Ad;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Flips published ads past their expires_at to the "expired" status.
 *
 * Hiding them from the public feed already happens instantly in
 * Ad::scopePublished. This command only corrects the stored status so the
 * ad moves into the "Inactive" tab of My Ads and the owner can see it ended.
 */
class ExpireAds extends Command
{
    /**
     * The Salman deployment scheduled this as ads:expire-old. Any cron entry or
     * ops runbook still using that name would silently stop expiring ads, so
     * keep it as an alias rather than renaming out from under them.
     */
    protected $aliases = ['ads:expire-old'];

    protected $signature = 'ads:expire
                            {--dry-run : List what would change without updating}';

    protected $description = 'Mark published ads whose expires_at has passed as expired';

    public function handle(): int
    {
        $query = Ad::query()->dueForExpiry();
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No ads to expire.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("[dry-run] {$count} ad(s) would be marked as expired:");
            (clone $query)
                ->select('id', 'title', 'expires_at')
                ->orderBy('expires_at')
                ->limit(20)
                ->get()
                ->each(fn (Ad $ad) => $this->line("  #{$ad->id}  {$ad->expires_at}  {$ad->title}"));

            return self::SUCCESS;
        }

        $updated = $query->update([
            'status' => 'expired',
            // MyAdResource renders inactive_reason / inactive_reason_label in
            // the "Inactive" tab. Leaving it null makes an auto-expired ad show
            // no reason at all, so the owner cannot tell why it stopped.
            'inactive_reason' => 'auto_expired',
            'updated_at' => now(),
        ]);

        $this->info("Marked {$updated} ad(s) as expired.");
        Log::info('ads:expire marked ads as expired', ['count' => $updated]);

        return self::SUCCESS;
    }
}
