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

class ListingExtensionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key_id' => 'listing_extension_price'], ['value' => '0.99']);
        Setting::updateOrCreate(['key_id' => 'free_ads_per_user'], ['value' => '0']);
    }

    public function test_extending_an_expired_listing_starts_a_new_paid_period(): void
    {
        $category = Category::create(['status' => 'active', 'sort' => 0, 'listing_fee' => 2.99]);
        [$user, $ad] = $this->expiredAd($category);

        Sanctum::actingAs($user);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_extend_intent',
                'client_secret' => 'pi_extend_intent_secret',
                'status' => 'requires_payment_method',
            ]),
        ]);

        $this->postJson("/api/my-ads/{$ad->id}/extend")
            ->assertOk()
            ->assertJsonPath('data.publication.published', false)
            ->assertJsonPath('data.publication.payment_required', true)
            ->assertJsonPath('data.publication.amount', 0.99);

        $this->assertSame('pending', $ad->fresh()->status);
        $this->assertSame('pi_extend_intent', $ad->fresh()->stripe_payment_intent_id);
    }

    public function test_extending_a_published_listing_is_rejected(): void
    {
        $category = Category::create(['status' => 'active', 'sort' => 0]);
        [$user, $ad] = $this->expiredAd($category);
        $ad->update(['status' => 'published', 'expires_at' => now()->addDays(10)]);

        Sanctum::actingAs($user);

        $this->postJson("/api/my-ads/{$ad->id}/extend")->assertStatus(422);
    }

    public function test_extending_a_paused_listing_still_within_its_paid_period_is_rejected(): void
    {
        $category = Category::create(['status' => 'active', 'sort' => 0]);
        [$user, $ad] = $this->expiredAd($category);
        $ad->update([
            'status' => 'paused',
            'paused_at' => now(),
            'payment_status' => 'paid',
            'expires_at' => now()->addDays(10),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/my-ads/{$ad->id}/extend")->assertStatus(422);
    }

    public function test_activate_still_works_exactly_as_before_for_an_expired_listing(): void
    {
        $category = Category::create(['status' => 'active', 'sort' => 0]);
        [$user, $ad] = $this->expiredAd($category);

        Sanctum::actingAs($user);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_activate_intent',
                'client_secret' => 'pi_activate_intent_secret',
                'status' => 'requires_payment_method',
            ]),
        ]);

        $this->postJson("/api/my-ads/{$ad->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.publication.amount', 0.99);
    }

    /** @return array{0: User, 1: Ad} */
    private function expiredAd(Category $category): array
    {
        $country = Country::create(['country_code' => strtoupper(Str::random(2)), 'status' => 'active']);
        $city = City::create([
            'country_id' => $country->id,
            'country_code' => $country->country_code,
            'status' => 'active',
            'code' => Str::upper(Str::random(8)),
        ]);
        $user = User::create([
            'name' => 'Extension User',
            'first_name' => 'Extension',
            'last_name' => 'User',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'status' => '1',
            'city_id' => $city->id,
        ]);

        $ad = Ad::create([
            'user_id' => $user->id,
            'title' => 'Expired listing',
            'description' => 'Needs a new paid period',
            'country_id' => $country->id,
            'city_id' => $city->id,
            'main_category_id' => $category->id,
            'price' => 10,
            'currency' => 'GBP',
            'status' => 'expired',
            'payment_status' => 'paid',
            'expires_at' => now()->subDay(),
        ]);

        return [$user, $ad];
    }
}
