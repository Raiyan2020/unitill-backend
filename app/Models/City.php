<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
        'status',
        'code',
        'sort',
        'country_code',
        'country_id',
    ];
    protected $casts = [
        'sort' => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CityTranslation::class);
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
        if ($code === 'en' && $this->name_en) {
            return $this->name_en;
        }
        if ($code === 'ar' && $this->name_ar) {
            return $this->name_ar;
        }

        return $this->name_ar ?? $this->name_en ?? '';
    }

    public function users()
    {
        return $this->hasMany(User::class, 'city_id');
    }

    public function ads()
    {
        return $this->hasMany(Ad::class);
    }

}
