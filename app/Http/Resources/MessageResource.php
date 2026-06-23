<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'body' => $this->body,
            'type' => $this->type,
            'attachment_url' => $this->attachment_url,
            'attachment_type' => $this->attachment_type,
            'sender_id' => $this->sender_id,
            'sender' => $this->sender
                ? new ChatParticipantResource($this->sender)
                : null,
            'is_mine' => $request->user()?->id === $this->sender_id,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
