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

class CategoryListingFeeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key_id' => 'post_price'], ['value' => '0.99']);
        Setting::updateOrCreate(['key_id' => 'listing_extension_price'], ['value' => '0.99']);
        Setting::updateOrCreate(['key_id' => 'free_ads_per_user'], ['value' => '0']);
    }

    public function test_category_resolves_its_own_fee_or_falls_back_to_standard(): void
    {
        $standard = Category::create(['status' => 'active', 'sort' => 0]);
        $cars = Category::create(['status' => 'active', 'sort' => 1, 'listing_fee' => 2.99]);

        $this->assertSame(0.99, $standard->resolvedListingFee());
        $this->assertSame(2.99, $cars->resolvedListingFee());
    }

    public function test_extending_an_expired_car_listing_charges_the_flat_extension_price_not_the_category_price(): void
    {
        $cars = Category::create(['status' => 'active', 'sort' => 1, 'listing_fee' => 2.99]);
        [$user, $ad] = $this->expiredAd($cars);

        Sanctum::actingAs($user);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_new_car_intent',
                'client_secret' => 'pi_new_car_intent_secret',
                'status' => 'requires_payment_method',
            ]),
        ]);

        $this->postJson("/api/my-ads/{$ad->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.publication.amount', 0.99);

        $this->assertSame('0.99', (string) $ad->fresh()->listing_fee);
    }

    public function test_extending_an_expired_standard_listing_charges_the_extension_price(): void
    {
        $standard = Category::create(['status' => 'active', 'sort' => 0]);
        [$user, $ad] = $this->expiredAd($standard);

        Sanctum::actingAs($user);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_new_standard_intent',
                'client_secret' => 'pi_new_standard_intent_secret',
                'status' => 'requires_payment_method',
            ]),
        ]);

        $this->postJson("/api/my-ads/{$ad->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.publication.amount', 0.99);

        $this->assertSame('0.99', (string) $ad->fresh()->listing_fee);
    }

    public function test_extending_pushes_expiry_30_days_from_payment_confirmation(): void
    {
        $standard = Category::create(['status' => 'active', 'sort' => 0]);
        [$user, $ad] = $this->expiredAd($standard);

        Sanctum::actingAs($user);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_extend_expiry_intent',
                'client_secret' => 'pi_extend_expiry_intent_secret',
                'status' => 'requires_payment_method',
            ]),
        ]);

        $this->postJson("/api/my-ads/{$ad->id}/activate")->assertOk();

        $intentId = $ad->fresh()->stripe_payment_intent_id;

        $published = app(\App\Services\ListingPaymentService::class)->publishPaidListing($intentId, 99, 'gbp');

        $this->assertSame('published', $published->status);
        $this->assertTrue($published->expires_at->isBetween(now()->addDays(29), now()->addDays(31)));
    }

    public function test_new_car_listing_charges_the_category_price_not_the_extension_price(): void
    {
        Setting::updateOrCreate(['key_id' => 'listing_extension_price'], ['value' => '5.00']);
        $cars = Category::create(['status' => 'active', 'sort' => 1, 'listing_fee' => 2.99]);
        [$user, $ad] = $this->expiredAd($cars);
        // sellAgain never goes through the extension branch — it always starts a
        // brand new listing, so it must still use the category price.
        $ad->update(['status' => 'sold']);

        Sanctum::actingAs($user);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_sell_again_intent',
                'client_secret' => 'pi_sell_again_intent_secret',
                'status' => 'requires_payment_method',
            ]),
        ]);

        $this->postJson("/api/my-ads/{$ad->id}/sell-again")
            ->assertOk()
            ->assertJsonPath('data.publication.amount', 2.99);
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
            'name' => 'Category Fee User',
            'first_name' => 'Category',
            'last_name' => 'Fee',
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
