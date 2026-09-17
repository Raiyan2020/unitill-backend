<?php

namespace App\Services;

use App\Models\LegalAffair;

class LegalDocumentService
{
    /** @return array{locale:string,title:string,content:string,effective_at:?string}|null */
    public function get(string $key, ?string $requestedLocale): ?array
    {
        $requestedLocale = in_array($requestedLocale, ['en', 'ar', 'fr', 'es', 'zh'], true)
            ? $requestedLocale
            : 'en';

        $document = LegalAffair::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->with('translations.language')
            ->first();

        if (! $document) {
            return null;
        }

        $translation = $document->translations->first(
            fn ($row) => $row->language?->code === $requestedLocale
                && trim((string) $row->title) !== ''
                && trim((string) $row->description) !== ''
        );
        $locale = $requestedLocale;

        if (! $translation) {
            $translation = $document->translations->first(
                fn ($row) => $row->language?->code === 'en'
                    && trim((string) $row->title) !== ''
                    && trim((string) $row->description) !== ''
            );
            $locale = 'en';
        }

        if (! $translation) {
            return null;
        }

        $content = (string) $translation->description;
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $content = collect($decoded)
                ->map(fn ($paragraph) => trim((string) $paragraph))
                ->filter()
                ->implode("\n\n");
        }

        return [
            'locale' => $locale,
            'title' => (string) $translation->title,
            'content' => $content,
            'effective_at' => $document->updated_at?->toIso8601String(),
        ];
    }
}
