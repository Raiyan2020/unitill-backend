<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Ad;
use App\Models\ChatReport;
use App\Models\Conversation;
use App\Services\ChatService;
use App\Support\ChatReportReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ConversationController extends Controller
{
    public function __construct(protected ChatService $chatService) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'tab' => ['nullable', Rule::in(['active', 'archived'])],
            'per_page' => 'nullable|integer|min:1|max:50',
            'search' => 'nullable|string|max:255',
            'q' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $tab = $validated['tab'] ?? 'active';
        $perPage = (int) ($validated['per_page'] ?? 20);
        $lang = $request->header('lang') === 'ar';
        $search = trim((string) ($validated['search'] ?? $validated['q'] ?? ''));

        $query = Conversation::query()
            ->visibleToUser($user->id)
            ->where('status', $tab === 'archived' ? 'archived' : 'active')
            ->with([
                'ad:id,public_id,title,cover_image,price,currency,status',
                'buyer:id,first_name,last_name,name,image',
                'seller:id,first_name,last_name,name,image',
            ]);

        if ($search !== '') {
            // Metadata only: the ad's title and the other participant's name.
            // Message bodies are deliberately not searched — the messages table
            // grows without bound and a LIKE scan over it has no index to use.
            $matchesName = function ($builder) use ($search, $user) {
                $builder->whereKeyNot($user->id)->where(function ($name) use ($search) {
                    $name->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            };

            $query->where(function ($outer) use ($search, $matchesName) {
                $outer->whereHas('ad', fn ($ad) => $ad->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('buyer', $matchesName)
                    ->orWhereHas('seller', $matchesName);
            });
        }

        $conversations = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        $response = ConversationResource::collection($conversations)
            ->additional([
                'current_tab' => $tab,
                'search' => $search !== '' ? $search : null,
            ])
            ->response()
            ->getData(true);

        return sendResponse($response);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ad_id' => 'required',
        ]);

        $lang = $request->header('lang') === 'ar';
        $user = Auth::user();

        $ad = Ad::query()
            ->published()
            ->where(function ($query) use ($validated) {
                $query->where('id', $validated['ad_id'])
                    ->orWhere('public_id', $validated['ad_id']);
            })
            ->first();

        if (! $ad) {
            return sendError(__('api.chat.ad_not_available'), [], 404);
        }

        if ($ad->user_id === $user->id) {
            return sendError(
                __('api.chat.cannot_message_own_ad'),
                [],
                422
            );
        }

        try {
            $conversation = $this->chatService->findOrCreateConversation($ad, $user);
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'seller_unavailable') {
                return sendError(
                    __('api.chat.seller_unavailable'),
                    [],
                    422
                );
            }

            if ($e->getMessage() === 'buyer_not_verified') {
                return sendError(
                    __('api.chat.buyer_not_verified'),
                    ['needs_verification' => true],
                    403
                );
            }

            return sendError(
                __('api.chat.cannot_start'),
                [],
                422
            );
        }

        return sendResponse(
            new ConversationResource($conversation),
            __('api.chat.started')
        );
    }

    public function show(Request $request, string $id)
    {
        $conversation = $this->findParticipantConversation($id);

        if (! $conversation) {
            return sendError(
                __('api.chat.not_found'),
                [],
                404
            );
        }

        $conversation->load([
            'ad:id,public_id,title,cover_image,price,currency,status,user_id',
            'buyer:id,first_name,last_name,name,image',
            'seller:id,first_name,last_name,name,image',
        ]);

        return sendResponse(new ConversationResource($conversation));
    }

    public function messages(Request $request, string $id)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'before_id' => 'nullable|integer|min:1',
        ]);

        $conversation = $this->findParticipantConversation($id);

        if (! $conversation) {
            return sendError(
                __('api.chat.not_found'),
                [],
                404
            );
        }

        $perPage = (int) ($validated['per_page'] ?? 30);

        $query = $conversation->messages()
            ->with('sender:id,first_name,last_name,name,image')
            ->orderByDesc('id');

        if (! empty($validated['before_id'])) {
            $query->where('id', '<', $validated['before_id']);
        }

        $messages = $query->paginate($perPage);

        $this->chatService->markAsRead($conversation, Auth::user());

        $response = MessageResource::collection($messages)->response()->getData(true);

        return sendResponse($response);
    }

    public function sendMessage(Request $request, string $id)
    {
        $validated = $request->validate([
            'body' => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $lang = $request->header('lang') === 'ar';
        $conversation = $this->findParticipantConversation($id);

        if (! $conversation) {
            return sendError(__('api.chat.not_found'), [], 404);
        }

        if (empty(trim($validated['body'] ?? '')) && !$request->hasFile('attachment')) {
            return sendError(__('api.chat.message_empty'), [], 422);
        }

        $attachmentPath = null;
        $attachmentType = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('chat_attachments', 'public');
            $attachmentType = str_starts_with($file->getMimeType(), 'image/') ? 'image' : 'file';
        }

        try {
            $message = $this->chatService->sendMessage(
                $conversation,
                Auth::user(),
                $validated['body'] ?? '',
                $attachmentPath,
                $attachmentType
            );
        } catch (\InvalidArgumentException $e) {
            return match ($e->getMessage()) {
                'participant_unavailable' => sendError(
                    __('api.chat.user_unavailable'),
                    ['can_send_messages' => false],
                    422
                ),
                'messaging_disabled' => sendError(
                    __('api.chat.messaging_disabled_sold'),
                    ['can_send_messages' => false],
                    422
                ),
                'empty_message' => sendError(
                    __('api.chat.message_empty'),
                    [],
                    422
                ),
                default => sendError(
                    __('api.chat.cannot_send'),
                    [],
                    422
                ),
            };
        }

        return sendResponse(
            new MessageResource($message),
            __('api.chat.message_sent')
        );
    }

    public function markRead(Request $request, string $id)
    {
        $conversation = $this->findParticipantConversation($id);

        if (! $conversation) {
            return sendError(
                __('api.chat.not_found'),
                [],
                404
            );
        }

        $updated = $this->chatService->markAsRead($conversation, Auth::user());

        return sendResponse(['marked_read' => $updated]);
    }

    public function archive(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $conversation = $this->findParticipantConversation($id);

        if (! $conversation) {
            return sendError(__('api.chat.not_found'), [], 404);
        }

        if ($conversation->status === 'archived') {
            return sendResponse(
                new ConversationResource($conversation),
                __('api.chat.already_archived')
            );
        }

        $this->chatService->archiveConversation($conversation, 'manual');

        return sendResponse(
            new ConversationResource($conversation->fresh(['ad', 'buyer', 'seller'])),
            __('api.chat.archived')
        );
    }

    public function unarchive(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $conversation = $this->findParticipantConversation($id);

        if (! $conversation) {
            return sendError(__('api.chat.not_found'), [], 404);
        }

        $result = $this->chatService->unarchiveConversation($conversation);

        if (isset($result['error'])) {
            $messages = [
                'not_archived' => __('api.chat.not_archived'),
                'sold_listing' => __('api.chat.cannot_unarchive_sold'),
                'ad_not_available' => __('api.chat.cannot_unarchive_ad_unavailable'),
            ];

            return sendError(
                $messages[$result['error']] ?? (__('api.chat.cannot_unarchive')),
                ['error_code' => $result['error']],
                422
            );
        }

        return sendResponse(
            new ConversationResource($result['conversation']),
            __('api.chat.unarchived')
        );
    }

    public function destroy(Request $request, string $id)
    {
        $lang = $request->header('lang') === 'ar';
        $conversation = $this->findParticipantConversation($id, includeDeleted: true);

        if (! $conversation) {
            return sendError(__('api.chat.not_found'), [], 404);
        }

        $userId = Auth::id();

        if ($conversation->buyer_id === $userId) {
            $conversation->update(['buyer_deleted_at' => now()]);
        } elseif ($conversation->seller_id === $userId) {
            $conversation->update(['seller_deleted_at' => now()]);
        }

        return sendResponse(
            ['conversation_id' => $conversation->id],
            __('api.chat.removed')
        );
    }

    /**
     * Chat report reasons. Public so the app can load them before sign-in.
     */
    public function reportReasons(Request $request)
    {
        return sendResponse(
            ChatReportReason::options($request->header('lang', 'en'))
        );
    }

    public function report(Request $request, string $id)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['user', 'chat'])],
            // Reason comes from a fixed list; the explanation is optional except for "other"
            'reason' => ['required', Rule::in(ChatReportReason::allowed())],
            'description' => [
                Rule::requiredIf(fn () => $request->input('reason') === ChatReportReason::OTHER),
                'nullable',
                'string',
                'max:2000',
            ],
            'reported_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $lang = $request->header('lang') === 'ar';
        $conversation = $this->findParticipantConversation($id);

        if (! $conversation) {
            return sendError(__('api.chat.not_found'), [], 404);
        }

        $reporter = Auth::user();
        $reportedUserId = $validated['reported_user_id']
            ?? $conversation->otherParticipantId($reporter->id);

        if (! $reportedUserId || ! $conversation->isParticipant($reportedUserId)) {
            return sendError(
                __('api.chat.invalid_reported_user'),
                [],
                422
            );
        }

        if ($reportedUserId === $reporter->id) {
            return sendError(
                __('api.chat.cannot_report_self'),
                [],
                422
            );
        }

        // Stop the same pending report being filed repeatedly, as ad reports already do
        $duplicate = ChatReport::query()
            ->where('reporter_id', $reporter->id)
            ->where('conversation_id', $conversation->id)
            ->where('type', $validated['type'])
            ->where('status', 'pending')
            ->exists();

        if ($duplicate) {
            return sendError(
                __('api.chat.report_pending_exists'),
                [],
                422
            );
        }

        $report = ChatReport::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUserId,
            'conversation_id' => $conversation->id,
            'type' => $validated['type'],
            'reason' => $validated['reason'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return sendResponse(
            [
                'id' => $report->id,
                'status' => $report->status,
            ],
            __('api.chat.report_submitted')
        );
    }

    protected function findParticipantConversation(string $id, bool $includeDeleted = false): ?Conversation
    {
        $query = Conversation::query()->where('id', $id);

        if (! $includeDeleted) {
            $query->visibleToUser(Auth::id());
        } else {
            $userId = Auth::id();
            $query->where(function ($q) use ($userId) {
                $q->where('buyer_id', $userId)->orWhere('seller_id', $userId);
            });
        }

        $conversation = $query->first();

        if (! $conversation || ! $conversation->isParticipant(Auth::id())) {
            return null;
        }

        return $conversation;
    }
}
