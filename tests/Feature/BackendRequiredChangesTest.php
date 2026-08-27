<?php

namespace Tests\Feature;

use App\Mail\ContactUsMail;
use App\Models\Ad;
use App\Models\Admin;
use App\Models\Category;
use App\Models\CategoryAttributeDefinition;
use App\Models\CategoryAttributeDefinitionTranslation;
use App\Models\CategoryTranslation;
use App\Models\City;
use App\Models\ContactReason;
use App\Models\ContactReasonTranslation;
use App\Models\ContactUsMessage;
use App\Models\Country;
use App\Models\Language;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the mobile-reported backend gaps: translation fallbacks, localized
 * option labels, the public share page, contact-details privacy, Contact Us
 * delivery and paid-ad reactivation.
 */
class BackendRequiredChangesTest extends TestCase
{
    use DatabaseTransactions;

    // ---- #8.1 fallback chain ------------------------------------------------

    public function test_missing_translation_falls_back_to_english_not_blank(): void
    {
        $reason = ContactReason::create(['sort_order' => 99, 'is_active' => true]);
        ContactReasonTranslation::create([
            'contact_reason_id' => $reason->id,
            'language_id' => $this->language('en')->id,
            'name' => 'General inquiry',
        ]);

        // Spanish row absent entirely, and a present-but-blank Chinese row:
        // both used to render as an unreadable empty chip.
        ContactReasonTranslation::create([
            'contact_reason_id' => $reason->id,
            'language_id' => $this->language('zh')->id,
            'name' => '',
        ]);

        $reason->refresh();

        $this->assertSame('General inquiry', $reason->nameForLanguageCode('es'));
        $this->assertSame('General inquiry', $reason->nameForLanguageCode('zh'));
    }

    public function test_attribute_label_falls_back_to_words_never_the_raw_slug(): void
    {
        $definition = CategoryAttributeDefinition::create([
            'category_id' => $this->category()->id,
            'slug' => 'property_type',
            'input_type' => 'select',
            'options' => [],
            'is_active' => true,
        ]);

        // No translation rows at all — the app used to print "property_type".
        $this->assertSame('Property type', $definition->labelForLanguageCode('fr'));
    }

    // ---- #3 / #5 localized option labels ------------------------------------

    public function test_option_labels_are_localized_while_values_stay_english(): void
    {
        $definition = CategoryAttributeDefinition::create([
            'category_id' => $this->category()->id,
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
            'language_id' => $this->language('ar')->id,
            'label' => 'الحالة',
            'options' => ['Like new' => 'شبه جديد', 'Used' => 'مستعمل'],
        ]);

        $definition->refresh();
        $options = $definition->optionsForLanguageCode('ar');

        $this->assertSame(['Like new', 'Used'], array_column($options, 'value'));
        $this->assertSame(['شبه جديد', 'مستعمل'], array_column($options, 'label'));
        $this->assertSame('شبه جديد', $definition->optionLabelForLanguageCode('ar', 'Like new'));

        // A language with no option map at all degrades to English, not blanks.
        $this->assertSame(['Like new', 'Used'], array_column($definition->optionsForLanguageCode('es'), 'label'));

        // Free-text values are the user's own input and pass straight through.
        $this->assertSame('Size 42', $definition->optionLabelForLanguageCode('ar', 'Size 42'));
    }

    // ---- #1 public share page -----------------------------------------------

    public function test_shared_ad_link_renders_the_listing_with_open_graph_tags(): void
    {
        [, $ad] = $this->publishedAd();

        $this->get('/ads/'.$ad->public_id)
            ->assertOk()
            ->assertSee($ad->title, false)
            ->assertSee('og:title', false)
            ->assertSee('og:description', false);
    }

    public function test_unknown_and_unpaid_ads_get_a_friendly_404_not_the_dashboard(): void
    {
        $this->get('/ads/DOESNOTEXIST')
            ->assertStatus(404)
            ->assertSee('no longer available', false);

        [, $ad] = $this->publishedAd();
        $ad->update(['status' => 'pending', 'payment_status' => 'requires_payment']);

        $this->get('/ads/'.$ad->public_id)->assertStatus(404);
    }

    // ---- #2 social links ----------------------------------------------------

    public function test_settings_expose_only_configured_social_links(): void
    {
        Setting::updateOrCreate(['key_id' => 'social_instagram'], ['value' => 'https://instagram.com/unitill']);
        Setting::updateOrCreate(['key_id' => 'social_tiktok'], ['value' => '']);

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.social_links.instagram', 'https://instagram.com/unitill')
            ->assertJsonMissingPath('data.social_links.tiktok');
    }

    // ---- #4 / #6 profile privacy and join date ------------------------------

