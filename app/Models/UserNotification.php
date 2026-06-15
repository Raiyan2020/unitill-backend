<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    public const TYPE_ADMIN = 'admin';

    public const TYPE_CHAT = 'chat';

    public const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'user_id',
        'push_notification_id',
        'type',
        'title',
        'body',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pushNotification(): BelongsTo
    {
        return $this->belongsTo(PushNotification::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
