<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdView extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'ad_id',
        'user_id',
        'guest_key',
        'viewed_on',
    ];

    protected $casts = [
        'viewed_on' => 'date',
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
