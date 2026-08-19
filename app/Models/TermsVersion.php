<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TermsVersion extends Model
{
    protected $fillable = [
        'version', 'title_en', 'title_ar', 'content_en', 'content_ar',
        'is_current', 'effective_at', 'published_by',
    ];

    protected $casts = ['is_current' => 'boolean', 'effective_at' => 'datetime'];

    public function acceptances(): HasMany
    {
        return $this->hasMany(TermsAcceptance::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'published_by');
    }
}
