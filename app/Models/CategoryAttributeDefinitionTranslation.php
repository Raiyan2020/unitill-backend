<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryAttributeDefinitionTranslation extends Model
{
    protected $fillable = [
        'category_attribute_definition_id',
        'language_id',
        'label',
        'options',
    ];

    protected $casts = [
        // value => localized label, for select/multiselect attributes.
        'options' => 'array',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CategoryAttributeDefinition::class, 'category_attribute_definition_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