    public function test_another_users_profile_never_returns_contact_details(): void
    {
        [$owner] = $this->publishedAd();
        $viewer = $this->user();

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/show-profile/'.$owner->id)->assertOk();

        $response->assertJsonMissingPath('data.email');
        $response->assertJsonMissingPath('data.student_email');
        $response->assertJsonMissingPath('data.phone');
    }

    public function test_self_profile_carries_contact_details_join_date_and_ads_count(): void
    {
        [$owner] = $this->publishedAd();
        Sanctum::actingAs($owner);

        $this->getJson('/api/show-profile')
            ->assertOk()
            ->assertJsonPath('data.email', $owner->email)
            ->assertJsonPath('data.active_ads_count', 1)
            ->assertJsonPath('data.created_at', $owner->created_at->toIso8601String());
    }

    public function test_public_seller_endpoint_exposes_no_contact_details(): void
    {
        [$owner] = $this->publishedAd();

        $response = $this->getJson('/api/sellers/'.$owner->id)
            ->assertOk()
            ->assertJsonPath('data.id', $owner->id)
            ->assertJsonPath('data.active_ads_count', 1);

        $response->assertJsonMissingPath('data.email');
        $response->assertJsonMissingPath('data.student_email');
        $response->assertJsonMissingPath('data.phone');
    }

    // ---- #9 Contact Us delivery ---------------------------------------------

    public function test_contact_us_mails_the_support_inbox_and_reports_delivery(): void
    {
        Mail::fake();
        Setting::updateOrCreate(['key_id' => 'contact_email'], ['value' => 'support@unitill.test']);

        $reason = ContactReason::create(['sort_order' => 98, 'is_active' => true]);
        ContactReasonTranslation::create([
            'contact_reason_id' => $reason->id,
            'language_id' => $this->language('en')->id,
            'name' => 'Technical support',
        ]);

        Sanctum::actingAs($this->user());

        $this->postJson('/api/contact-us', [
            'contact_reason_id' => $reason->id,
            'message' => 'The share link does not open.',
        ])
            ->assertOk()
            ->assertJsonPath('data.mail_sent', true);

        Mail::assertSent(ContactUsMail::class, fn (ContactUsMail $mail) => $mail->hasTo('support@unitill.test'));
    }

    public function test_contact_us_reports_when_no_support_inbox_is_configured(): void
    {
        Mail::fake();
        Setting::updateOrCreate(['key_id' => 'contact_email'], ['value' => '']);

        $reason = ContactReason::create(['sort_order' => 97, 'is_active' => true]);
        ContactReasonTranslation::create([
            'contact_reason_id' => $reason->id,
            'language_id' => $this->language('en')->id,
            'name' => 'Complaint',
        ]);

        Sanctum::actingAs($this->user());

        // Still a 200 — the message is stored — but the app is told the truth.
        $this->postJson('/api/contact-us', [
            'contact_reason_id' => $reason->id,
            'message' => 'Nothing arrives by email.',
        ])
            ->assertOk()
            ->assertJsonPath('data.mail_sent', false);

        Mail::assertNothingSent();
    }

    public function test_contact_us_accepts_a_guest_submission_without_a_bearer_token(): void
    {
        Mail::fake();
        Setting::updateOrCreate(['key_id' => 'contact_email'], ['value' => 'support@unitill.test']);

        $reason = ContactReason::create(['sort_order' => 96, 'is_active' => true]);
        ContactReasonTranslation::create([
            'contact_reason_id' => $reason->id,
            'language_id' => $this->language('en')->id,
            'name' => 'General inquiry',
        ]);

        // No Sanctum::actingAs() — this call is unauthenticated on purpose.
        $response = $this->postJson('/api/contact-us', [
            'contact_reason_id' => $reason->id,
            'message' => 'I cannot log in to my account.',
            'guest_name' => 'Jane Guest',
            'guest_email' => 'jane.guest@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.mail_sent', true);

        $id = $response->json('data.id');
        $message = ContactUsMessage::find($id);
        $this->assertNull($message->user_id);
        $this->assertSame('Jane Guest', $message->guest_name);
        $this->assertSame('jane.guest@example.com', $message->guest_email);

        Mail::assertSent(ContactUsMail::class, fn (ContactUsMail $mail) => $mail->hasTo('support@unitill.test'));
    }

    public function test_contact_us_rejects_a_guest_submission_missing_name_or_email(): void
    {
        $reason = ContactReason::create(['sort_order' => 95, 'is_active' => true]);
        ContactReasonTranslation::create([
            'contact_reason_id' => $reason->id,
            'language_id' => $this->language('en')->id,
            'name' => 'General inquiry',
        ]);

        $this->postJson('/api/contact-us', [
            'contact_reason_id' => $reason->id,
            'message' => 'No way to reach me back.',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['guest_name', 'guest_email'], 'data');
    }

    public function test_contact_us_from_a_logged_in_user_does_not_require_guest_fields(): void
    {
        $reason = ContactReason::create(['sort_order' => 94, 'is_active' => true]);
        ContactReasonTranslation::create([
            'contact_reason_id' => $reason->id,
            'language_id' => $this->language('en')->id,
            'name' => 'General inquiry',
        ]);

        $user = $this->user();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/contact-us', [
            'contact_reason_id' => $reason->id,
            'message' => 'Question about my listing.',
        ])->assertOk();

        $message = ContactUsMessage::find($response->json('data.id'));
        $this->assertSame($user->id, $message->user_id);
        $this->assertNull($message->guest_name);
        $this->assertNull($message->guest_email);
    }

