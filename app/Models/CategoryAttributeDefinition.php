<?php

namespace App\Models;

use App\Models\Concerns\ResolvesTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CategoryAttributeDefinition extends Model
{
    use ResolvesTranslations;

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

    /** Falls back to a humanised slug, never the raw slug. */
    public function labelForLanguageCode(string $code): string
    {
        return (string) $this->translatedValue($code, 'label', $this->humanisedSlug());
    }

    /**
     * Options with localized labels. `value` is the raw key the app submits and
     * filters with and is never translated; labels come from the translation
     * row's `options` map, falling back to English and then to the value.
     */
    public function optionsForLanguageCode(string $code): array
    {
        $options = $this->options ?? [];
        if ($options === []) {
            return [];
        }

        $labels = $this->optionLabelMapForLanguageCode($code);
        $englishLabels = $code === 'en' ? $labels : $this->optionLabelMapForLanguageCode('en');

        return collect($options)
            ->map(function ($option) use ($labels, $englishLabels) {
                // Tolerate a plain string list as well as {value,label}.
                $value = is_array($option)
                    ? (string) ($option['value'] ?? $option['label'] ?? '')
                    : (string) $option;
                $stored = is_array($option) ? (string) ($option['label'] ?? '') : (string) $option;

                $label = $labels[$value]
                    ?? $englishLabels[$value]
                    ?? ($stored !== '' ? $stored : $value);

                return ['value' => $value, 'label' => (string) $label];
            })
            ->filter(fn (array $option) => $option['value'] !== '')
            ->values()
            ->all();
    }

    /** Localized label for one stored value; powers `value_label` on ad details. */
    public function optionLabelForLanguageCode(string $code, string $value): string
    {
        foreach ($this->optionsForLanguageCode($code) as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        // Free-text and numeric attributes: the user's input is the label.
        return $value;
    }

    protected function optionLabelMapForLanguageCode(string $code): array
    {
        $language = static::languageByCode($code);
        if (! $language) {
            return [];
        }

        $row = $this->translationRows()->firstWhere('language_id', $language->id);
        $map = $row?->options;

        return is_array($map) ? $map : [];
    }

    protected function humanisedSlug(): string
    {
        return Str::ucfirst(str_replace('_', ' ', (string) $this->slug));
    }
}
