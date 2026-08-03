<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegalAffairResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // The full language code is honoured here: collapsing it to en/ar was
        // why fr/es/zh always rendered the English policies.
        $lang = (string) $request->header('lang', 'en');
        $locale = $lang === 'ar' ? 'ar' : 'en';
        $translation = $this->resource->translationRowFor($lang);

        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $translation?->title ?? '',
            'subtitle' => $translation?->subtitle,
            'points' => $this->parsePoints($translation?->description),
            'last_updated_at' => $this->updated_at?->toIso8601String(),
            'last_updated_label' => $this->updated_at
                ? ($locale === 'ar'
                    ? 'آخر تحديث: '.$this->updated_at->locale($locale)->translatedFormat('M Y')
                    : 'LAST UPDATED: '.strtoupper($this->updated_at->format('M Y')))
                : null,
        ];
    }

    protected function parsePoints(?string $description): array
    {
        if ($description === null || trim($description) === '') {
            return [];
        }

        $decoded = json_decode($description, true);
        if (is_array($decoded)) {
            return collect($decoded)
                ->filter(fn ($text) => trim((string) $text) !== '')
                ->values()
                ->map(fn ($text, $index) => [
                    'number' => $index + 1,
                    'text' => (string) $text,
                ])
                ->all();
        }

        return collect(preg_split("/\r\n|\n|\r/", $description))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->map(fn ($text, $index) => [
                'number' => $index + 1,
                'text' => $text,
            ])
            ->all();
    }
}
