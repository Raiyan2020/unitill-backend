<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushNotification extends Model
{
    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_USER = 'user';

    public const AUDIENCE_MARKETING = 'marketing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'admin_id',
        'audience',
        'user_id',
        'topic',
        'title',
        'body',
        'data',
        'status',
        'fcm_message_id',
        'error_message',
        'recipients_count',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
