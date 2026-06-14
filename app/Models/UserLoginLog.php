<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoginLog extends Model
{
    public const TYPE_DATA = 'data';

    public const TYPE_FINGERPRINT = 'fingerprint';

    protected $fillable = [
        'user_id',
        'type',
        'device_identifier',
        'device_name',
        'city_name',
        'country_code',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
