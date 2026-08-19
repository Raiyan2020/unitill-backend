<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Authenticates with a service-account JSON key (Cloud Translation API v3)
 * instead of a plain API key — the credentials file itself carries the
 * project id, so no separate GOOGLE_CLOUD_PROJECT setting is needed.
 */
class GoogleTranslateService
{
    private const SCOPE = 'https://www.googleapis.com/auth/cloud-translation';

    public function isConfigured(): bool
    {
        $path = config('services.google_translate.credentials_path');

        return (bool) $path && file_exists(base_path($path));
    }

    /**
     * Translates a single piece of text. Only ever called on the exact text
     * the user chose to translate — never automatic, never bulk.
     *
     * @return array{translated_text: string, detected_source_language: ?string}
     */
    public function translate(string $text, string $targetLanguage, ?string $sourceLanguage = null): array
    {
        $credentialsPath = $this->credentialsPath();
        $projectId = $this->projectId($credentialsPath);

        $payload = [
            'contents' => [$text],
            'targetLanguageCode' => $targetLanguage,
            'mimeType' => 'text/plain',
        ];
        if ($sourceLanguage) {
            $payload['sourceLanguageCode'] = $sourceLanguage;
        }

        $response = Http::withToken($this->accessToken($credentialsPath))
            ->post("https://translation.googleapis.com/v3/projects/{$projectId}/locations/global:translateText", $payload);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error.message') ?: 'Unable to translate the text.');
        }

        $translation = $response->json('translations.0');
        if (! $translation) {
            throw new RuntimeException('Translation service returned an unexpected response.');
        }

        return [
            'translated_text' => html_entity_decode($translation['translatedText'], ENT_QUOTES),
            'detected_source_language' => $translation['detectedLanguageCode'] ?? $sourceLanguage,
        ];
    }

    private function credentialsPath(): string
    {
        $path = config('services.google_translate.credentials_path');
        if (! $path || ! file_exists(base_path($path))) {
            throw new RuntimeException('Google Translate is not configured. Set GOOGLE_TRANSLATE_CREDENTIALS in the environment.');
        }

        return base_path($path);
    }

    private function projectId(string $credentialsPath): string
    {
        return Cache::remember('google_translate_project_id', now()->addDay(), function () use ($credentialsPath) {
            $json = json_decode((string) file_get_contents($credentialsPath), true);

            return $json['project_id']
                ?? throw new RuntimeException('Google Translate credentials file is missing project_id.');
        });
    }

    /**
     * Access tokens last ~1 hour; cached so every /translate call doesn't
     * redo the OAuth2 exchange.
     */
    private function accessToken(string $credentialsPath): string
    {
        return Cache::remember('google_translate_access_token', now()->addMinutes(50), function () use ($credentialsPath) {
            $credentials = new ServiceAccountCredentials(self::SCOPE, $credentialsPath);
            $token = $credentials->fetchAuthToken();

            return $token['access_token']
                ?? throw new RuntimeException('Unable to obtain a Google Translate access token.');
        });
    }
}
