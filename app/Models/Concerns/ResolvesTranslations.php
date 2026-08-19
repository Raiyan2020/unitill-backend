<?php

namespace App\Models\Concerns;

use App\Models\Language;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Shared translation lookup for every `*_translations` table.
 *
 * The chain is always: requested language → English → default language → any
 * row, and blank values are skipped — a missing translation must never surface
 * as an empty string or a raw column/slug, which the app cannot render.
 */
trait ResolvesTranslations
{
    protected static array $languageCache = [];

    protected static function languageByCode(?string $code): ?Language
    {
        $code = trim((string) $code);
        if ($code === '') {
            return null;
        }

        if (! array_key_exists($code, static::$languageCache)) {
            static::$languageCache[$code] = Language::query()->where('code', $code)->first();
        }

        return static::$languageCache[$code];
    }

    protected static function defaultLanguageCode(): ?string
    {
        if (! array_key_exists('__default', static::$languageCache)) {
            static::$languageCache['__default'] = Language::query()->where('is_default', true)->first();
        }

        return static::$languageCache['__default']?->code;
    }

    protected function translationRows(): Collection
    {
        return $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();
    }

    /** `$filled` decides what counts as usable, so blank rows continue the chain. */
    protected function translationForLanguageCode(string $code, ?callable $filled = null): ?Model
    {
        $rows = $this->translationRows();
        if ($rows->isEmpty()) {
            return null;
        }

        $filled ??= static fn ($row) => $row !== null;

        $candidates = array_values(array_unique(array_filter([
            $code,
            'en',
            static::defaultLanguageCode(),
        ])));

        foreach ($candidates as $candidate) {
            $language = static::languageByCode($candidate);
            if (! $language) {
                continue;
            }

            $row = $rows->firstWhere('language_id', $language->id);
            if ($row && $filled($row)) {
                return $row;
            }
        }

        return $rows->first($filled);
    }

    protected function translatedValue(string $code, string $column, ?string $default = null): ?string
    {
        $row = $this->translationForLanguageCode(
            $code,
            static fn ($candidate) => trim((string) ($candidate->{$column} ?? '')) !== ''
        );

        $value = $row?->{$column};

        return trim((string) $value) !== '' ? (string) $value : $default;
    }
}
