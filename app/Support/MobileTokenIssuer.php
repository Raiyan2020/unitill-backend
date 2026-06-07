<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;

class MobileTokenIssuer
{
    public static function issue(User $user, Request $request): string
    {
        $deviceName = $request->input('device_name');
        $deviceIdentifier = $request->input('device_identifier');
        $cityName = $request->input('city_name');
        $countryCode = $request->input('country_code');

        if (! $deviceName && $request->filled('device_type')) {
            $deviceName = match ($request->input('device_type')) {
                'ios' => 'iPhone',
                'android' => 'Android Device',
                default => 'Mobile Device',
            };
        }

        $tokenResult = $user->createToken('mobile');

        $tokenResult->accessToken->forceFill([
            'device_name' => $deviceName,
            'device_identifier' => $deviceIdentifier,
            'city_name' => $cityName,
            'country_code' => $countryCode ? strtoupper((string) $countryCode) : null,
        ])->save();

        if ($deviceIdentifier) {
            UserDevice::query()
                ->where('user_id', $user->id)
                ->where('device_identifier', '!=', $deviceIdentifier)
                ->update(['is_active' => false]);

            UserDevice::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'device_identifier' => $deviceIdentifier,
                ],
                [
                    'device_name' => $deviceName,
                    'country_code' => $countryCode ? strtoupper((string) $countryCode) : null,
                    'last_seen_at' => now(),
                    'is_active' => true,
                ]
            );
        }

        return $tokenResult->plainTextToken;
    }
}
