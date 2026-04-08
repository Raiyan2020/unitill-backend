<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'image',
        'status',
        'filter_group_id',
        'sort',
    ];

    protected $casts = [
        'sort' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function filterGroup()
    {
        return $this->belongsTo(FilterGroup::class, 'filter_group_id');
    }

    public function isMainCategory(): bool
    {
        return $this->parent_id === null;
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

    public function getNameArAttribute(): string
    {
        return $this->nameForLanguageCode('ar');
    }

    public function getNameEnAttribute(): string
    {
        return $this->nameForLanguageCode('en');
    }

    public function attributeDefinitions(): HasMany
    {
        return $this->hasMany(CategoryAttributeDefinition::class)->orderBy('sort_order');
    }

    public function mainAds(): HasMany
    {
        return $this->hasMany(Ad::class, 'main_category_id');
    }

    public function subAds(): HasMany
    {
        return $this->hasMany(Ad::class, 'sub_category_id');
    }

    public function trustedSellerApplications(): HasMany
    {
        return $this->hasMany(TrustedSellerApplication::class);
    }
}
