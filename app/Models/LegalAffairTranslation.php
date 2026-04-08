<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalAffairTranslation extends Model
{
    protected $fillable = [
        'legal_affair_id',
        'language_id',
        'title',
        'subtitle',
        'description',
    ];

    public function legalAffair(): BelongsTo
    {
        return $this->belongsTo(LegalAffair::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
