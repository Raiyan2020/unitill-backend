<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactUsMessage extends Model
{
    protected $fillable = [
        'user_id',
        'contact_reason_id',
        'message',
    ];

    protected $casts = [
        // Set when the support-inbox notification actually left the server;
        // null with a mail_error means the row was stored but never mailed.
        'mail_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contactReason(): BelongsTo
    {
        return $this->belongsTo(ContactReason::class);
    }
}
