<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserNotificationResource;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserNotificationController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:50',
            'unread_only' => 'nullable|boolean',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $userId = Auth::id();

        $query = UserNotification::query()
            ->where('user_id', $userId)
            ->latest('id');

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $rows = $query->paginate($perPage);

        $response = UserNotificationResource::collection($rows)
            ->response()
            ->getData(true);

        $response['unread_count'] = UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return sendResponse($response);
    }

    public function unreadCount(Request $request)
    {
        $count = UserNotification::query()
            ->where('user_id', Auth::id())
            ->whereNull('read_at')
            ->count();

        return sendResponse(['unread_count' => $count]);
    }

    public function markRead(Request $request, int $id)
    {
        $lang = $request->header('lang') === 'ar';

        $notification = UserNotification::query()
            ->where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        if (! $notification) {
            return sendError(__('api.notification.not_found'), [], 404);
        }

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return sendResponse(
            new UserNotificationResource($notification->fresh()),
            __('api.notification.marked_read')
        );
    }

    public function markAllRead(Request $request)
    {
        $lang = $request->header('lang') === 'ar';

        $updated = UserNotification::query()
            ->where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return sendResponse(
            ['marked_read' => $updated],
            __('api.notification.all_marked_read')
        );
    }

    public function destroy(Request $request, int $id)
    {
        $lang = $request->header('lang') === 'ar';

        $deleted = UserNotification::query()
            ->where('user_id', Auth::id())
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            return sendError(__('api.notification.not_found'), [], 404);
        }

        return sendResponse(
            ['deleted' => true],
            __('api.notification.deleted')
        );
    }
}
