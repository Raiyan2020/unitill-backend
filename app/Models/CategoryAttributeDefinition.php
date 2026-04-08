<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryAttributeDefinition extends Model
{
    protected $fillable = [
        'category_id',
        'slug',
        'input_type',
        'options',
        'sort_order',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryAttributeDefinitionTranslation::class);
    }

    public function adAttributeValues(): HasMany
    {
        return $this->hasMany(AdAttributeValue::class);
    }

    public function labelForLanguageCode(string $code): string
    {
        $language = Language::where('code', $code)->first();
        if ($language) {
            $t = $this->relationLoaded('translations')
                ? $this->translations->firstWhere('language_id', $language->id)
                : $this->translations()->where('language_id', $language->id)->first();
            if ($t) {
                return $t->label;
            }
        }

        return $this->slug;
    }
}
