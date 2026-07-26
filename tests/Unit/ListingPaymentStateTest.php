<?php

namespace Tests\Unit;

use App\Models\Ad;
use App\Services\ListingPaymentService;
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
}
