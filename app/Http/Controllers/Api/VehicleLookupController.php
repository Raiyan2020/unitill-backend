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

        $ar = $request->header('lang') === 'ar';
        $data = $service->getMappedVehicleData($request->plate);

        if (isset($data['error'])) {
            // A provider outage is not the user's fault, so it must not be
            // reported as "vehicle not found" — otherwise they retype a
            // perfectly valid plate over and over.
            if ($data['error'] !== 'not_found') {
                return sendError(
                    __('api.vehicle.service_unavailable'),
                    [],
                    503
                );
            }

            return sendError(
                __('api.vehicle.not_found'),
                [],
                404
            );
        }

        return sendResponse($data, __('api.vehicle.details_retrieved'));
    }
}