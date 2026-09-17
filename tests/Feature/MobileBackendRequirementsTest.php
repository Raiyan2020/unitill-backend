<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureFeatureIsAvailable;
use App\Models\Ad;
use App\Models\AdImage;
use App\Models\Category;
use App\Models\CategoryAttributeDefinition;
use App\Models\CategoryAttributeDefinitionTranslation;
use App\Models\CategoryTranslation;
use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use App\Models\LegalAffair;
use App\Models\LegalAffairTranslation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileBackendRequirementsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_category_contract_has_stable_slug_localized_options_and_date_controls(): void
    {
        $category = $this->category('Accommodation test', 'Alojamiento de prueba');
        $definition = CategoryAttributeDefinition::create([
            'category_id' => $category->id,
            'slug' => 'availability_until',
            'input_type' => 'date',
            'filter_control' => 'date',
            'post_control' => 'date',
            'options' => [['value' => 'Flat', 'label' => 'Flat']],
            'is_active' => true,
        ]);
        CategoryAttributeDefinitionTranslation::create([
            'category_attribute_definition_id' => $definition->id,
            'language_id' => $this->language('es')->id,
            'label' => 'Disponible hasta',
            'options' => ['Flat' => 'Piso'],
        ]);

        $categories = $this->getJson('/api/categories', ['lang' => 'es'])
            ->assertOk()
            ->json('data');
        $payload = collect($categories)->firstWhere('id', $category->id);

        $this->assertSame($category->slug, $payload['slug']);
        $this->assertSame('Alojamiento de prueba', $payload['name']);
        $this->assertSame('date', $payload['attributes'][0]['input_type']);
        $this->assertSame('date', $payload['attributes'][0]['post_control']);
        $this->assertSame('Flat', $payload['attributes'][0]['options'][0]['value']);
        $this->assertSame('Piso', $payload['attributes'][0]['options'][0]['label']);
    }

    public function test_draft_rejects_same_day_availability_with_field_error_map(): void
    {
        [$user, $category, $city] = $this->listingFixture();
        Sanctum::actingAs($user);

        $date = now()->addDays(2)->toDateString();
        $response = $this->postJson('/api/v2/ads/draft', [
            'main_category_id' => $category->id,
            'title' => 'Room near campus',
            'description' => 'Available for the academic year.',
            'price' => 180,
            'currency' => 'GBP',
            'city_id' => $city->id,
            'attributes' => [
                'availability_from' => $date,
                'availability_until' => $date,
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The availability period is not valid.');

        $this->assertSame(
            'The end date must be at least one day after the start date.',
            $response->json('errors')['attributes.availability_until'][0]
        );
    }

    public function test_owner_can_edit_published_ad_without_changing_payment_or_status(): void
    {
        [$user, $category, $city, $ad] = $this->listingFixture(true);
        Sanctum::actingAs($user);
        $this->withoutMiddleware(EnsureFeatureIsAvailable::class);

        $intentId = $ad->stripe_payment_intent_id;
        $this->putJson("/api/v2/my-ads/{$ad->id}", [
            'main_category_id' => $category->id,
            'title' => 'Room near campus with parking',
            'description' => 'Updated description.',
            'price' => 195,
            'currency' => 'GBP',
            'city_id' => $city->id,
            'postcode' => 'LS2 9JT',
            'is_negotiable' => true,
            'attributes' => [],
        ])
            ->assertOk()
            ->assertJsonPath('data.ad.title', 'Room near campus with parking')
            ->assertJsonPath('data.ad.status', 'published');

        $ad->refresh();
        $this->assertSame('published', $ad->status);
        $this->assertSame('paid', $ad->payment_status);
        $this->assertSame($intentId, $ad->stripe_payment_intent_id);
    }

    public function test_edit_rejects_non_owner_and_sold_listing_with_distinct_contracts(): void
    {
        [$owner, $category, $city, $ad] = $this->listingFixture(true);
        $payload = [
            'main_category_id' => $category->id,
            'title' => 'Changed title',
            'description' => 'Changed description.',
            'price' => 10,
            'city_id' => $city->id,
        ];
        $this->withoutMiddleware(EnsureFeatureIsAvailable::class);

        Sanctum::actingAs($this->user($city));
        $this->putJson("/api/v2/my-ads/{$ad->id}", $payload)
            ->assertForbidden()
            ->assertJsonPath('data.error_code', 'not_owner');

        $ad->update(['status' => 'sold']);
        Sanctum::actingAs($owner);
        $this->putJson("/api/v2/my-ads/{$ad->id}", $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'A sold listing cannot be edited.');
    }

    public function test_reactivate_sold_ad_is_free_inside_the_paid_window(): void
    {
        [$user, , , $ad] = $this->listingFixture(true);
        $ad->update([
            'status' => 'sold',
            'sold_at' => now(),
            'sold_to_user_id' => null,
            'is_sold_outside' => true,
            'payment_status' => 'paid',
            'expires_at' => now()->addDays(10),
        ]);
        Sanctum::actingAs($user);
        $this->withoutMiddleware(EnsureFeatureIsAvailable::class);

        $this->postJson("/api/v2/my-ads/{$ad->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.ad.status', 'published');

        $ad->refresh();
        $this->assertSame('published', $ad->status);
        $this->assertNull($ad->sold_at);
        $this->assertFalse((bool) $ad->is_sold_outside);
    }

    public function test_reactivate_charges_the_extend_fee_not_the_full_listing_fee_outside_the_window(): void
    {
        Setting::updateOrCreate(['key_id' => 'post_price'], ['value' => '5.00']);
        Setting::updateOrCreate(['key_id' => 'listing_extension_price'], ['value' => '0.99']);

        [$user, , , $ad] = $this->listingFixture(true);
        $ad->update([
            'status' => 'sold',
            'sold_at' => now()->subDays(40),
            'sold_to_user_id' => null,
            'is_sold_outside' => true,
            'payment_status' => 'paid',
            'expires_at' => now()->subDays(10),
            'stripe_payment_intent_id' => null,
        ]);
        Sanctum::actingAs($user);
        $this->withoutMiddleware(EnsureFeatureIsAvailable::class);
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_reactivate_test',
                'client_secret' => 'pi_reactivate_test_secret',
                'status' => 'requires_payment_method',
            ]),
        ]);

        $this->postJson("/api/v2/my-ads/{$ad->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.publication.payment_required', true)
            ->assertJsonPath('data.publication.amount', 0.99);

        $ad->refresh();
        $this->assertSame('pending', $ad->status);
        $this->assertSame('requires_payment', $ad->payment_status);
        $this->assertNull($ad->sold_at);
    }

    public function test_publish_refuses_paused_and_expired_ads_and_points_to_the_right_action(): void
    {
        [$user, , , $pausedAd] = $this->listingFixture(true);
        $pausedAd->update(['status' => 'paused', 'payment_status' => 'paid', 'expires_at' => now()->addDays(10)]);
        AdImage::create(['ad_id' => $pausedAd->id, 'path' => 'ads/placeholder.jpg', 'sort_order' => 1]);
        Sanctum::actingAs($user);
        $this->withoutMiddleware(EnsureFeatureIsAvailable::class);

        $this->postJson("/api/v2/ads/{$pausedAd->id}/publish", ['confirm_publish_immediately' => true])
            ->assertStatus(422)
            ->assertJsonPath('data.error_code', 'invalid_status')
            ->assertJsonPath('data.status', 'paused');
        $this->assertSame('paused', $pausedAd->fresh()->status);

        [$owner, , , $expiredAd] = $this->listingFixture(true);
        $expiredAd->update(['status' => 'expired', 'payment_status' => 'paid', 'expires_at' => now()->subDays(2)]);
        AdImage::create(['ad_id' => $expiredAd->id, 'path' => 'ads/placeholder.jpg', 'sort_order' => 1]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v2/ads/{$expiredAd->id}/publish", ['confirm_publish_immediately' => true])
            ->assertStatus(422)
            ->assertJsonPath('data.error_code', 'invalid_status')
            ->assertJsonPath('data.status', 'expired');
        $this->assertSame('expired', $expiredAd->fresh()->status);
    }

    public function test_coupon_refusal_has_machine_readable_reason_and_code(): void
    {
        [$user] = $this->listingFixture();
        Sanctum::actingAs($user);

        $this->postJson('/api/coupons/validate', ['code' => 'does-not-exist'])
            ->assertStatus(422)
            ->assertJsonPath('data.coupon_error', 'invalid')
            ->assertJsonPath('data.code', 'DOES-NOT-EXIST');
    }

    public function test_terms_and_privacy_report_the_locale_actually_served(): void
    {
        $this->legalDocument('terms_of_service', [
            'en' => ['Terms of Service', 'English terms body'],
            'fr' => ["Conditions d'utilisation", 'Corps des conditions en français'],
        ]);
        $this->legalDocument('privacy_policy', [
            'en' => ['Privacy Policy', 'English privacy body'],
            'es' => ['Política de privacidad', 'Contenido de privacidad en español'],
        ]);

        $this->getJson('/api/terms/current', ['lang' => 'fr'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'fr');

        $this->getJson('/api/privacy/current', ['lang' => 'es'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'es');
    }

    public function test_production_migration_backfills_localized_content_without_seeders(): void
    {
        $category = Category::create([
            'slug' => 'migration-accommodation-'.Str::lower(Str::random(5)),
            'status' => 'active',
            'sort' => 998,
        ]);
        CategoryTranslation::create([
            'category_id' => $category->id,
            'language_id' => $this->language('en')->id,
            'name' => 'Accommodation',
        ]);
        $definition = CategoryAttributeDefinition::create([
            'category_id' => $category->id,
            'slug' => 'condition',
            'input_type' => 'select',
            'options' => [
                ['value' => 'Like new', 'label' => 'Like new'],
                ['value' => 'Used', 'label' => 'Used'],
            ],
            'is_active' => true,
        ]);
        CategoryAttributeDefinitionTranslation::create([
            'category_attribute_definition_id' => $definition->id,
            'language_id' => $this->language('en')->id,
            'label' => 'Condition',
        ]);

        $migrationPath = database_path('migrations/2026_09_16_000100_backfill_mobile_localizations_and_legal_documents.php');
        $migration = require $migrationPath;
        $migration->up();

        $spanishId = $this->language('es')->id;
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->id,
            'language_id' => $spanishId,
            'name' => 'Alojamiento',
        ]);

        $translation = CategoryAttributeDefinitionTranslation::query()
            ->where('category_attribute_definition_id', $definition->id)
            ->where('language_id', $spanishId)
            ->firstOrFail();
        $this->assertSame('Como nuevo', $translation->options['Like new']);
        $this->assertSame('Usado', $translation->options['Used']);

        $privacy = LegalAffair::query()->where('key', 'privacy_policy')->firstOrFail();
        $this->assertNotSame('', $privacy->translationRowFor('fr')?->description);
        $counts = [
            'categories' => CategoryTranslation::where('category_id', $category->id)->count(),
            'attributes' => $definition->translations()->count(),
            'privacy' => $privacy->translations()->count(),
        ];

        // A second deploy/retry must update in place rather than duplicate rows.
        $migration->up();
        $this->assertSame($counts['categories'], CategoryTranslation::where('category_id', $category->id)->count());
        $this->assertSame($counts['attributes'], $definition->translations()->count());
        $this->assertSame($counts['privacy'], $privacy->translations()->count());
    }

    /** @param array<string, array{0:string,1:string}> $translations */
    private function legalDocument(string $key, array $translations): void
    {
        $document = LegalAffair::updateOrCreate(
            ['key' => $key],
            [
                'section' => 'policies',
                'sort_order' => 999,
                'is_active' => true,
            ]
        );

        foreach ($translations as $code => [$title, $content]) {
            LegalAffairTranslation::updateOrCreate(
                [
                    'legal_affair_id' => $document->id,
                    'language_id' => $this->language($code)->id,
                ],
                [
                    'title' => $title,
                    'description' => $content,
                ]
            );
        }
    }

    /** @return array{0:User,1:Category,2:City,3?:Ad} */
    private function listingFixture(bool $withAd = false): array
    {
        $country = Country::create([
            'country_code' => strtoupper(Str::random(2)),
            'status' => 'active',
        ]);
        $city = City::create([
            'country_id' => $country->id,
            'country_code' => $country->country_code,
            'status' => 'active',
            'code' => Str::upper(Str::random(8)),
        ]);
        $category = $this->category('Test category '.Str::random(6), 'Categoría de prueba');
        $user = $this->user($city);
        $result = [$user, $category, $city];

        if ($withAd) {
            $result[] = Ad::create([
                'user_id' => $user->id,
                'public_id' => strtoupper(Str::random(10)),
                'title' => 'Room near campus',
                'description' => 'Original description.',
                'country_id' => $country->id,
                'city_id' => $city->id,
                'main_category_id' => $category->id,
                'price' => 180,
                'currency' => 'GBP',
                'status' => 'published',
                'payment_status' => 'paid',
                'stripe_payment_intent_id' => 'pi_'.Str::random(18),
                'published_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);
        }

        return $result;
    }

    private function category(string $english, string $spanish): Category
    {
        $category = Category::create([
            'slug' => Str::slug($english).'-'.Str::lower(Str::random(5)),
            'status' => 'active',
            'sort' => 999,
        ]);
        CategoryTranslation::create([
            'category_id' => $category->id,
            'language_id' => $this->language('en')->id,
            'name' => $english,
        ]);
        CategoryTranslation::create([
            'category_id' => $category->id,
            'language_id' => $this->language('es')->id,
            'name' => $spanish,
        ]);

        return $category;
    }

    private function language(string $code): Language
    {
        return Language::firstOrCreate(
            ['code' => $code],
            [
                'name' => strtoupper($code),
                'native_name' => strtoupper($code),
                'direction' => $code === 'ar' ? 'rtl' : 'ltr',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 99,
            ]
        );
    }

    private function user(City $city): User
    {
        return User::create([
            'name' => 'Mobile Test User',
            'first_name' => 'Mobile',
            'last_name' => 'Tester',
            'email' => Str::uuid().'@example.test',
            'student_email' => Str::uuid().'@example.ac.uk',
            'phone' => '07'.random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => '1',
            'city_id' => $city->id,
        ]);
    }
}
