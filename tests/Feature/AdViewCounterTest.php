<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\AdView;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdViewCounterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_guest_opening_an_ad_counts_a_view(): void
    {
        $ad = $this->publishedAd();

        $this->getJson("/api/ads/{$ad->id}")
            ->assertOk()
            ->assertJsonPath('data.views_count', 1);

        $this->assertSame(1, $ad->views()->count());
    }

    public function test_repeat_opens_by_the_same_user_do_not_move_the_count(): void
    {
        $ad = $this->publishedAd();
        $viewer = $this->newUser($ad->city_id);

        Sanctum::actingAs($viewer);

        $this->getJson("/api/ads/{$ad->id}")->assertJsonPath('data.views_count', 1);
        $this->getJson("/api/ads/{$ad->id}")->assertJsonPath('data.views_count', 1);

        // "and again tomorrow" — a registered viewer is deduped permanently,
        // not just within a day.
        $this->travelTo(now()->addDay());
        $this->getJson("/api/ads/{$ad->id}")->assertJsonPath('data.views_count', 1);

        $this->assertSame(1, $ad->views()->count());
    }

    public function test_a_second_distinct_user_moves_the_count_to_two(): void
    {
        $ad = $this->publishedAd();
        $userA = $this->newUser($ad->city_id);
        $userB = $this->newUser($ad->city_id);

        Sanctum::actingAs($userA);
        $this->getJson("/api/ads/{$ad->id}")->assertJsonPath('data.views_count', 1);

        Sanctum::actingAs($userB);
        $this->getJson("/api/ads/{$ad->id}")->assertJsonPath('data.views_count', 2);

        $this->assertSame(2, $ad->views()->count());
    }

    public function test_the_owner_opening_their_own_ad_is_never_counted(): void
    {
        $ad = $this->publishedAd();
        $userA = $this->newUser($ad->city_id);
        $userB = $this->newUser($ad->city_id);

        Sanctum::actingAs($userA);
        $this->getJson("/api/ads/{$ad->id}")->assertJsonPath('data.views_count', 1);
        Sanctum::actingAs($userB);
        $this->getJson("/api/ads/{$ad->id}")->assertJsonPath('data.views_count', 2);

        // The owner opens the same detail endpoint (there is no separate
        // my-ads/{id} GET / edit-read route) any number of times.
        Sanctum::actingAs($ad->user);
        $this->getJson("/api/ads/{$ad->id}")->assertJsonPath('data.views_count', 2);
        $this->getJson("/api/ads/{$ad->id}")->assertJsonPath('data.views_count', 2);

        $this->assertSame(2, $ad->views()->count());
        $this->assertSame(0, AdView::where('ad_id', $ad->id)->where('user_id', $ad->user_id)->count());
    }

    public function test_two_simultaneous_opens_by_the_same_new_user_count_once_and_never_500(): void
    {
        $ad = $this->publishedAd();
        $viewer = $this->newUser($ad->city_id);
        Sanctum::actingAs($viewer);

        // Simulate the race the unique index is meant to absorb: the first
        // request's row already exists by the time of this call, so the
        // controller's own duplicate insert must be swallowed, not surfaced
        // as a 500.
        AdView::create(['ad_id' => $ad->id, 'user_id' => $viewer->id]);

        $this->getJson("/api/ads/{$ad->id}")
            ->assertOk()
            ->assertJsonPath('data.views_count', 1);

        $this->assertSame(1, $ad->views()->count());
    }

    public function test_guests_are_deduped_by_ip_and_user_agent_within_a_day(): void
    {
        $ad = $this->publishedAd();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1', 'HTTP_USER_AGENT' => 'GuestApp/1.0']);
        $this->getJson("/api/ads/{$ad->id}")->assertJsonPath('data.views_count', 1);

        // Same fingerprint, same day — no increment.
        $this->getJson("/api/ads/{$ad->id}")->assertJsonPath('data.views_count', 1);

        // Different fingerprint — counts as a new viewer.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2', 'HTTP_USER_AGENT' => 'GuestApp/1.0']);
        $this->getJson("/api/ads/{$ad->id}")->assertJsonPath('data.views_count', 2);

        $this->assertSame(2, $ad->views()->count());
    }

    public function test_views_count_is_returned_on_my_ads_list(): void
    {
        $ad = $this->publishedAd();
        $viewer = $this->newUser($ad->city_id);

        Sanctum::actingAs($viewer);
        $this->getJson("/api/ads/{$ad->id}")->assertJsonPath('data.views_count', 1);

        Sanctum::actingAs($ad->user);
        $this->getJson('/api/my-ads?status=active')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $ad->id)
            ->assertJsonPath('data.data.0.views_count', 1);
    }

    private function newUser(int $cityId): User
    {
        return User::create([
            'name' => 'Viewer',
            'first_name' => 'Viewer',
            'last_name' => 'User',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'status' => '1',
            'city_id' => $cityId,
        ]);
    }

    private function publishedAd(): Ad
    {
        $country = Country::create(['country_code' => strtoupper(Str::random(2)), 'status' => 'active']);
        $city = City::create([
            'country_id' => $country->id,
            'country_code' => $country->country_code,
            'status' => 'active',
            'code' => Str::upper(Str::random(8)),
        ]);
        $category = Category::create(['status' => 'active', 'sort' => 0]);
        $owner = $this->newUser($city->id);

        return Ad::create([
            'user_id' => $owner->id,
            'title' => 'Test listing',
            'description' => 'A listing used to test the view counter',
            'country_id' => $country->id,
            'city_id' => $city->id,
            'main_category_id' => $category->id,
            'price' => 122,
            'currency' => 'GBP',
            'status' => 'published',
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }
}
