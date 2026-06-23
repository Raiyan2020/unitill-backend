<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'buyer_id',
        'seller_id',
        'ad_id',
        'status',
        'amount',
        'currency',
        'payment_method',
        'notes',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }
}
