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
        'filter_control',
        'post_control',
        'options',
        'config',
        'sort_order',
        'is_required',
        'is_filterable',
        'is_postable',
        'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'config' => 'array',
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'is_filterable' => 'boolean',
        'is_postable' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** How this attribute renders in the filter panel (falls back to input_type). */
    public function resolvedFilterControl(): string
    {
        return $this->filter_control ?: $this->input_type;
    }

    /** How this attribute renders in the post-ad form (falls back to input_type). */
    public function resolvedPostControl(): string
    {
        return $this->post_control ?: $this->input_type;
    }

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
