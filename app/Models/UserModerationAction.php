<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserModerationAction extends Model
{
    protected $fillable = [
        'user_id', 'admin_id', 'action', 'reason', 'source_type', 'source_id',
        'starts_at', 'ends_at', 'resolved_at', 'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(ModerationAppeal::class, 'moderation_action_id');
    }
}
