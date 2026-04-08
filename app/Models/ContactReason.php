<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactReason extends Model
{
    protected $fillable = [
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(ContactReasonTranslation::class);
    }

    public function nameForLanguageCode(string $code): string
    {
        $language = Language::where('code', $code)->first();
        if ($language) {
            $translation = $this->relationLoaded('translations')
                ? $this->translations->firstWhere('language_id', $language->id)
                : $this->translations()->where('language_id', $language->id)->first();
            if ($translation) {
                return $translation->name;
            }
        }

        return '';
    }
}
