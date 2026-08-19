<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FcmController extends Controller
{
    public function __construct(protected PushNotificationService $pushNotifications) {}

    public function register(Request $request)
    {
        $validated = $request->validate([
            'device_token' => 'required|string|min:20',
            'device_id' => 'nullable|string|max:191',
            'device_identifier' => 'nullable|string|max:191',
        ]);

        $lang = $request->header('lang') === 'ar';
        $user = Auth::user();
        $deviceToken = $validated['device_token'];

        $user->update([
            'device_token' => $deviceToken,
            'device_token_updated_at' => now(),
        ]);
        $this->pushNotifications->syncUserTopicSubscription($user->fresh(), $deviceToken);

        return sendResponse([
            'device_token' => $deviceToken,
            'all_users_topic' => $this->pushNotifications->allUsersTopic(),
            'subscribed_to_all_topic' => (bool) $user->notify_system,
        ], __('api.session.fcm_registered'));
    }
}
