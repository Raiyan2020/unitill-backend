<?php

namespace Tests\Unit;

use App\Http\Resources\MyAdResource;
use App\Models\Ad;
use Illuminate\Http\Request;
use Tests\TestCase;

class MyAdPaymentActionsTest extends TestCase
{
    public function test_pending_ad_exposes_pay_and_delete_actions(): void
    {
        $ad = new Ad([
            'status' => 'pending',
            'payment_status' => 'requires_payment',
            'listing_fee' => 5,
            'currency' => 'GBP',
        ]);

        $payload = (new MyAdResource($ad))->toArray(Request::create('/api/my-ads'));

        $this->assertTrue($payload['payment_required']);
        $this->assertContains('pay', $payload['available_actions']);
        $this->assertContains('delete', $payload['available_actions']);
    }

    public function test_draft_exposes_publish_and_delete_actions(): void
    {
        $ad = new Ad([
            'status' => 'draft',
            'payment_status' => 'not_required',
            'currency' => 'GBP',
        ]);

        $payload = (new MyAdResource($ad))->toArray(Request::create('/api/my-ads'));

        $this->assertFalse($payload['payment_required']);
        $this->assertContains('publish', $payload['available_actions']);
        $this->assertContains('delete', $payload['available_actions']);
    }
}
