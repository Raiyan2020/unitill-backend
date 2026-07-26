<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountSettingsController extends Controller
{
    /**
     * Toggles the user may change. `notify_system` is deliberately absent:
     * system alerts are always on, so accepting it would let a client switch off
     * something the product says cannot be switched off.
     */
    private const TOGGLES = [
        'show_last_name',
        'show_approximate_location',
        'trusted_users_only',
        'notify_chat',
        'notify_ads',
    ];

    public function show(Request $request)
    {
        return sendResponse($this->payload($request->user()));
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (self::TOGGLES as $toggle) {
            // "sometimes" so the app can send a single switch rather than the
            // whole settings screen every time.
            $rules[$toggle] = 'sometimes|boolean';
        }
        $validated = $request->validate($rules);

        if ($validated === []) {
            return sendError(
                __('api.settings.none_supplied'),
                [],
                422
            );
        }

        $user = $request->user();
        foreach ($validated as $key => $value) {
            $user->{$key} = (bool) $value;
        }
        $user->save();

        return sendResponse(
            $this->payload($user->fresh()),
            __('api.settings.updated')
        );
    }

    private function payload($user): array
    {
        return [
            'privacy' => [
                'show_last_name' => (bool) $user->show_last_name,
                'show_approximate_location' => (bool) $user->show_approximate_location,
                'trusted_users_only' => (bool) $user->trusted_users_only,
            ],
            'notifications' => [
                'notify_chat' => (bool) $user->notify_chat,
                'notify_ads' => (bool) $user->notify_ads,
                'notify_system' => true,
            ],
        ];
    }
}
