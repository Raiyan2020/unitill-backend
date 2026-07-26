<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PostcodeService;
use Illuminate\Http\Request;
use App\Models\City;

class PostcodeController extends Controller
{
    protected $postcodeService;

    public function __construct(PostcodeService $postcodeService)
    {
        $this->postcodeService = $postcodeService;
    }

    public function lookup(Request $request)
    {
        $request->validate([
            'postcode' => [
                'required',
                'string',
                'regex:/^([A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2}|GIR ?0AA)$/i'
            ],
        ]);

        $details = $this->postcodeService->getDetails($request->postcode);

        if (!$details) {
            return sendError('Invalid postcode or service unavailable', [], 404);
        }

        $city = City::whereHas('translations', function ($q) use ($details) {
            $q->where('name', $details['city']);
        })->first();

        $details['city_id'] = $city ? $city->id : null;

        return sendResponse($details, 'Postcode details retrieved successfully');
    }
}