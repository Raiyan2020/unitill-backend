<?php

namespace App\Services;

use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;

class PushNotificationService
{
    public function __construct(protected FirebaseService $firebase) {}

    public function allUsersTopic(): string
    {
        return (string) config('fcm.all_users_topic', 'unitill_all');
    }

    public function marketingTopic(): string
    {
        return (string) config('fcm.marketing_topic', 'unitill_marketing');
    }

    public function syncUserTopicSubscription(User $user, ?string $deviceToken = null): void
    {
        $token = $deviceToken ?: $user->device_token;

        if (! $token || ! $this->firebase->isConfigured()) {
            return;
        }

        $topic = $this->allUsersTopic();

        if ($user->notify_system) {
            $this->firebase->subscribeTokenToTopic($token, $topic);
        } else {
            $this->firebase->unsubscribeTokenFromTopic($token, $topic);
        }

        $marketingTopic = $this->marketingTopic();

        if ($user->notify_marketing) {
            $this->firebase->subscribeTokenToTopic($token, $marketingTopic);
        } else {
            $this->firebase->unsubscribeTokenFromTopic($token, $marketingTopic);
        }
    }

    public function sendToAll(string $title, string $body, array $data = [], ?int $adminId = null): PushNotification
    {
        $topic = $this->allUsersTopic();
        $result = $this->firebase->sendNotificationToTopic($topic, $title, $body, $data);

        $recipientsCount = User::query()
            ->where('status', '1')
            ->whereNotNull('device_token')
            ->where('notify_system', true)
            ->count();

        $log = PushNotification::query()->create([
            'admin_id' => $adminId,
            'audience' => PushNotification::AUDIENCE_ALL,
            'topic' => $topic,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
            'status' => $result['success'] ? PushNotification::STATUS_SENT : PushNotification::STATUS_FAILED,
            'fcm_message_id' => self::asText($result['message_id'] ?? null),
            'error_message' => self::asText($result['error'] ?? null),
            'recipients_count' => $recipientsCount,
        ]);

        $this->createInboxForAllUsers($log);

        return $log;
    }

    /**
     * Marketing broadcast — unlike {@see sendToAll}, both the push and the
     * in-app inbox entry are restricted to users who gave separate marketing
     * consent (notify_marketing), never just notify_system.
     */
    public function sendMarketingToAll(string $title, string $body, array $data = [], ?int $adminId = null): PushNotification
    {
        $topic = $this->marketingTopic();
        $result = $this->firebase->sendNotificationToTopic($topic, $title, $body, $data);

        $recipientsCount = $this->estimatedMarketingAudienceCount();

        $log = PushNotification::query()->create([
            'admin_id' => $adminId,
            'audience' => PushNotification::AUDIENCE_MARKETING,
            'topic' => $topic,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
            'status' => $result['success'] ? PushNotification::STATUS_SENT : PushNotification::STATUS_FAILED,
            'fcm_message_id' => self::asText($result['message_id'] ?? null),
            'error_message' => self::asText($result['error'] ?? null),
            'recipients_count' => $recipientsCount,
        ]);

        $this->createInboxForMarketingUsers($log);

        return $log;
    }

    public function sendToUser(User $user, string $title, string $body, array $data = [], ?int $adminId = null): PushNotification
    {
        if (! $user->device_token) {
            $log = PushNotification::query()->create([
                'admin_id' => $adminId,
                'audience' => PushNotification::AUDIENCE_USER,
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'data' => $data ?: null,
                'status' => PushNotification::STATUS_FAILED,
                'error_message' => 'User has no FCM device token',
                'recipients_count' => 0,
            ]);

            $this->createInboxForUser($user->id, $log);

            return $log;
        }

        if (! $user->notify_system) {
            $log = PushNotification::query()->create([
                'admin_id' => $adminId,
                'audience' => PushNotification::AUDIENCE_USER,
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'data' => $data ?: null,
                'status' => PushNotification::STATUS_FAILED,
                'error_message' => 'User has disabled system notifications',
                'recipients_count' => 0,
            ]);

            $this->createInboxForUser($user->id, $log);

            return $log;
        }

        $result = $this->firebase->sendNotificationToToken(
            $user->device_token,
            $title,
            $body,
            $data
        );

        $log = PushNotification::query()->create([
            'admin_id' => $adminId,
            'audience' => PushNotification::AUDIENCE_USER,
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
            'status' => $result['success'] ? PushNotification::STATUS_SENT : PushNotification::STATUS_FAILED,
            'fcm_message_id' => self::asText($result['message_id'] ?? null),
            'error_message' => self::asText($result['error'] ?? null),
            'recipients_count' => $result['success'] ? 1 : 0,
        ]);

        $this->createInboxForUser($user->id, $log);

        return $log;
    }

    /**
     * Send an app-event notification (chat, order, rating, …) to one user.
     *
     * Always stores an inbox record so it shows in the in-app bell, and pushes
     * an FCM message when the user has a device token and the given preference
     * flag is enabled. Unlike {@see sendToUser} this does not create an admin
     * PushNotification log row.
     *
     * @param  string  $preferenceFlag  User column gating the push (e.g.
     *                                   notify_ads, notify_system). Pass '' to
     *                                   always push when a token exists.
     */
    public function notifyUser(
        User $user,
        string $title,
        string $body,
        array $data = [],
        string $type = UserNotification::TYPE_SYSTEM,
        string $preferenceFlag = 'notify_system',
    ): UserNotification {
        $inbox = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
        ]);

        $allowsPush = $preferenceFlag === '' || (bool) ($user->{$preferenceFlag} ?? true);

        if ($user->device_token && $allowsPush && $this->firebase->isConfigured()) {
            $this->firebase->sendNotificationToToken(
                $user->device_token,
                $title,
                $body,
                $data,
            );
        }

        return $inbox;
    }

    public function estimatedAllAudienceCount(): int
    {
        return User::query()
            ->where('status', '1')
            ->whereNotNull('device_token')
            ->where('notify_system', true)
            ->count();
    }

    public function estimatedMarketingAudienceCount(): int
    {
        return User::query()
            ->where('status', '1')
            ->whereNotNull('device_token')
            ->where('notify_marketing', true)
            ->count();
    }

    protected function createInboxForAllUsers(PushNotification $log): void
    {
        $this->createInboxForUsers(User::query()->where('status', '1'), $log);
    }

    protected function createInboxForMarketingUsers(PushNotification $log): void
    {
        $this->createInboxForUsers(
            User::query()->where('status', '1')->where('notify_marketing', true),
            $log
        );
    }

    protected function createInboxForUsers($query, PushNotification $log): void
    {
        $now = now();

        $query->select('id')
            ->chunkById(200, function ($users) use ($log, $now) {
                $rows = $users->map(fn (User $user) => [
                    'user_id' => $user->id,
                    'push_notification_id' => $log->id,
                    'type' => UserNotification::TYPE_ADMIN,
                    'title' => $log->title,
                    'body' => $log->body,
                    'data' => $log->data ? json_encode($log->data) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                UserNotification::query()->insert($rows);
            });
    }

    protected function createInboxForUser(int $userId, PushNotification $log): void
    {
        UserNotification::query()->create([
            'user_id' => $userId,
            'push_notification_id' => $log->id,
            'type' => UserNotification::TYPE_ADMIN,
            'title' => $log->title,
            'body' => $log->body,
            'data' => $log->data,
        ]);
    }

    /**
     * Text columns must never receive an array: a driver-level
     * "Array to string conversion" would fail the whole request, so a status
     * change would appear broken because of a logging detail.
     */
    protected static function asText(mixed $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: null;
    }
}
