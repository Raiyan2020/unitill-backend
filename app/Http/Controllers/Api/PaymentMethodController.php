<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{

    public function __invoke(Request $request)
    {

        $payments = PaymentMethod::where('status', 'active')->get();
        return sendResponse(PaymentMethodResource::collection($payments));

    }
}
