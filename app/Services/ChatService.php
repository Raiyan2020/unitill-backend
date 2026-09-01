<?php

namespace App\Services;

use App\Events\ConversationArchived;
use App\Events\ConversationUpdated;
use App\Events\MessageSent;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function __construct(protected FirebaseService $firebase) {}

    /**
     * A fully verified account: status "1". Status "2" is still awaiting student
     * email verification and "3" is disabled.
     *
     * Note: the 30 Sep / 31 Mar re-verification deadlines from the spec are not
     * modelled anywhere yet, so an account that has lapsed still reads as "1"
     * until that is implemented.
     */
    protected function isFullyVerified(User $user): bool
    {
        return (string) $user->status === '1';
    }

    public function findOrCreateConversation(Ad $ad, User $buyer): Conversation
    {
        if ($ad->user_id === $buyer->id) {
            throw new \InvalidArgumentException('cannot_message_own_ad');
        }

        // The seller may have closed their account since the ad was cached by
        // the client, so a new thread must not be opened against them.
        $seller = User::whereKey($ad->user_id)->first();
        if (! $seller) {
            throw new \InvalidArgumentException('seller_unavailable');
        }

        // "Only verified users can message me": when the seller has opted in, the
        // buyer must be fully active — not pending student verification and not
        // disabled. Checked only for new threads, so an existing conversation is
        // never cut off midway by a status change.
        if ($seller->trusted_users_only && ! $this->isFullyVerified($buyer)) {
            throw new \InvalidArgumentException('buyer_not_verified');
        }

        $conversation = Conversation::query()->firstOrCreate(
            [
                'ad_id' => $ad->id,
                'buyer_id' => $buyer->id,
            ],
            [
                'seller_id' => $ad->user_id,
                'status' => 'active',
            ]
        );

        if ($conversation->buyer_id === $buyer->id) {
            $conversation->buyer_deleted_at = null;
        }

        if ($conversation->seller_id === $buyer->id) {
            $conversation->seller_deleted_at = null;
        }

        if ($conversation->isDirty()) {
            $conversation->save();
        }

        return $conversation->load([
            'ad:id,public_id,title,cover_image,price,currency,status,user_id',
            'buyer:id,first_name,last_name,name,image,last_seen_at',
            'seller:id,first_name,last_name,name,image,last_seen_at',
        ]);
    }

    public function sendMessage(
        Conversation $conversation,
        User $sender,
        ?string $body,
        ?string $attachmentPath = null,
        ?string $attachmentType = null,
        ?string $clientMessageId = null,
    ): Message {
        if (! $conversation->isParticipant($sender->id)) {
            throw new \InvalidArgumentException('not_participant');
        }

        // Checked before the generic gate so the caller can tell "the other
        // person left" apart from "the item is sold".
        if (! $conversation->bothParticipantsExist()) {
            throw new \InvalidArgumentException('participant_unavailable');
        }

        if (! $conversation->canSendMessages()) {
            throw new \InvalidArgumentException('messaging_disabled');
        }

        // Drop invalid byte sequences before anything is stored: a client that
        // posts malformed UTF-8 otherwise poisons the whole conversations list,
        // because json_encode() fails on the entire response.
        $body = self::toValidUtf8(trim($body ?? ''));

        if ($body === '' && !$attachmentPath) {
            throw new \InvalidArgumentException('empty_message');
        }

        // A replayed send must never write a second row: if the client already
        // used this id here, hand back what the first attempt stored.
        if ($clientMessageId !== null) {
            $existing = $this->findByClientMessageId($conversation, $sender, $clientMessageId);

            if ($existing) {
                return $existing;
            }
        }

        $previewText = $body !== '' ? $body : ($attachmentType === 'image' ? '📷 Image' : '📎 Attachment');
        $preview = mb_strlen($previewText) > 120 ? mb_substr($previewText, 0, 117).'...' : $previewText;

        try {
            $message = DB::transaction(function () use ($conversation, $sender, $body, $attachmentPath, $attachmentType, $clientMessageId, $preview) {
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'body' => $body,
                    'attachment_path' => $attachmentPath,
                    'attachment_type' => $attachmentType,
                    'type' => 'text',
                    'client_message_id' => $clientMessageId,
                ]);

                $conversation->update([
                    'last_message_preview' => $preview,
                    'last_message_at' => $message->created_at,
                    'buyer_deleted_at' => null,
                    'seller_deleted_at' => null,
                ]);

                return $message->load('sender:id,first_name,last_name,name,image,last_seen_at');
            });
        } catch (QueryException $e) {
            // Two retries racing each other: the unique index rejects the loser,
            // which then reads back the row the winner committed.
            if ($clientMessageId !== null) {
                $existing = $this->findByClientMessageId($conversation, $sender, $clientMessageId);

                if ($existing) {
                    return $existing;
                }
            }

            throw $e;
        }

        // Everything below runs after the commit and is best-effort. The row is
        // already durable, so a Pusher outage or a dead FCM token must never
        // surface to the caller as a failed send.
        $this->broadcastQuietly(new MessageSent($message));

        $recipientId = $conversation->otherParticipantId($sender->id);

        if ($recipientId) {
            $this->broadcastQuietly(new ConversationUpdated($conversation->fresh([
                'ad', 'buyer', 'seller',
            ]), $recipientId));

            $this->notifyRecipient($conversation, $sender, $recipientId, $preview);
        }

        return $message;
    }

    protected function findByClientMessageId(Conversation $conversation, User $sender, string $clientMessageId): ?Message
    {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', $sender->id)
            ->where('client_message_id', $clientMessageId)
            ->with('sender:id,first_name,last_name,name,image,last_seen_at')
            ->first();
    }

    /**
     * Deliver an event without letting a transport failure escape. Realtime is
     * a side effect of a write that has already happened; it can be logged and
     * dropped, but it must not be able to fail the request that caused it.
     */
    protected function broadcastQuietly(object $event): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function sendSystemMessage(Conversation $conversation, string $body): Message
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => null,
            'body' => $body,
            'type' => 'system',
        ]);

        $conversation->update([
            'last_message_preview' => $body,
            'last_message_at' => $message->created_at,
        ]);

        return $message;
    }

    /**
     * Hide the conversation for one participant only. The other side keeps the
     * thread, so it is told to re-read the list rather than left showing stale
     * counters.
     */
    public function deleteForUser(Conversation $conversation, User $user): void
    {
        if ($conversation->buyer_id === $user->id) {
            $conversation->update(['buyer_deleted_at' => now()]);
        } elseif ($conversation->seller_id === $user->id) {
            $conversation->update(['seller_deleted_at' => now()]);
        } else {
            return;
        }

        $otherId = $conversation->otherParticipantId($user->id);

        if ($otherId) {
            $this->broadcastQuietly(new ConversationUpdated(
                $conversation->fresh(['ad', 'buyer', 'seller']),
                $otherId
            ));
        }
    }

    public function markAsRead(Conversation $conversation, User $user): int
    {
        if (! $conversation->isParticipant($user->id)) {
            return 0;
        }

        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function archiveConversation(Conversation $conversation, string $reason = 'sold'): void
    {
        if ($conversation->status === 'archived') {
            return;
        }

        $conversation->update([
            'status' => 'archived',
            'archived_reason' => $reason,
            'archived_at' => now(),
        ]);

        $systemMessage = 'THE DEAL IS CLOSED! This listing has been marked as sold. You can still view your message history.';

        $this->sendSystemMessage($conversation, $systemMessage);

        $this->broadcastQuietly(new ConversationArchived($conversation->fresh(), $systemMessage));

        foreach ([$conversation->buyer_id, $conversation->seller_id] as $userId) {
            $this->broadcastQuietly(new ConversationUpdated($conversation->fresh(['ad', 'buyer', 'seller']), $userId));
        }
    }

    public function archiveConversationsForAd(Ad $ad, string $reason = 'sold'): void
    {
        Conversation::query()
            ->where('ad_id', $ad->id)
            ->where('status', 'active')
            ->each(fn (Conversation $conversation) => $this->archiveConversation($conversation, $reason));
    }

    /**
     * @return array{conversation?: Conversation, error?: string}
     */
    public function unarchiveConversation(Conversation $conversation): array
    {
        if ($conversation->status !== 'archived') {
            return ['error' => 'not_archived'];
        }

        if ($conversation->archived_reason === 'sold') {
            return ['error' => 'sold_listing'];
        }

        $ad = $conversation->relationLoaded('ad')
            ? $conversation->ad
            : $conversation->ad()->first(['id', 'status']);

        if (! $ad || $ad->status !== 'published') {
            return ['error' => 'ad_not_available'];
        }

        $conversation->update([
            'status' => 'active',
            'archived_reason' => null,
            'archived_at' => null,
        ]);

        $conversation = $conversation->fresh(['ad', 'buyer', 'seller']);

        foreach ([$conversation->buyer_id, $conversation->seller_id] as $userId) {
            $this->broadcastQuietly(new ConversationUpdated($conversation, $userId));
        }

        return ['conversation' => $conversation];
    }

    protected function notifyRecipient(
        Conversation $conversation,
        User $sender,
        int $recipientId,
        string $preview,
    ): void {
        $recipient = User::query()->find($recipientId);

        if (! $recipient || ! $recipient->notify_chat || ! $recipient->device_token) {
            return;
        }

        $senderName = trim($sender->first_name.' '.$sender->last_name) ?: $sender->name;

        try {
            $this->firebase->sendNotificationToToken(
                $recipient->device_token,
                $senderName,
                $preview,
                [
                    // `type` + `related_data` drive the mobile tap-navigation, which
                    // opens the conversation by id. Extra keys kept for context.
                    'type' => 'chat',
                    'related_data' => (string) $conversation->id,
                    'conversation_id' => (string) $conversation->id,
                    'ad_id' => (string) $conversation->ad_id,
                ]
            );
        } catch (\Throwable $e) {
            // Same rule as broadcasting: an expired token or an FCM outage is
            // logged, never allowed to fail the send that triggered it.
            report($e);
        }
    }

    /**
     * Strips byte sequences that are not valid UTF-8, keeping every valid
     * character (Arabic, emoji, CJK) untouched.
     */
    public static function toValidUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
