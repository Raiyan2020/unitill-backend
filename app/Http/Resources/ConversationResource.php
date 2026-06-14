<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    protected ?int $viewerId = null;

    public function forViewer(?int $viewerId): self
    {
        $this->viewerId = $viewerId;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $viewerId = $this->viewerId ?? $request->user()?->id;
        $other = $viewerId ? $this->otherParticipant($viewerId) : null;
        $ad = $this->ad;
        $locale = $request->header('lang') === 'ar' ? 'ar' : 'en';

        return [
            'id' => $this->id,
            'status' => $this->status,
            'archived_reason' => $this->archived_reason,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'can_send_messages' => $this->canSendMessages(),
            'last_message_preview' => $this->last_message_preview,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_message_ago' => $this->last_message_at
                ? strtoupper($this->last_message_at->locale($locale)->diffForHumans(null, true)).' AGO'
                : null,
            'unread_count' => $viewerId ? $this->unreadCountFor($viewerId) : 0,
            'participant' => $other ? new ChatParticipantResource($other) : null,
            'ad' => $ad ? [
                'id' => $ad->id,
                'public_id' => $ad->public_id,
                'title' => $ad->title,
                'cover_image_url' => $ad->coverImageUrl(),
                'formatted_price' => $ad->formattedPrice(),
                'status' => $ad->status,
                'is_sold' => $ad->status === 'sold',
            ] : null,
            'buyer_id' => $this->buyer_id,
            'seller_id' => $this->seller_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
