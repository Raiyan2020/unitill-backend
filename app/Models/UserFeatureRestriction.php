<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeatureRestriction extends Model
{
    public const FEATURES = ['posting', 'messaging'];

    protected $fillable = [
        'user_id', 'admin_id', 'feature', 'reason', 'starts_at', 'ends_at',
        'lifted_at', 'lifted_by', 'lift_reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime', 'ends_at' => 'datetime', 'lifted_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('lifted_at')
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'lifted_by');
    }
}
