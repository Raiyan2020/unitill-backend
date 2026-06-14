<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceIdentifierRequest;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Auth;

class UserDeviceController extends Controller
{
    public function storeIdentifier(StoreDeviceIdentifierRequest $request)
    {
        $user = Auth::user();
        $lang = $request->header('lang') === 'ar';
        $deviceIdentifier = $request->input('device_identifier');

        $device = UserDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_identifier' => $deviceIdentifier,
            ],
            [
                'last_seen_at' => now(),
                'is_active' => true,
            ]
        );

        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $currentToken->forceFill([
                'device_identifier' => $deviceIdentifier,
            ])->save();
        }

        return sendResponse([
            'id' => $device->id,
            'device_identifier' => $device->device_identifier,
        ], $lang ? 'تم حفظ معرّف الجهاز' : 'Device identifier saved');
    }
}
