<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleTranslateService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.google_translate.api_key');
    }

    /**
     * Translates a single piece of text. Only ever called on the exact text
     * the user chose to translate — never automatic, never bulk.
     *
     * @return array{translated_text: string, detected_source_language: ?string}
     */
    public function translate(string $text, string $targetLanguage, ?string $sourceLanguage = null): array
    {
        $apiKey = config('services.google_translate.api_key');
        if (! $apiKey) {
            throw new RuntimeException('Google Translate is not configured. Set GOOGLE_TRANSLATE_API_KEY in the environment.');
        }

        $payload = [
            'q' => $text,
            'target' => $targetLanguage,
            'format' => 'text',
        ];
        if ($sourceLanguage) {
            $payload['source'] = $sourceLanguage;
        }

        $response = Http::asForm()->post(
            'https://translation.googleapis.com/language/translate/v2?key='.$apiKey,
            $payload
        );

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: 'Unable to translate the text.');
        }

        $translation = $response->json('data.translations.0');
        if (! $translation) {
            throw new RuntimeException('Translation service returned an unexpected response.');
        }

        return [
            'translated_text' => html_entity_decode($translation['translatedText'], ENT_QUOTES),
            'detected_source_language' => $translation['detectedSourceLanguage'] ?? $sourceLanguage,
        ];
    }
}
