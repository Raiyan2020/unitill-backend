<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AdminPushNotificationController extends Controller
{
    public function __construct(protected PushNotificationService $pushNotifications) {}

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));

        $query = PushNotification::query()
            ->with(['admin:id,name,email', 'user:id,name,email'])
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $rows = $query->paginate($perPage)->through(function (PushNotification $row) {
            return [
                'id' => $row->id,
                'audience' => $row->audience,
                'topic' => $row->topic,
                'title' => $row->title,
                'body' => $row->body,
                'status' => $row->status,
                'recipients_count' => $row->recipients_count,
                'error_message' => $row->error_message,
                'admin_name' => $row->admin?->name ?? '-',
                'user_id' => $row->user_id,
                'user_name' => $row->user?->name,
                'user_email' => $row->user?->email,
                'created_at' => $row->created_at?->toDateTimeString(),
            ];
        });

        return sendResponse($rows, 'Push notifications fetched');
    }

    public function meta()
    {
        return sendResponse([
            'all_users_topic' => $this->pushNotifications->allUsersTopic(),
            'estimated_all_audience' => $this->pushNotifications->estimatedAllAudienceCount(),
            'marketing_topic' => $this->pushNotifications->marketingTopic(),
            'estimated_marketing_audience' => $this->pushNotifications->estimatedMarketingAudienceCount(),
            'firebase_configured' => app(\App\Services\FirebaseService::class)->isConfigured(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'audience' => ['required', Rule::in(['all', 'user', 'marketing'])],
            'user_id' => 'required_if:audience,user|nullable|integer|exists:users,id',
            'title' => 'required|string|max:191',
            'body' => 'required|string|max:2000',
            'link' => 'nullable|string|max:500',
        ]);

        $adminId = Auth::guard('sanctum')->user()?->id;
        $data = array_filter([
            'type' => 'admin_push',
            'link' => $validated['link'] ?? null,
        ]);

        if ($validated['audience'] === 'all') {
            $log = $this->pushNotifications->sendToAll(
                $validated['title'],
                $validated['body'],
                $data,
                $adminId
            );
        } elseif ($validated['audience'] === 'marketing') {
            // Marketing broadcasts need their own consent — never sent under
            // the "all" audience's notify_system gate.
            $log = $this->pushNotifications->sendMarketingToAll(
                $validated['title'],
                $validated['body'],
                $data,
                $adminId
            );
        } else {
            $user = User::findOrFail($validated['user_id']);
            $log = $this->pushNotifications->sendToUser(
                $user,
                $validated['title'],
                $validated['body'],
                $data,
                $adminId
            );
        }

        if ($log->status !== PushNotification::STATUS_SENT) {
            return sendError(
                $log->error_message ?: 'Failed to send push notification',
                ['notification' => $log],
                422
            );
        }

        return sendResponse([
            'id' => $log->id,
            'audience' => $log->audience,
            'topic' => $log->topic,
            'status' => $log->status,
            'recipients_count' => $log->recipients_count,
            'fcm_message_id' => $log->fcm_message_id,
        ], 'Push notification sent');
    }
}
