<?php

namespace Tests\Feature;

use App\Models\AccountDeletionRequest;
use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use App\Models\UserFeatureRestriction;
use App\Support\ChatReportReason;
use App\Support\ReportPriority;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplianceFeaturesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_current_terms_returns_null_for_guest_and_boolean_for_authenticated_user(): void
    {
        $terms = TermsVersion::where('is_current', true)->firstOrFail();

        $this->getJson('/api/terms/current')
            ->assertOk()
            ->assertJsonPath('data.accepted', null);

        $user = $this->user();
        Sanctum::actingAs($user);

        $this->getJson('/api/terms/current')
            ->assertOk()
            ->assertJsonPath('data.accepted', false);

        TermsAcceptance::create([
            'user_id' => $user->id,
            'terms_version_id' => $terms->id,
            'accepted_at' => now(),
            'source' => 'app',
        ]);

        $this->getJson('/api/terms/current')
            ->assertOk()
            ->assertJsonPath('data.accepted', true);
    }

    public function test_user_can_accept_and_audit_the_current_terms_version(): void
    {
        $user = $this->user();
        $terms = TermsVersion::where('is_current', true)->firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson('/api/terms/accept', [
            'terms_version' => $terms->version,
            'accepted' => true,
        ])->assertOk()
            ->assertJsonPath('data.version', $terms->version);

        $this->assertDatabaseHas('terms_acceptances', [
            'user_id' => $user->id,
            'terms_version_id' => $terms->id,
            'source' => 'app',
        ]);

        $this->getJson('/api/terms/history')->assertOk()
            ->assertJsonPath('data.0.version', $terms->version);
    }

    public function test_old_terms_version_is_rejected(): void
    {
        $acceptancesBefore = TermsAcceptance::count();
        Sanctum::actingAs($this->user());

        $this->postJson('/api/terms/accept', [
            'terms_version' => 'obsolete-version',
            'accepted' => true,
        ])->assertStatus(422)
            ->assertJsonPath('data.terms_version.0', 'The accepted terms version is no longer current. Refresh the terms and try again.');

        $this->assertSame($acceptancesBefore, TermsAcceptance::count());
    }

    public function test_public_page_creates_a_deletion_request_without_login(): void
    {
        $requestsBefore = AccountDeletionRequest::count();
        $user = $this->user();

        $this->get('/delete-account')->assertOk()->assertSee('Request account deletion');
        $this->post('/delete-account', [
            'email' => $user->email,
            'reason' => 'I no longer use the service.',
            'confirm' => '1',
        ])->assertRedirect()->assertSessionHas('deletion_request_received', true);

        $this->assertDatabaseHas('account_deletion_requests', [
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'pending',
        ]);
        $this->assertSame($requestsBefore + 1, AccountDeletionRequest::count());
    }

    public function test_messaging_and_posting_can_be_restricted_independently(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        UserFeatureRestriction::create([
            'user_id' => $user->id,
            'feature' => 'messaging',
            'reason' => 'Messaging abuse review',
            'starts_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/conversations', [])->assertStatus(403)
            ->assertJsonMissing(['error_code' => 'feature_restricted'])
            ->assertJsonMissingPath('data');

        $this->postJson('/api/v2/conversations', [])->assertStatus(403)
            ->assertJsonPath('data.error_code', 'feature_restricted')
            ->assertJsonPath('data.feature', 'messaging');

        // Posting is still allowed through its middleware; validation reaches
        // the controller and returns 422, rather than a feature restriction.
        $this->postJson('/api/ads/draft', [])->assertStatus(422)
            ->assertJsonMissing(['error_code' => 'feature_restricted']);
    }

    public function test_legacy_and_v2_account_settings_have_separate_response_contracts(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->getJson('/api/account/settings')
            ->assertOk()
            ->assertJsonMissingPath('data.notifications.notify_marketing')
            ->assertJsonMissingPath('data.notifications.marketing_consent_at');

        $this->getJson('/api/v2/account/settings')
            ->assertOk()
            ->assertJsonPath('data.notifications.notify_marketing', false)
            ->assertJsonPath('data.notifications.marketing_consent_at', null);

        $this->putJson('/api/v2/account/settings', ['notify_marketing' => true])
            ->assertOk()
            ->assertJsonPath('data.notifications.notify_marketing', true)
            ->assertJsonPath('data.notifications.marketing_consent_at', fn ($value) => is_string($value));
    }

    public function test_terms_and_capabilities_are_only_added_to_v2_owner_profile(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->getJson('/api/show-profile')
            ->assertOk()
            ->assertJsonMissingPath('data.terms')
            ->assertJsonMissingPath('data.capabilities')
            ->assertJsonMissingPath('data.feature_restrictions');

        $this->getJson('/api/v2/show-profile')
            ->assertOk()
            ->assertJsonPath('data.terms.accepted', false)
            ->assertJsonPath('data.capabilities.can_post', true)
            ->assertJsonPath('data.capabilities.can_message', true)
            ->assertJsonPath('data.feature_restrictions', []);
    }

    public function test_serious_safety_reasons_are_exposed_only_by_v2_and_are_critical(): void
    {
        $this->getJson('/api/chat-report-reasons')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonMissing(['value' => ChatReportReason::CREDIBLE_THREAT]);

        $this->getJson('/api/v2/chat-report-reasons')
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonFragment(['value' => ChatReportReason::CHILD_SEXUAL_ABUSE_OR_EXPLOITATION])
            ->assertJsonFragment(['value' => ChatReportReason::CREDIBLE_THREAT]);

        foreach (ChatReportReason::seriousSafety() as $reason) {
            $this->assertSame(ReportPriority::CRITICAL, ReportPriority::fromReason($reason));
        }
    }

    private function user(): User
    {
        static $sequence = 0;
        $sequence++;

        return User::create([
            'name' => 'Compliance User',
            'first_name' => 'Compliance',
            'last_name' => 'User',
            'email' => "compliance{$sequence}@example.test",
            'password' => 'secret123',
            'status' => '1',
        ]);
    }
}
