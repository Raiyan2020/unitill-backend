<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublishConfirmationV2Test extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key_id' => 'post_price'], ['value' => '0.99']);
        Setting::updateOrCreate(['key_id' => 'listing_extension_price'], ['value' => '0.99']);
        Setting::updateOrCreate(['key_id' => 'free_ads_per_user'], ['value' => '0']);
    }

    public function test_v2_publish_rejects_without_confirmation(): void
    {
        [$user, $ad] = $this->pendingDraft();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/ads/{$ad->id}/publish")->assertStatus(422);
        $this->assertNull($ad->fresh()->publish_confirmed_at);
    }

    public function test_v2_publish_records_confirmation_and_starts_payment(): void
    {
        [$user, $ad] = $this->pendingDraft();
        Sanctum::actingAs($user);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_v2_publish_intent',
                'client_secret' => 'pi_v2_publish_intent_secret',
                'status' => 'requires_payment_method',
            ]),
        ]);

        $this->postJson("/api/v2/ads/{$ad->id}/publish", ['confirm_publish_immediately' => true])
            ->assertOk()
            ->assertJsonPath('data.publication.payment_required', true);

        $this->assertNotNull($ad->fresh()->publish_confirmed_at);
    }

    public function test_v1_publish_still_works_without_confirmation(): void
    {
        [$user, $ad] = $this->pendingDraft();
        Sanctum::actingAs($user);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_v1_publish_intent',
                'client_secret' => 'pi_v1_publish_intent_secret',
                'status' => 'requires_payment_method',
            ]),
        ]);

        $this->postJson("/api/ads/{$ad->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.publication.payment_required', true);
    }

    public function test_v2_extend_rejects_without_confirmation(): void
    {
        [$user, $ad] = $this->expiredAd();
        Sanctum::actingAs($user);

        $this->postJson("/api/v2/my-ads/{$ad->id}/extend")->assertStatus(422);
    }

    public function test_v2_extend_records_confirmation_and_starts_payment(): void
    {
        [$user, $ad] = $this->expiredAd();
        Sanctum::actingAs($user);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_v2_extend_intent',
                'client_secret' => 'pi_v2_extend_intent_secret',
                'status' => 'requires_payment_method',
            ]),
        ]);

        $this->postJson("/api/v2/my-ads/{$ad->id}/extend", ['confirm_publish_immediately' => true])
            ->assertOk()
            ->assertJsonPath('data.publication.amount', 0.99);

        $this->assertNotNull($ad->fresh()->publish_confirmed_at);
    }

    /** @return array{0: User, 1: Ad} */
    private function pendingDraft(): array
    {
        [$user, $ad] = $this->baseAd();
        $ad->update(['status' => 'draft']);

        return [$user, $ad];
    }

    /** @return array{0: User, 1: Ad} */
    private function expiredAd(): array
    {
        [$user, $ad] = $this->baseAd();
        $ad->update(['status' => 'expired', 'payment_status' => 'paid', 'expires_at' => now()->subDay()]);

        return [$user, $ad];
    }

    /** @return array{0: User, 1: Ad} */
    private function baseAd(): array
    {
        $country = Country::create(['country_code' => strtoupper(Str::random(2)), 'status' => 'active']);
        $city = City::create([
            'country_id' => $country->id,
            'country_code' => $country->country_code,
            'status' => 'active',
            'code' => Str::upper(Str::random(8)),
        ]);
        $category = Category::create(['status' => 'active', 'sort' => 0]);
        $user = User::create([
            'name' => 'Confirmation User',
            'first_name' => 'Confirmation',
            'last_name' => 'User',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'status' => '1',
            'city_id' => $city->id,
        ]);

        $ad = Ad::create([
            'user_id' => $user->id,
            'title' => 'Confirmation listing',
            'description' => 'Needs a payment step',
            'country_id' => $country->id,
            'city_id' => $city->id,
            'main_category_id' => $category->id,
            'price' => 10,
            'currency' => 'GBP',
            'status' => 'pending',
        ]);

        \App\Models\AdImage::create([
            'ad_id' => $ad->id,
            'path' => 'ads/placeholder.jpg',
            'sort_order' => 1,
        ]);

        return [$user, $ad];
    }
}
