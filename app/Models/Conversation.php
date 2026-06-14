<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = [
        'ad_id',
        'buyer_id',
        'seller_id',
        'status',
        'archived_reason',
        'archived_at',
        'last_message_preview',
        'last_message_at',
        'buyer_deleted_at',
        'seller_deleted_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'last_message_at' => 'datetime',
        'buyer_deleted_at' => 'datetime',
        'seller_deleted_at' => 'datetime',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ChatReport::class);
    }

    public function isParticipant(int $userId): bool
    {
        return $this->buyer_id === $userId || $this->seller_id === $userId;
    }

    public function otherParticipantId(int $userId): ?int
    {
        if ($this->buyer_id === $userId) {
            return $this->seller_id;
        }

        if ($this->seller_id === $userId) {
            return $this->buyer_id;
        }

        return null;
    }

    public function otherParticipant(int $userId): ?User
    {
        $otherId = $this->otherParticipantId($userId);

        if (! $otherId) {
            return null;
        }

        if ($this->buyer_id === $otherId) {
            return $this->relationLoaded('buyer') ? $this->buyer : $this->buyer()->first();
        }

        return $this->relationLoaded('seller') ? $this->seller : $this->seller()->first();
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function canSendMessages(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $ad = $this->relationLoaded('ad') ? $this->ad : $this->ad()->first(['id', 'status']);

        return $ad && $ad->status === 'published';
    }

    public function unreadCountFor(int $userId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function scopeVisibleToUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where(function ($inner) use ($userId) {
                $inner->where('buyer_id', $userId)->whereNull('buyer_deleted_at');
            })->orWhere(function ($inner) use ($userId) {
                $inner->where('seller_id', $userId)->whereNull('seller_deleted_at');
            });
        });
    }
}
