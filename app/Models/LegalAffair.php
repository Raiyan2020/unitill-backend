<?php

namespace App\Models;

use App\Models\Concerns\ResolvesTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalAffair extends Model
{
    use ResolvesTranslations;

    protected $fillable = [
        'key',
        'section',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(LegalAffairTranslation::class);
    }

    public function titleForLanguageCode(string $code): string
    {
        return (string) $this->translatedValue($code, 'title', '');
    }

    /**
     * The best translation row for a language code, used by LegalAffairResource
     * so title/subtitle/description all come from one consistent row.
     */
    public function translationRowFor(string $code): ?LegalAffairTranslation
    {
        return $this->translationForLanguageCode(
            $code,
            static fn ($row) => trim((string) ($row->title ?? '')) !== ''
        );
    }
}