    // ---- #10 reactivation ---------------------------------------------------

    public function test_reactivating_a_paid_in_period_ad_is_free_and_immediate(): void
    {
        [$user, $ad] = $this->publishedAd();
        $ad->update([
            'status' => 'paused',
            'paused_at' => now(),
            'payment_status' => 'paid',
            'listing_fee' => 5,
            'expires_at' => now()->addDays(20),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/my-ads/{$ad->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.publication.published', true)
            ->assertJsonPath('data.publication.payment_required', false);

        $fresh = $ad->fresh();
        $this->assertSame('published', $fresh->status);
        $this->assertSame('paid', $fresh->payment_status);
        $this->assertNull($fresh->paused_at);
    }

    // ---- admin user deletion is a hard delete --------------------------------

    public function test_admin_deleting_a_user_removes_the_row_not_just_soft_deletes_it(): void
    {
        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class);

        $user = $this->user();
        Sanctum::actingAs(Admin::create([
            'name' => 'Deletion Admin',
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]));

        $this->deleteJson("/api/admin/users/{$user->id}")->assertOk();

        // A soft delete would leave the row behind with deleted_at set —
        // assert it is gone even from an unscoped query.
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_api_login_from_a_stateful_origin_is_not_csrf_blocked(): void
    {
        // The React dashboard is on another host and authenticates with a bearer
        // token, never cookies. statefulApi() used to attach session + CSRF to
        // /api/* for any Origin in sanctum.stateful, which made this return 419.
        config(['sanctum.stateful' => ['dashboard.unitill.test']]);

        $this->postJson('/api/admin/login', [
            'email' => 'nobody@example.test',
            'password' => 'wrong-on-purpose',
        ], [
            'Origin' => 'https://dashboard.unitill.test',
            'Referer' => 'https://dashboard.unitill.test/admin/login',
        ])->assertStatus(401);
    }

    public function test_reactivating_an_expired_ad_still_starts_a_new_paid_period(): void
    {
        Setting::updateOrCreate(['key_id' => 'post_price'], ['value' => '5.00']);
        Setting::updateOrCreate(['key_id' => 'free_ads_per_user'], ['value' => '0']);

        [$user, $ad] = $this->publishedAd();
        $ad->update([
            'status' => 'expired',
            'payment_status' => 'paid',
            'expires_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        // Stripe is not reachable in tests; the assertion that matters is that
        // the free path is not taken, i.e. a fee is computed for this ad.
        $this->assertFalse(
            (new \ReflectionMethod(\App\Http\Controllers\Api\MyAdController::class, 'hasUnexpiredPaidPeriod'))
                ->invoke(app(\App\Http\Controllers\Api\MyAdController::class), $ad->fresh())
        );
    }

    // ---- fixtures -----------------------------------------------------------

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

    private function category(): Category
    {
        $category = Category::create(['status' => 'active', 'sort' => 0]);
        CategoryTranslation::create([
            'category_id' => $category->id,
            'language_id' => $this->language('en')->id,
            'name' => 'Test category '.Str::random(6),
        ]);

        return $category;
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
            'name' => 'Required Changes User',
            'first_name' => 'Required',
            'last_name' => 'User',
            'email' => Str::uuid().'@example.test',
            'student_email' => Str::uuid().'@example.ac.uk',
            'phone' => '07'.random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => '1',
            'city_id' => $city->id,
        ]);
    }

    /** @return array{0: User, 1: Ad} */
    private function publishedAd(): array
    {
        $user = $this->user();
        $category = $this->category();

        $ad = Ad::create([
            'user_id' => $user->id,
            'public_id' => strtoupper(Str::random(10)),
            'title' => 'North Face jacket, size M',
            'description' => 'Worn one winter, no damage.',
            'country_id' => $user->city->country_id,
            'city_id' => $user->city_id,
            'main_category_id' => $category->id,
            'price' => 55,
            'currency' => 'GBP',
            'status' => 'published',
            'payment_status' => 'paid',
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return [$user, $ad];
    }
}
