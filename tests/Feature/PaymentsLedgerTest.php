<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentsLedgerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key_id' => 'post_price'], ['value' => '0.99']);
        Setting::updateOrCreate(['key_id' => 'listing_extension_price'], ['value' => '0.99']);
        Setting::updateOrCreate(['key_id' => 'free_ads_per_user'], ['value' => '0']);
    }

    public function test_reactivating_an_expired_ad_records_a_new_extension_payment_distinct_from_the_original(): void
    {
        $category = Category::create(['status' => 'active', 'sort' => 0]);
        [$user, $ad] = $this->expiredAd($category);

        Payment::create([
            'ad_id' => $ad->id,
            'user_id' => $user->id,
            'type' => 'listing',
            'stripe_payment_intent_id' => 'pi_original_listing',
            'amount' => 0.99,
            'currency' => 'GBP',
            'status' => 'paid',
            'paid_at' => now()->subDays(31),
        ]);

        Sanctum::actingAs($user);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_extension_attempt',
                'client_secret' => 'pi_extension_attempt_secret',
                'status' => 'requires_payment_method',
            ]),
        ]);

        $this->postJson("/api/my-ads/{$ad->id}/activate")->assertOk();

        $this->assertDatabaseHas('payments', [
            'stripe_payment_intent_id' => 'pi_original_listing',
            'type' => 'listing',
        ]);
        $this->assertDatabaseHas('payments', [
            'ad_id' => $ad->id,
            'type' => 'extension',
            'stripe_payment_intent_id' => 'pi_extension_attempt',
            'amount' => 0.99,
            'status' => 'requires_payment',
        ]);
        $this->assertSame(2, Payment::where('ad_id', $ad->id)->count());
    }

    public function test_webhook_confirmation_marks_the_matching_payment_paid(): void
    {
        $category = Category::create(['status' => 'active', 'sort' => 0, 'listing_fee' => 2.99]);
        [, $ad] = $this->pendingAd($category);

        Payment::create([
            'ad_id' => $ad->id,
            'user_id' => $ad->user_id,
            'type' => 'listing',
            'stripe_payment_intent_id' => $ad->stripe_payment_intent_id,
            'amount' => 2.99,
            'currency' => 'GBP',
            'status' => 'requires_payment',
        ]);

        app(\App\Services\ListingPaymentService::class)->publishPaidListing(
            $ad->stripe_payment_intent_id,
            299,
            'gbp',
            ['payment_method_types' => ['card']]
        );

        $this->assertDatabaseHas('payments', [
            'stripe_payment_intent_id' => $ad->stripe_payment_intent_id,
            'status' => 'paid',
            'payment_method_type' => 'card',
        ]);
    }

    public function test_admin_refund_updates_the_matching_payment_row(): void
    {
        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class);
        $category = Category::create(['status' => 'active', 'sort' => 0]);
        [, $ad] = $this->pendingAd($category);
        $ad->update(['status' => 'published', 'payment_status' => 'paid']);

        Payment::create([
            'ad_id' => $ad->id,
            'user_id' => $ad->user_id,
            'type' => 'listing',
            'stripe_payment_intent_id' => $ad->stripe_payment_intent_id,
            'amount' => 0.99,
            'currency' => 'GBP',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Sanctum::actingAs(\App\Models\Admin::create([
            'name' => 'Ledger Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]));

        Http::fake([
            'api.stripe.com/v1/refunds' => Http::response(['id' => 're_ledger_test', 'status' => 'succeeded']),
        ]);

        $this->postJson("/api/admin/ads/{$ad->id}/refund", ['reason' => 'Service failure'])->assertOk();

        $this->assertDatabaseHas('payments', [
            'stripe_payment_intent_id' => $ad->stripe_payment_intent_id,
            'refund_status' => 'refunded',
            'refund_reference' => 're_ledger_test',
        ]);
    }

    /** @return array{0: User, 1: Ad} */
    private function expiredAd(Category $category): array
    {
        [$user, $ad] = $this->baseAd($category);
        $ad->update(['status' => 'expired', 'payment_status' => 'paid', 'expires_at' => now()->subDay()]);

        return [$user, $ad];
    }

    /** @return array{0: User, 1: Ad} */
    private function pendingAd(Category $category): array
    {
        [$user, $ad] = $this->baseAd($category);
        $ad->update([
            'status' => 'pending',
            'payment_status' => 'requires_payment',
            'listing_fee' => $category->resolvedListingFee(),
            'stripe_payment_intent_id' => 'pi_pending_'.Str::lower(Str::random(16)),
        ]);

        return [$user, $ad];
    }

    /** @return array{0: User, 1: Ad} */
    private function baseAd(Category $category): array
    {
        $country = Country::create(['country_code' => strtoupper(Str::random(2)), 'status' => 'active']);
        $city = City::create([
            'country_id' => $country->id,
            'country_code' => $country->country_code,
            'status' => 'active',
            'code' => Str::upper(Str::random(8)),
        ]);
        $user = User::create([
            'name' => 'Ledger User',
            'first_name' => 'Ledger',
            'last_name' => 'User',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'status' => '1',
            'city_id' => $city->id,
        ]);

        $ad = Ad::create([
            'user_id' => $user->id,
            'title' => 'Ledger listing',
            'description' => 'Payments ledger coverage',
            'country_id' => $country->id,
            'city_id' => $city->id,
            'main_category_id' => $category->id,
            'price' => 10,
            'currency' => 'GBP',
            'status' => 'pending',
        ]);

        return [$user, $ad];
    }
}
