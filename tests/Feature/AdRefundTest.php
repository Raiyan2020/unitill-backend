<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Admin;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Tests\TestCase;

class AdRefundTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_refund_a_paid_ad(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        [, $ad] = $this->paidAd();

        Sanctum::actingAs(Admin::create([
            'name' => 'Refund Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]));

        Http::fake([
            'api.stripe.com/v1/refunds' => Http::response(['id' => 're_test123', 'status' => 'succeeded']),
        ]);

        $this->postJson("/api/admin/ads/{$ad->id}/refund", ['reason' => 'Listing failed to deliver the paid service'])
            ->assertOk()
            ->assertJsonPath('data.refund_status', 'refunded')
            ->assertJsonPath('data.refund_reference', 're_test123');

        $fresh = $ad->fresh();
        $this->assertSame('refunded', $fresh->refund_status);
        $this->assertNotNull($fresh->refunded_at);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $fresh->user_id,
            'title' => __('api.refund.accepted_title'),
        ]);
    }

    public function test_admin_can_decline_a_refund_request(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        [, $ad] = $this->paidAd();

        Sanctum::actingAs(Admin::create([
            'name' => 'Refund Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]));

        $this->postJson("/api/admin/ads/{$ad->id}/refund/decline", ['reason' => 'Item was sold, not a service failure'])
            ->assertOk()
            ->assertJsonPath('data.refund_status', 'declined');

        $fresh = $ad->fresh();
        $this->assertSame('declined', $fresh->refund_status);
        $this->assertNotNull($fresh->refund_declined_at);

        $notification = UserNotification::where('user_id', $fresh->user_id)
            ->where('title', __('api.refund.declined_title'))
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Item was sold, not a service failure', $notification->body);
    }

    public function test_admin_decline_requires_a_reason(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        [, $ad] = $this->paidAd();

        Sanctum::actingAs(Admin::create([
            'name' => 'Refund Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]));

        $this->postJson("/api/admin/ads/{$ad->id}/refund/decline")->assertStatus(422);
    }

    public function test_cannot_decide_on_a_refund_twice(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        [, $ad] = $this->paidAd();
        $ad->update(['refund_status' => 'declined']);

        Sanctum::actingAs(Admin::create([
            'name' => 'Refund Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]));

        $this->postJson("/api/admin/ads/{$ad->id}/refund/decline", ['reason' => 'test'])->assertStatus(422);
    }

    public function test_admin_refund_requires_a_reason(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        [, $ad] = $this->paidAd();

        Sanctum::actingAs(Admin::create([
            'name' => 'Refund Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]));

        $this->postJson("/api/admin/ads/{$ad->id}/refund")->assertStatus(422);
    }

    public function test_cannot_refund_an_ad_with_no_paid_stripe_payment(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        [, $ad] = $this->paidAd();
        $ad->update(['payment_status' => 'free', 'stripe_payment_intent_id' => null]);

        Sanctum::actingAs(Admin::create([
            'name' => 'Refund Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]));

        $this->postJson("/api/admin/ads/{$ad->id}/refund", ['reason' => 'test'])->assertStatus(422);
    }

    public function test_cannot_refund_twice(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        [, $ad] = $this->paidAd();
        $ad->update(['refund_status' => 'refunded']);

        Sanctum::actingAs(Admin::create([
            'name' => 'Refund Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]));

        $this->postJson("/api/admin/ads/{$ad->id}/refund", ['reason' => 'test'])->assertStatus(422);
    }

    public function test_user_can_request_a_refund_for_their_own_paid_ad(): void
    {
        [$user, $ad] = $this->paidAd();

        Sanctum::actingAs($user);

        $this->postJson("/api/my-ads/{$ad->id}/refund-request", ['reason' => 'Item sold outside the app before it went live'])
            ->assertOk()
            ->assertJsonPath('data.refund_status', 'requested');

        $fresh = $ad->fresh();
        $this->assertSame('requested', $fresh->refund_status);
        $this->assertNotNull($fresh->refund_requested_at);
        $this->assertSame('Item sold outside the app before it went live', $fresh->refund_request_reason);
    }

    public function test_user_cannot_request_a_refund_for_someone_elses_ad(): void
    {
        [, $ad] = $this->paidAd();
        $otherUser = User::create([
            'name' => 'Other User',
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'status' => '1',
            'city_id' => $ad->city_id,
        ]);

        Sanctum::actingAs($otherUser);

        $this->postJson("/api/my-ads/{$ad->id}/refund-request", ['reason' => 'test'])->assertStatus(422);
    }

    public function test_user_cannot_request_a_refund_twice(): void
    {
        [$user, $ad] = $this->paidAd();
        $ad->update(['refund_status' => 'requested']);

        Sanctum::actingAs($user);

        $this->postJson("/api/my-ads/{$ad->id}/refund-request", ['reason' => 'test'])->assertStatus(422);
    }

    public function test_user_can_list_their_own_refund_requests(): void
    {
        [$user, $ad] = $this->paidAd();
        $ad->update([
            'refund_status' => 'requested',
            'refund_requested_at' => now(),
            'refund_request_reason' => 'Changed my mind',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/my-ads/refund-requests')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $ad->id)
            ->assertJsonPath('data.data.0.refund_status', 'requested');
    }

    public function test_admin_can_list_refund_requests_filtered_by_status(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        [, $requested] = $this->paidAd();
        $requested->update([
            'refund_status' => 'requested',
            'refund_requested_at' => now(),
            'refund_request_reason' => 'test',
        ]);
        [, $refunded] = $this->paidAd();
        $refunded->update(['refund_status' => 'refunded', 'refunded_at' => now()]);

        Sanctum::actingAs(Admin::create([
            'name' => 'Refund Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]));

        $this->getJson('/api/admin/refund-requests?status=requested')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $requested->id)
            ->assertJsonCount(1, 'data.data');
    }

    /** @return array{0: User, 1: Ad} */
    private function paidAd(): array
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
            'name' => 'Refund User',
            'first_name' => 'Refund',
            'last_name' => 'User',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'status' => '1',
            'city_id' => $city->id,
        ]);

        $ad = Ad::create([
            'user_id' => $user->id,
            'title' => 'Paid listing',
            'description' => 'Already paid for',
            'country_id' => $country->id,
            'city_id' => $city->id,
            'main_category_id' => $category->id,
            'price' => 10,
            'currency' => 'GBP',
            'status' => 'published',
            'listing_fee' => 0.99,
            'payment_status' => 'paid',
            'stripe_payment_intent_id' => 'pi_paid_'.Str::lower(Str::random(16)),
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return [$user, $ad];
    }
}
