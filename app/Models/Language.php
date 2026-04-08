<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = [
        'code',
        'name',
        'native_name',
        'direction',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function countryTranslations()
    {
        return $this->hasMany(CountryTranslation::class);
    }

    public function cityTranslations()
    {
        return $this->hasMany(CityTranslation::class);
    }

    public function contactReasonTranslations()
    {
        return $this->hasMany(ContactReasonTranslation::class);
    }

    public function categoryTranslations()
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function categoryAttributeDefinitionTranslations()
    {
        return $this->hasMany(CategoryAttributeDefinitionTranslation::class);
    }
}
