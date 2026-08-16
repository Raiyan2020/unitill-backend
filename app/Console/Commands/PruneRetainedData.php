<?php

namespace App\Console\Commands;

use App\Models\Ad;
use App\Models\AdReport;
use App\Models\ChatReport;
use App\Models\ContactReason;
use App\Models\ContactUsMessage;
use App\Models\Conversation;
use App\Models\ModerationAppeal;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserLoginLog;
use App\Models\UserModerationAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enforces the retention schedule set out in the platform's data-protection
 * policy. Each section below deletes (or, for ads, force-deletes past their
 * soft-delete grace period) rows once they are older than the period their
 * category is legally allowed to be kept for, counted from the date the
 * underlying case/record was closed rather than created where the two differ.
 *
 * Backups are excluded — those are infra-level (max 90 days, overwritten
 * automatically) and have no application-level record to prune here.
 */
class PruneRetainedData extends Command
{
    protected $signature = 'data:prune
                            {--dry-run : Report counts without deleting anything}';

    protected $description = 'Delete data past its legally required retention period';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $results = [
            'Login/security logs (12 months)' => $this->pruneLoginLogs($dryRun),
            'Inactive device tokens (90 days)' => $this->pruneInactiveDevices($dryRun),
            'Expired/deleted ads (12 months)' => $this->pruneAds($dryRun),
            'Ad reports (3 years after closed)' => $this->pruneAdReports($dryRun),
            'Chat reports (3 years after closed)' => $this->pruneChatReports($dryRun),
            'Moderation actions (3 years)' => $this->pruneModerationActions($dryRun),
            'Moderation appeals (3 years after resolved)' => $this->pruneModerationAppeals($dryRun),
            'Routine support requests (12 months)' => $this->pruneSupportMessages($dryRun),
            'Complaints/privacy requests (3 years)' => $this->pruneComplaintMessages($dryRun),
            'Permanently-deleted users (12 months after deletion)' => $this->prunePermanentlyDeletedUsers($dryRun),
            'Closed conversations (12 months)' => $this->pruneConversations($dryRun),
        ];

        foreach ($results as $label => $count) {
            $this->info(($dryRun ? '[dry-run] ' : '')."{$label}: {$count}");
        }

        if (! $dryRun) {
            Log::info('data:prune completed', $results);
        }

        return self::SUCCESS;
    }

    private function pruneLoginLogs(bool $dryRun): int
    {
        $query = UserLoginLog::where('created_at', '<', now()->subMonths(12));

        return $dryRun ? $query->count() : $query->delete();
    }

    private function pruneInactiveDevices(bool $dryRun): int
    {
        $query = UserDevice::where('is_active', false)
            ->where('updated_at', '<', now()->subDays(90));

        return $dryRun ? $query->count() : $query->delete();
    }

    /**
     * Ads become eligible once they have sat 12 months past their end state
     * (soft-deleted, or naturally expired/rejected) — unless an open ad
     * report still points at them, in which case they are kept until that
     * case is closed.
     */
    private function pruneAds(bool $dryRun): int
    {
        $openReportAdIds = AdReport::where('status', 'pending')->pluck('ad_id');

        $query = Ad::withTrashed()
            ->whereNotIn('id', $openReportAdIds)
            ->where(function ($q) {
                $q->where('deleted_at', '<', now()->subMonths(12))
                    ->orWhere(function ($inner) {
                        $inner->whereNull('deleted_at')
                            ->whereIn('status', ['expired', 'rejected'])
                            ->where('updated_at', '<', now()->subMonths(12));
                    });
            });

        if ($dryRun) {
            return $query->count();
        }

        $count = $query->count();
        $query->get()->each(fn (Ad $ad) => $ad->forceDelete());

        return $count;
    }

    private function pruneAdReports(bool $dryRun): int
    {
        $query = AdReport::whereIn('status', ['reviewed', 'dismissed'])
            ->where('updated_at', '<', now()->subYears(3));

        return $dryRun ? $query->count() : $query->delete();
    }

    private function pruneChatReports(bool $dryRun): int
    {
        $query = ChatReport::whereIn('status', ['reviewed', 'dismissed'])
            ->where('updated_at', '<', now()->subYears(3));

        return $dryRun ? $query->count() : $query->delete();
    }

    private function pruneModerationActions(bool $dryRun): int
    {
        $query = UserModerationAction::where('created_at', '<', now()->subYears(3));

        return $dryRun ? $query->count() : $query->delete();
    }

    private function pruneModerationAppeals(bool $dryRun): int
    {
        $query = ModerationAppeal::whereIn('status', ['accepted', 'rejected'])
            ->where('resolved_at', '<', now()->subYears(3));

        return $dryRun ? $query->count() : $query->delete();
    }

    private function pruneSupportMessages(bool $dryRun): int
    {
        $complaintReasonIds = ContactReason::where('sort_order', 2)->pluck('id');

        $query = ContactUsMessage::whereNotIn('contact_reason_id', $complaintReasonIds)
            ->where('created_at', '<', now()->subMonths(12));

        return $dryRun ? $query->count() : $query->delete();
    }

    private function pruneComplaintMessages(bool $dryRun): int
    {
        $complaintReasonIds = ContactReason::where('sort_order', 2)->pluck('id');

        $query = ContactUsMessage::whereIn('contact_reason_id', $complaintReasonIds)
            ->where('created_at', '<', now()->subYears(3));

        return $dryRun ? $query->count() : $query->delete();
    }

    /**
     * A soft-deleted user is the "permanent deletion" event for retention
     * purposes; the row (and the student-verification fields on it) is force
     * deleted 12 months after that. Cascades already leave the legally
     * retained records (orders, reports, ratings left, login logs,
     * moderation history, conversations) behind with a null owner.
     */
    private function prunePermanentlyDeletedUsers(bool $dryRun): int
    {
        $query = User::onlyTrashed()->where('deleted_at', '<', now()->subMonths(12));

        if ($dryRun) {
            return $query->count();
        }

        $count = $query->count();
        $query->get()->each(fn (User $user) => $user->forceDelete());

        return $count;
    }

    /**
     * A conversation is "closed" once both participants have deleted their
     * side, or it was explicitly archived — kept while an open chat report
     * still references it.
     */
    private function pruneConversations(bool $dryRun): int
    {
        $openReportConversationIds = ChatReport::where('status', 'pending')
            ->whereNotNull('conversation_id')
            ->pluck('conversation_id');

        $cutoff = now()->subMonths(12);

        $query = Conversation::whereNotIn('id', $openReportConversationIds)
            ->where(function ($q) use ($cutoff) {
                $q->where(function ($inner) use ($cutoff) {
                    $inner->whereNotNull('buyer_deleted_at')
                        ->whereNotNull('seller_deleted_at')
                        ->where('buyer_deleted_at', '<', $cutoff)
                        ->where('seller_deleted_at', '<', $cutoff);
                })->orWhere(function ($inner) use ($cutoff) {
                    $inner->where('status', 'archived')
                        ->where('archived_at', '<', $cutoff);
                });
            });

        return $dryRun ? $query->count() : $query->delete();
    }
}
