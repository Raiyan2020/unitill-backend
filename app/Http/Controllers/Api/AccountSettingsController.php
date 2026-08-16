<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

class AccountSettingsController extends Controller
{
    public function __construct(private readonly PushNotificationService $pushNotifications) {}

    /**
     * Toggles the user may change. `notify_system` is deliberately absent:
     * system alerts are always on, so accepting it would let a client switch off
     * something the product says cannot be switched off.
     */
    private const LEGACY_TOGGLES = [
        'show_last_name',
        'show_approximate_location',
        'trusted_users_only',
        'notify_chat',
        'notify_ads',
    ];

    public function show(Request $request)
    {
        return sendResponse($this->payload($request->user(), $this->isV2($request)));
    }

    public function update(Request $request)
    {
        $rules = [];
        $toggles = self::LEGACY_TOGGLES;
        if ($this->isV2($request)) {
            $toggles[] = 'notify_marketing';
        }

        foreach ($toggles as $toggle) {
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
        // Marketing consent must be its own timestamped record, separate from
        // the general terms-of-use acceptance — stamped fresh every time the
        // user opts back in, not just the first time.
        if (($validated['notify_marketing'] ?? null) === true) {
            $user->marketing_consent_at = now();
        }
        $user->save();

        // Consent changes must take effect for the currently registered token
        // immediately. Otherwise an opted-out device remains subscribed to the
        // marketing topic until its next login/FCM registration.
        if (array_key_exists('notify_marketing', $validated)) {
            $this->pushNotifications->syncUserTopicSubscription($user->fresh());
        }

        return sendResponse(
            $this->payload($user->fresh(), $this->isV2($request)),
            __('api.settings.updated')
        );
    }

    private function payload($user, bool $includeMarketing): array
    {
        $notifications = [
            'notify_chat' => (bool) $user->notify_chat,
            'notify_ads' => (bool) $user->notify_ads,
            'notify_system' => true,
        ];

        if ($includeMarketing) {
            $notifications['notify_marketing'] = (bool) $user->notify_marketing;
            $notifications['marketing_consent_at'] = $user->marketing_consent_at?->toIso8601String();
        }

        return [
            'privacy' => [
                'show_last_name' => (bool) $user->show_last_name,
                'show_approximate_location' => (bool) $user->show_approximate_location,
                'trusted_users_only' => (bool) $user->trusted_users_only,
            ],
            'notifications' => $notifications,
        ];
    }

    private function isV2(Request $request): bool
    {
        return $request->is('api/v2/*');
    }
}
