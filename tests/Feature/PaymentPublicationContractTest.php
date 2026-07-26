<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\CategoryAttributeDefinition;
use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentPublicationContractTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pending_stripe_payment_returns_authoritative_unpublished_state(): void
    {
        [$user, $ad] = $this->pendingPaidAd();
        Sanctum::actingAs($user);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => $ad->stripe_payment_intent_id,
                'status' => 'requires_payment_method',
                'amount_received' => 0,
                'currency' => 'gbp',
            ]),
        ]);

        $this->postJson("/api/ads/{$ad->id}/payment/complete")
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.publication.published', false)
            ->assertJsonPath('data.publication.payment_required', true)
            ->assertJsonPath('data.publication.amount', 5);

        $this->assertSame('pending', $ad->fresh()->status);
    }

    public function test_successful_payment_publishes_once_and_retries_are_idempotent(): void
    {
        [$user, $ad] = $this->pendingPaidAd();
        Sanctum::actingAs($user);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => $ad->stripe_payment_intent_id,
                'status' => 'succeeded',
                'amount_received' => 500,
                'currency' => 'gbp',
            ]),
        ]);

        $uri = "/api/ads/{$ad->id}/payment/complete";

        $this->postJson($uri)
            ->assertOk()
            ->assertJsonPath('data.publication.published', true)
            ->assertJsonPath('data.publication.payment_required', false);

        $publishedAt = $ad->fresh()->published_at;

        $this->postJson($uri)
            ->assertOk()
            ->assertJsonPath('data.publication.published', true);

        $ad->refresh();
        $this->assertSame('published', $ad->status);
        $this->assertSame('paid', $ad->payment_status);
        $this->assertTrue($publishedAt->equalTo($ad->published_at));
        Http::assertSentCount(1);
    }

    public function test_owner_can_read_payment_status(): void
    {
        [$user, $ad] = $this->pendingPaidAd();
        Sanctum::actingAs($user);

        $this->getJson("/api/ads/{$ad->id}/payment/status")
            ->assertOk()
            ->assertJsonPath('data.publication.published', false)
            ->assertJsonPath('data.publication.payment_required', true)
            ->assertJsonPath('data.publication.payment_status', 'requires_payment');
    }

    public function test_pending_payment_ad_is_absent_from_public_endpoints(): void
    {
        [, $ad] = $this->pendingPaidAd();

        $this->getJson('/api/ads?search=Payment%20contract%20ad')
            ->assertOk()
            ->assertJsonMissing(['id' => $ad->id]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonMissing(['id' => $ad->id]);

        $this->getJson("/api/ads/{$ad->id}")
            ->assertNotFound();
    }

    public function test_owner_can_list_and_delete_an_abandoned_draft(): void
    {
        [$user, $ad] = $this->pendingPaidAd();
        $ad->update([
            'status' => 'draft',
            'payment_status' => 'not_required',
            'listing_fee' => null,
            'stripe_payment_intent_id' => null,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/my-ads?status=inactive')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $ad->id,
                'status' => 'draft',
            ]);

        $this->deleteJson("/api/my-ads/{$ad->id}")
            ->assertOk()
            ->assertJsonPath('data.ad_id', $ad->id);

        $this->assertSoftDeleted('ads', ['id' => $ad->id]);
    }

    public function test_draft_accepts_and_stores_multiselect_attribute_values(): void
    {
        [$user, $seedAd] = $this->pendingPaidAd();
        $definition = CategoryAttributeDefinition::create([
            'category_id' => $seedAd->main_category_id,
            'slug' => 'features',
            'input_type' => 'multiselect',
            'filter_control' => 'multiselect',
            'post_control' => 'multiselect',
            'options' => ['Parking', 'Garden', 'Balcony'],
            'is_active' => true,
            'is_filterable' => true,
            'is_postable' => true,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ads/draft', [
            'main_category_id' => $seedAd->main_category_id,
            'city_id' => $seedAd->city_id,
            'title' => 'Multiselect draft',
            'description' => 'Draft with multiple features',
            'price' => 100,
            'currency' => 'GBP',
            'attributes' => [
                'features' => ['Parking', 'Garden'],
            ],
        ])->assertOk();

        $draftId = $response->json('data.ad.id');
        $this->assertNotNull($draftId);
        $this->assertDatabaseHas('ad_attribute_values', [
            'ad_id' => $draftId,
            'category_attribute_definition_id' => $definition->id,
            'value' => 'Parking',
        ]);
        $this->assertDatabaseHas('ad_attribute_values', [
            'ad_id' => $draftId,
            'category_attribute_definition_id' => $definition->id,
            'value' => 'Garden',
        ]);
    }

    private function pendingPaidAd(): array
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
        $category = Category::create([
            'status' => 'active',
            'sort' => 0,
        ]);
        $user = User::create([
            'name' => 'Payment Contract User',
            'first_name' => 'Payment',
            'last_name' => 'User',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'status' => '1',
            'city_id' => $city->id,
        ]);

        $ad = Ad::create([
            'user_id' => $user->id,
            'title' => 'Payment contract ad',
            'description' => 'Contract test',
            'country_id' => $country->id,
            'city_id' => $city->id,
            'main_category_id' => $category->id,
            'price' => 25,
            'currency' => 'GBP',
            'status' => 'pending',
            'listing_fee' => 5,
            'payment_status' => 'requires_payment',
            'stripe_payment_intent_id' => 'pi_'.Str::lower(Str::random(24)),
        ]);

        return [$user, $ad];
    }
}
