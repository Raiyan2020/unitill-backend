<?php

namespace Tests\Unit;

use App\Models\Ad;
use App\Services\ListingPaymentService;
use App\Services\StripeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ListingPaymentStateTest extends TestCase
{
    public function test_pending_unpaid_ad_reports_payment_required(): void
    {
        $ad = new Ad([
            'status' => 'pending',
            'payment_status' => 'requires_payment',
            'listing_fee' => 5,
        ]);

        $state = app(ListingPaymentService::class)->publicationState($ad);

        $this->assertFalse($state['published']);
        $this->assertTrue($state['payment_required']);
        $this->assertSame(5.0, $state['amount']);
        $this->assertSame('GBP', $state['currency']);
    }

    public function test_paid_published_ad_reports_authoritative_success(): void
    {
        $ad = new Ad([
            'status' => 'published',
            'payment_status' => 'paid',
            'listing_fee' => 5,
        ]);

        $state = app(ListingPaymentService::class)->publicationState($ad);

        $this->assertTrue($state['published']);
        $this->assertFalse($state['payment_required']);
        $this->assertSame('paid', $state['payment_status']);
    }

    public function test_draft_is_not_live_and_does_not_claim_payment_is_due(): void
    {
        $ad = new Ad([
            'status' => 'draft',
            'payment_status' => 'not_required',
        ]);

        $state = app(ListingPaymentService::class)->publicationState($ad);

        $this->assertFalse($state['published']);
        $this->assertFalse($state['payment_required']);
    }

    public function test_succeeded_intent_reports_paid_while_publication_is_pending(): void
    {
        $ad = new Ad([
            'status' => 'pending',
            'payment_status' => 'requires_payment',
            'listing_fee' => 5,
            'stripe_payment_intent_id' => 'pi_test',
        ]);

        $state = app(ListingPaymentService::class)->publicationState($ad, [
            'status' => 'succeeded',
            'client_secret' => 'pi_test_secret_value',
        ]);

        $this->assertFalse($state['published']);
        $this->assertFalse($state['payment_required']);
        $this->assertSame('paid', $state['payment_status']);
        $this->assertSame('pi_test', $state['payment_intent_id']);
        $this->assertSame('pi_test_secret_value', $state['client_secret']);
    }

    public function test_payment_intent_creation_uses_an_ad_scoped_idempotency_key(): void
    {
        config(['services.stripe.secret' => 'sk_test_contract']);
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_test',
                'client_secret' => 'pi_test_secret_value',
            ]),
        ]);

        $ad = new Ad([
            'public_id' => 'PUBLIC123',
            'user_id' => 7,
            'listing_fee' => 5,
        ]);
        $ad->id = 42;

        app(StripeService::class)->createListingPaymentIntent($ad);

        Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', 'listing-payment-42-listing-500'));
    }

    public function test_extension_payment_uses_a_distinct_idempotency_key_from_the_original_listing_payment(): void
    {
        // Same ad id, different fee (extension price vs listing fee) — this is
        // exactly the collision that produced Stripe's "idempotent requests...
        // same parameters" error when the key was ad-id-only.
        config(['services.stripe.secret' => 'sk_test_contract']);
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_test',
                'client_secret' => 'pi_test_secret_value',
            ]),
        ]);

        $ad = new Ad([
            'public_id' => 'PUBLIC123',
            'user_id' => 7,
            'listing_fee' => 0.99,
        ]);
        $ad->id = 80;

        app(StripeService::class)->createListingPaymentIntent($ad, 'extension');

        Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', 'listing-payment-80-extension-99'));
    }
}
