<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdAttributeValue extends Model
{
    protected $fillable = [
        'ad_id',
        'category_attribute_definition_id',
        'value',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CategoryAttributeDefinition::class, 'category_attribute_definition_id');
    }
}
