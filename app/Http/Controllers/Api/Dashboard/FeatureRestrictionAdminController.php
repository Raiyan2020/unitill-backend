<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFeatureRestriction;
use App\Models\UserNotification;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeatureRestrictionAdminController extends Controller
{
    public function index(int $userId)
    {
        if (! User::whereKey($userId)->exists()) {
            return sendError('User not found', [], 404);
        }

        return sendResponse(UserFeatureRestriction::with(['admin:id,name,email', 'liftedBy:id,name,email'])
            ->where('user_id', $userId)->latest('id')->get());
    }

    public function store(Request $request, int $userId, PushNotificationService $push)
    {
        $user = User::find($userId);
        if (! $user) {
            return sendError('User not found', [], 404);
        }

        $data = $request->validate([
            'feature' => ['required', Rule::in(UserFeatureRestriction::FEATURES)],
            'reason' => ['required', 'string', 'max:3000'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        if ($user->featureRestrictions()->active()->where('feature', $data['feature'])->exists()) {
            return sendError('This feature is already restricted', [], 409);
        }

        $restriction = $user->featureRestrictions()->create([
            'admin_id' => $request->user()?->id,
            'feature' => $data['feature'],
            'reason' => $data['reason'],
            'starts_at' => now(),
            'ends_at' => isset($data['duration_days']) ? now()->addDays($data['duration_days']) : null,
        ]);

        $push->notifyUser($user, 'Feature restricted', $data['reason'], [
            'type' => 'feature_restriction', 'feature' => $data['feature'],
            'restriction_id' => (string) $restriction->id,
        ], UserNotification::TYPE_SYSTEM, 'notify_system');

        return sendResponse($restriction->load('admin:id,name,email'), 'Feature restriction applied');
    }

    public function destroy(Request $request, int $userId, int $restrictionId)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:3000']]);
        $restriction = UserFeatureRestriction::where('user_id', $userId)->find($restrictionId);
        if (! $restriction) {
            return sendError('Feature restriction not found', [], 404);
        }
        if ($restriction->lifted_at) {
            return sendError('Feature restriction already lifted', [], 409);
        }

        $restriction->update([
            'lifted_at' => now(),
            'lifted_by' => $request->user()?->id,
            'lift_reason' => $data['reason'] ?? null,
        ]);

        return sendResponse($restriction->fresh('liftedBy:id,name,email'), 'Feature restriction lifted');
    }
}
