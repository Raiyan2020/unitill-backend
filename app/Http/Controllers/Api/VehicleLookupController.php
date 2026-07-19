<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VehicleApiService;
use Illuminate\Http\Request;

class VehicleLookupController extends Controller
{
    public function lookup(Request $request, VehicleApiService $service)
    {
        $request->validate([
            'plate' => 'required|string|regex:/^[A-Z]{2}[0-9]{2}[A-Z]{3}$/|max:7',
        ]);

        $data = $service->getMappedVehicleData($request->plate);

        if (isset($data['error'])) {
            return sendError('Couldn’t find this vehicle. Please check the number or enter details manually.', [], 404);
        }

        return sendResponse($data, 'Vehicle details retrieved successfully');
    }
}