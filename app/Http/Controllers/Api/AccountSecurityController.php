<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\ActiveSessionResource;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AccountSecurityController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $lang = $request->header('lang') === 'ar';
        $locale = $lang ? 'ar' : 'en';
        $currentTokenId = $user->currentAccessToken()?->id;

        $sessions = $user->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get();

        $passwordUpdatedAt = $user->password_updated_at ?? $user->updated_at;

        return sendResponse([
            'password' => [
                'last_updated_at' => $passwordUpdatedAt?->toIso8601String(),
                'last_updated_ago' => $passwordUpdatedAt
                    ? strtoupper($passwordUpdatedAt->locale($locale)->diffForHumans())
                    : null,
            ],
            'sessions' => ActiveSessionResource::collectionWithCurrent($sessions, $currentTokenId),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = Auth::user();
        $lang = $request->header('lang') === 'ar';
        $locale = $lang ? 'ar' : 'en';

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return sendError(
                $lang ? 'كلمة المرور الحالية غير صحيحة' : 'Current password is incorrect',
                [],
                422
            );
        }

        $user->update([
            'password' => $request->input('new_password'),
        ]);

        $passwordUpdatedAt = $user->fresh()->password_updated_at;

        return sendResponse([
            'password' => [
                'last_updated_at' => $passwordUpdatedAt?->toIso8601String(),
                'last_updated_ago' => $passwordUpdatedAt
                    ? strtoupper($passwordUpdatedAt->locale($locale)->diffForHumans())
                    : null,
            ],
        ], $lang ? 'تم تغيير كلمة المرور بنجاح' : 'Password changed successfully');
    }

    public function logoutOtherDevices(Request $request)
    {
        $user = Auth::user();
        $lang = $request->header('lang') === 'ar';
        $currentToken = $user->currentAccessToken();

        if (! $currentToken) {
            return sendError($lang ? 'غير مسجّل الدخول' : 'Not authenticated', [], 401);
        }

        $deletedCount = $user->tokens()
            ->where('id', '!=', $currentToken->id)
            ->delete();

        UserDevice::query()
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);

        if ($currentToken->device_identifier) {
            UserDevice::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'device_identifier' => $currentToken->device_identifier,
                ],
                [
                    'device_name' => $currentToken->device_name,
                    'country_code' => $currentToken->country_code,
                    'last_seen_at' => now(),
                    'is_active' => true,
                ]
            );
        }

        return sendResponse(
            ['logged_out_devices_count' => $deletedCount],
            $lang ? 'تم تسجيل الخروج من الأجهزة الأخرى' : 'Logged out from other devices'
        );
    }
}
