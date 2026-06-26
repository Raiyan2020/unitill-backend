<?php

namespace App\Services;

use App\Events\ConversationArchived;
use App\Events\ConversationUpdated;
use App\Events\MessageSent;
use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function __construct(protected FirebaseService $firebase) {}

    public function findOrCreateConversation(Ad $ad, User $buyer): Conversation
    {
        if ($ad->user_id === $buyer->id) {
            throw new \InvalidArgumentException('cannot_message_own_ad');
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
            'buyer:id,first_name,last_name,name,image',
            'seller:id,first_name,last_name,name,image',
        ]);
    }

    public function sendMessage(Conversation $conversation, User $sender, ?string $body, ?string $attachmentPath = null, ?string $attachmentType = null): Message
    {
        if (! $conversation->isParticipant($sender->id)) {
            throw new \InvalidArgumentException('not_participant');
        }

        if (! $conversation->canSendMessages()) {
            throw new \InvalidArgumentException('messaging_disabled');
        }

        $body = trim($body ?? '');

        if ($body === '' && !$attachmentPath) {
            throw new \InvalidArgumentException('empty_message');
        }

        return DB::transaction(function () use ($conversation, $sender, $body, $attachmentPath, $attachmentType) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $body,
                'attachment_path' => $attachmentPath,
                'attachment_type' => $attachmentType,
                'type' => 'text',
            ]);

            $previewText = $body !== '' ? $body : ($attachmentType === 'image' ? '📷 Image' : '📎 Attachment');
            $preview = mb_strlen($previewText) > 120 ? mb_substr($previewText, 0, 117).'...' : $previewText;

            $conversation->update([
                'last_message_preview' => $preview,
                'last_message_at' => $message->created_at,
                'buyer_deleted_at' => null,
                'seller_deleted_at' => null,
            ]);

            $message->load('sender:id,first_name,last_name,name,image');

            broadcast(new MessageSent($message));

            $recipientId = $conversation->otherParticipantId($sender->id);

            if ($recipientId) {
                broadcast(new ConversationUpdated($conversation->fresh([
                    'ad', 'buyer', 'seller',
                ]), $recipientId));

                $this->notifyRecipient($conversation, $sender, $recipientId, $preview);
            }

            return $message;
        });
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

        broadcast(new ConversationArchived($conversation->fresh(), $systemMessage));

        foreach ([$conversation->buyer_id, $conversation->seller_id] as $userId) {
            broadcast(new ConversationUpdated($conversation->fresh(['ad', 'buyer', 'seller']), $userId));
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
            broadcast(new ConversationUpdated($conversation, $userId));
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
    }
}
