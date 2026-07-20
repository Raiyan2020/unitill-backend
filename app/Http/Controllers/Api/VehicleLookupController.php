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
                    $ar
                        ? 'خدمة بيانات المركبات غير متاحة حالياً. أدخل التفاصيل يدوياً.'
                        : 'The vehicle data service is unavailable right now. Please enter the details manually.',
                    [],
                    503
                );
            }

            return sendError(
                $ar
                    ? 'لم نعثر على هذه المركبة. تحقق من الرقم أو أدخل التفاصيل يدوياً.'
                    : 'Couldn’t find this vehicle. Please check the number or enter details manually.',
                [],
                404
            );
        }

        return sendResponse($data, $ar ? 'تم جلب بيانات المركبة بنجاح' : 'Vehicle details retrieved successfully');
    }
}