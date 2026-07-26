<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ListingPaymentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, ListingPaymentService $listings)
    {
        $payload = $request->getContent();
        if (! $this->validSignature($payload, (string) $request->header('Stripe-Signature'))) {
            return response()->json(['error' => 'Invalid Stripe signature'], Response::HTTP_BAD_REQUEST);
        }

        $event = json_decode($payload, true);
        if (($event['type'] ?? '') === 'payment_intent.succeeded') {
            $intent = $event['data']['object'] ?? [];
            $listings->publishPaidListing($intent['id'], (int) $intent['amount_received'], (string) $intent['currency']);
        }

        return response()->json(['received' => true]);
    }

    private function validSignature(string $payload, string $header): bool
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) return false;
        preg_match('/(?:^|,)t=(\d+)/', $header, $time);
        preg_match_all('/(?:^|,)v1=([^,]+)/', $header, $matches);
        if (empty($time[1]) || abs(time() - (int) $time[1]) > 300) return false;
        $expected = hash_hmac('sha256', $time[1].'.'.$payload, $secret);
        foreach ($matches[1] ?? [] as $signature) if (hash_equals($expected, $signature)) return true;
        return false;
    }
}
