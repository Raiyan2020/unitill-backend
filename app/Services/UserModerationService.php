<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserModerationAction;
use App\Models\UserNotification;
use App\Support\MobileAuthTokenService;

class UserModerationService
{
    public function __construct(
        protected MobileAuthTokenService $tokens,
        protected PushNotificationService $pushNotifications,
    ) {}

    public function apply(
        User $user,
        string $action,
        string $reason,
        ?int $adminId = null,
        ?int $durationDays = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): UserModerationAction {
        $startsAt = now();
        $endsAt = $action === 'temporary_suspension'
            ? now()->addDays((int) $durationDays)
            : null;

        if ($action === 'warning') {
            $user->increment('warning_count');
        } elseif (in_array($action, ['temporary_suspension', 'permanent_suspension'], true)) {
            $user->forceFill([
                'status' => '3',
                'suspended_until' => $endsAt,
            ])->save();
            $this->tokens->revokeAllForUser($user);
        } elseif ($action === 'reactivated') {
            $user->forceFill(['status' => '1', 'suspended_until' => null])->save();
            UserModerationAction::query()
                ->where('user_id', $user->id)
                ->whereIn('action', ['temporary_suspension', 'permanent_suspension'])
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);
        }

        $resolvedAt = match ($action) {
            'temporary_suspension' => $endsAt,
            'permanent_suspension' => null,
            default => now(),
        };

        $record = UserModerationAction::create([
            'user_id' => $user->id,
            'admin_id' => $adminId,
            'action' => $action,
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'resolved_at' => $resolvedAt,
        ]);

        $this->notify($user, $record);

        return $record;
    }

    public function restoreExpiredSuspension(User $user): bool
    {
        if ($user->status !== '3' || ! $user->suspended_until || $user->suspended_until->isFuture()) {
            return false;
        }

        $user->forceFill(['status' => '1', 'suspended_until' => null])->save();
        UserModerationAction::query()
            ->where('user_id', $user->id)
            ->where('action', 'temporary_suspension')
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
        UserModerationAction::create([
            'user_id' => $user->id,
            'action' => 'suspension_expired',
            'reason' => 'Temporary suspension period ended automatically.',
            'starts_at' => now(),
            'resolved_at' => now(),
        ]);

        return true;
    }

    protected function notify(User $user, UserModerationAction $record): void
    {
        $title = match ($record->action) {
            'warning' => 'Account warning',
            'temporary_suspension' => 'Account temporarily suspended',
            'permanent_suspension' => 'Account suspended',
            'reactivated' => 'Account reactivated',
            default => 'Account moderation update',
        };

        $this->pushNotifications->notifyUser(
            $user,
            $title,
            $record->reason,
            ['type' => 'moderation_action', 'action_id' => (string) $record->id],
            UserNotification::TYPE_SYSTEM,
            'notify_system',
        );
    }
}
