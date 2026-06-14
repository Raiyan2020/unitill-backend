<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LoginLogger
{
    public static function record(User $user, Request $request, string $type): UserLoginLog
    {
        $deviceName = $request->input('device_name');

        if (! $deviceName && $request->filled('device_type')) {
            $deviceName = match ($request->input('device_type')) {
                'ios' => 'iPhone',
                'android' => 'Android Device',
                default => 'Mobile Device',
            };
        }

        $log = UserLoginLog::create([
            'user_id' => $user->id,
            'type' => $type,
            'device_identifier' => $request->input('device_identifier'),
            'device_name' => $deviceName,
            'city_name' => $request->input('city_name'),
            'country_code' => $request->filled('country_code')
                ? strtoupper((string) $request->input('country_code'))
                : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Log::info('User login', [
            'user_id' => $user->id,
            'type' => $type,
            'device_identifier' => $request->input('device_identifier'),
            'ip' => $request->ip(),
        ]);

        return $log;
    }
}
