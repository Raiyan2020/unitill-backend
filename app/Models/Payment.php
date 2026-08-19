<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'ad_id',
        'user_id',
        'type',
        'stripe_payment_intent_id',
        'amount',
        'currency',
        'status',
        'payment_method_type',
        'paid_at',
        'refund_status',
        'refund_reference',
        'refund_reason',
        'refund_requested_at',
        'refund_request_reason',
        'refunded_at',
        'refund_declined_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refund_requested_at' => 'datetime',
        'refunded_at' => 'datetime',
        'refund_declined_at' => 'datetime',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
