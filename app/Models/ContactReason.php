<?php

namespace App\Models;

use App\Models\Concerns\ResolvesTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactReason extends Model
{
    use ResolvesTranslations;

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
        // Never "" — a blank chip cannot be read or chosen in the app.
        return (string) $this->translatedValue($code, 'name', '');
    }
}
