<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = [
        'country_code',
        'status',
        'sort',
    ];
    protected $casts = [
        'sort' => 'integer',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(CountryTranslation::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
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
