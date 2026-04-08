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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contactReason(): BelongsTo
    {
        return $this->belongsTo(ContactReason::class);
    }
}
