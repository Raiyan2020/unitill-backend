<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MyFatoorahService
{
    public function executePayment(array $data, $orderId): string
    {
        $response = Http::withToken(config('services.myfatoorah.token'))
            ->withHeaders([
                'accept' => 'application/json',
                'Content-type' => 'application/json',
            ])
            ->post(config('services.myfatoorah.base_url') . '/ExecutePayment', $data)
            ->json();

        if (!($response['IsSuccess'] ?? false)) {
            Log::error('MyFatoorah ExecutePayment Failed', [
                'order_id' => $orderId,
                'request_data' => $data,
                'response' => $response,
            ]);

            throw ValidationException::withMessages([
                'payment' => 'حدث خطأ أثناء إنشاء الفاتورة، الرجاء المحاولة لاحقًا.',
            ]);
        }

        return $response['Data']['PaymentURL'];

// this when you want to user webhook
//        return [
//            'payment_url' => $response['Data']['PaymentURL'],
//            'invoice_id'  => $response['Data']['InvoiceId'],
//        ];

    }
}
