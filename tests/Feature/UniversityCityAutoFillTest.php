<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\TermsVersion;
use App\Models\University;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A student's city is meant to come from their university, not a manual
 * picker — RegisterRequest already requires student_email to match an
 * active university domain, so AuthController::register resolves that same
 * university and uses its city_id, overriding whatever (if anything) the
 * client sent as city_id.
 */
class UniversityCityAutoFillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Registration sends a real OTP email; without faking it, every test
        // here actually attempts SMTP delivery (and hangs on it in this env).
        Mail::fake();

        // A migration already seeds a default "1.0" current terms version —
        // reuse it if present rather than colliding on the unique version.
        if (! TermsVersion::query()->where('is_current', true)->exists()) {
            TermsVersion::create([
                'version' => 'test-1.0',
                'title_en' => 'Terms',
                'title_ar' => 'الشروط',
                'content_en' => 'Terms content',
                'content_ar' => 'محتوى الشروط',
                'is_current' => true,
                'effective_at' => now()->subDay(),
            ]);
        }
    }

    private function makeCity(string $name): City
    {
        $country = Country::firstOrCreate(['country_code' => 'GB'], ['status' => 'active']);

        $city = City::create([
            'country_id' => $country->id,
            'country_code' => 'GB',
            'status' => 'active',
        ]);
        $city->translations()->create(['language_id' => $this->languageId(), 'name' => $name]);

        return $city;
    }

    private int $langId;

    private function languageId(): int
    {
        if (! isset($this->langId)) {
            $this->langId = \App\Models\Language::firstOrCreate(
                ['code' => 'en'],
                ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_default' => true, 'is_active' => true, 'sort_order' => 1]
            )->id;
        }

        return $this->langId;
    }

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ada',
            'last_name' => 'Student',
            'email' => 'ada.personal@example.com',
            'student_email' => 'ada@oxforduni.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms_accepted' => true,
        ], $overrides);
    }

    public function test_registration_auto_fills_city_from_the_matched_university(): void
    {
        $oxford = $this->makeCity('Oxford');
        $elsewhere = $this->makeCity('Elsewhere');

        $university = University::create([
            'name' => 'University of Oxford',
            'country_code' => 'GB',
            'city' => 'Oxford',
            'city_id' => $oxford->id,
            'status' => 'active',
        ]);
        $university->domains()->create(['domain' => 'oxforduni.test', 'status' => 'active']);

        // Client sends a different, wrong city_id — the university match must win.
        $response = $this->postJson('/api/register', $this->registerPayload([
            'city_id' => $elsewhere->id,
        ]))->assertOk();

        $userId = $response->json('data.user_id');
        $this->assertNotNull($userId);

        $user = \App\Models\User::findOrFail($userId);
        $this->assertSame($oxford->id, $user->city_id);
    }

    public function test_registration_falls_back_to_the_submitted_city_when_the_university_has_no_city_linked(): void
    {
        $fallback = $this->makeCity('Fallback City');

        $university = University::create([
            'name' => 'University With No City',
            'country_code' => 'GB',
            'city' => null,
            'city_id' => null,
            'status' => 'active',
        ]);
        $university->domains()->create(['domain' => 'nocity.test', 'status' => 'active']);

        $response = $this->postJson('/api/register', $this->registerPayload([
            'student_email' => 'ada@nocity.test',
            'city_id' => $fallback->id,
        ]))->assertOk();

        $user = \App\Models\User::findOrFail($response->json('data.user_id'));
        $this->assertSame($fallback->id, $user->city_id);
    }

    public function test_registration_leaves_city_null_when_neither_university_nor_client_provide_one(): void
    {
        $university = University::create([
            'name' => 'University With No City',
            'country_code' => 'GB',
            'city' => null,
            'city_id' => null,
            'status' => 'active',
        ]);
        $university->domains()->create(['domain' => 'nocity2.test', 'status' => 'active']);

        $response = $this->postJson('/api/register', $this->registerPayload([
            'student_email' => 'ada@nocity2.test',
        ]))->assertOk();

        $user = \App\Models\User::findOrFail($response->json('data.user_id'));
        $this->assertNull($user->city_id);
    }

    public function test_registration_still_rejects_an_email_whose_domain_matches_no_active_university(): void
    {
        // Validation failures on this endpoint return 400, not 422 — see
        // RegisterRequest::failedValidation(), which calls sendError()
        // without an explicit code (its default is 400).
        $this->postJson('/api/register', $this->registerPayload([
            'student_email' => 'ada@not-a-real-university.test',
        ]))->assertStatus(400);
    }

    public function test_a_subdomain_of_a_registered_university_domain_still_resolves_the_same_university(): void
    {
        $oxford = $this->makeCity('Oxford');

        $university = University::create([
            'name' => 'University of Oxford',
            'country_code' => 'GB',
            'city' => 'Oxford',
            'city_id' => $oxford->id,
            'status' => 'active',
        ]);
        $university->domains()->create(['domain' => 'ox.test', 'status' => 'active']);

        $response = $this->postJson('/api/register', $this->registerPayload([
            'student_email' => 'ada@stcatz.ox.test',
        ]))->assertOk();

        $user = \App\Models\User::findOrFail($response->json('data.user_id'));
        $this->assertSame($oxford->id, $user->city_id);
    }
}
