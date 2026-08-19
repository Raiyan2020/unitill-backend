<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationAppeal extends Model
{
    protected $fillable = [
        'user_id', 'moderation_action_id', 'message', 'status', 'resolved_by',
        'decision_reason', 'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function action(): BelongsTo { return $this->belongsTo(UserModerationAction::class, 'moderation_action_id'); }
    public function resolver(): BelongsTo { return $this->belongsTo(Admin::class, 'resolved_by'); }
}
