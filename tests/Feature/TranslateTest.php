<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TranslateTest extends TestCase
{
    use RefreshDatabase;

    public function test_translates_the_exact_text_sent(): void
    {
        Config::set('services.google_translate.api_key', 'test-key');

        Http::fake([
            'translation.googleapis.com/*' => Http::response([
                'data' => [
                    'translations' => [
                        ['translatedText' => 'مرحبا', 'detectedSourceLanguage' => 'en'],
                    ],
                ],
            ]),
        ]);

        Sanctum::actingAs($this->user());

        $this->postJson('/api/translate', [
            'text' => 'Hello',
            'target' => 'ar',
        ])
            ->assertOk()
            ->assertJsonPath('data.translated_text', 'مرحبا')
            ->assertJsonPath('data.source_language', 'en')
            ->assertJsonPath('data.target_language', 'ar');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'translation.googleapis.com')
                && $request['q'] === 'Hello'
                && $request['target'] === 'ar';
        });
    }

    public function test_returns_503_when_google_translate_is_not_configured(): void
    {
        Config::set('services.google_translate.api_key', null);

        Sanctum::actingAs($this->user());

        $this->postJson('/api/translate', [
            'text' => 'Hello',
            'target' => 'ar',
        ])->assertStatus(503);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/translate', [
            'text' => 'Hello',
            'target' => 'ar',
        ])->assertStatus(401);
    }

    public function test_rejects_an_unsupported_target_language(): void
    {
        Config::set('services.google_translate.api_key', 'test-key');
        Sanctum::actingAs($this->user());

        $this->postJson('/api/translate', [
            'text' => 'Hello',
            'target' => 'de',
        ])->assertStatus(422);
    }

    private function user(): User
    {
        $country = Country::create(['country_code' => strtoupper(Str::random(2)), 'status' => 'active']);
        $city = City::create([
            'country_id' => $country->id,
            'country_code' => $country->country_code,
            'status' => 'active',
            'code' => Str::upper(Str::random(8)),
        ]);

        return User::create([
            'name' => 'Translate User',
            'first_name' => 'Translate',
            'last_name' => 'User',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'status' => '1',
            'city_id' => $city->id,
        ]);
    }
}
