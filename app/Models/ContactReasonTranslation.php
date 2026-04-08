<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactReasonTranslation extends Model
{
    protected $fillable = [
        'contact_reason_id',
        'language_id',
        'name',
    ];

    public function contactReason(): BelongsTo
    {
        return $this->belongsTo(ContactReason::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
