<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ChatReport;
use App\Models\Message;
use App\Support\ChatReportReason;
use App\Support\ReportPriority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ChatReportAdminController extends Controller
{
    private const STATUSES = ['pending', 'reviewed', 'dismissed'];

    /**
     * How many messages of conversation context to show when opening a report.
     */
    private const CONTEXT_MESSAGE_LIMIT = 30;

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', '');
        $reason = (string) $request->input('reason', '');
        $type = (string) $request->input('type', '');
        $priority = (string) $request->input('priority', '');

        $query = ChatReport::query()
            ->with([
                'reporter:id,name,first_name,last_name,email',
                'reportedUser:id,name,first_name,last_name,email',
                'conversation:id,ad_id',
                'conversation.ad:id,public_id,title',
            ])
            // Pending first — those are the ones waiting on a decision
            ->orderByRaw("FIELD(status, 'pending', 'reviewed', 'dismissed')")
            ->orderByRaw("FIELD(priority, 'critical', 'urgent', 'normal')")
            ->orderByDesc('id');

        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        if (in_array($type, ['user', 'chat'], true)) {
            $query->where('type', $type);
        }

        if ($reason !== '') {
            $query->where('reason', $reason);
        }

        if (in_array($priority, ReportPriority::allowed(), true)) {
            $query->where('priority', $priority);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('reporter', fn ($u) => $this->searchUser($u, $search))
                    ->orWhereHas('reportedUser', fn ($u) => $this->searchUser($u, $search));
            });
        }

        $rows = $query->paginate($perPage);
        $lang = (string) $request->header('lang', 'en');
        $rows->getCollection()->transform(fn (ChatReport $report) => $this->present($report, $lang));

        return sendResponse([
            'reports' => $rows,
            'counts' => $this->statusCounts($type),
            'reasons' => ChatReportReason::options($request->header('lang', 'en')),
        ], 'Chat reports fetched');
    }

    public function show(Request $request, int $id)
    {
        $report = ChatReport::with([
            'reporter:id,name,first_name,last_name,email',
            'reportedUser:id,name,first_name,last_name,email',
            'conversation:id,ad_id',
            'conversation.ad:id,public_id,title',
        ])->find($id);

        if (! $report) {
            return sendError('Report not found', [], 404);
        }

        return sendResponse(
            $this->present($report, (string) $request->header('lang', 'en')) + ['messages' => $this->conversationContext($report)],
            'Report details'
        );
    }

    /**
     * Changes the report status only. Blocking or deleting a user stays in user admin.
     */
    public function update(Request $request, int $id)
    {
        $report = ChatReport::find($id);

        if (! $report) {
            return sendError('Report not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(self::STATUSES)],
            'decision_reason' => [Rule::requiredIf(fn () => $request->input('status') !== 'pending'), 'nullable', 'string', 'max:3000'],
        ]);

        if ($validator->fails()) {
            return sendError($validator->errors()->first(), $validator->errors()->toArray(), 422);
        }

        $isResolved = $request->input('status') !== 'pending';
        $report->update([
            'status' => $request->input('status'),
            'decision_reason' => $isResolved ? $validator->validated()['decision_reason'] : null,
            'resolved_by' => $isResolved ? $request->user()?->id : null,
            'resolved_at' => $isResolved ? now() : null,
        ]);

        return sendResponse(
            $this->present($report->fresh(['reporter', 'reportedUser', 'conversation.ad']), (string) $request->header('lang', 'en')),
            'Report status updated'
        );
    }

    /**
     * Recent messages from the reported conversation, so a moderator judges the
     * context rather than relying on the reporter's description alone.
     */
    private function conversationContext(ChatReport $report): array
    {
        if (! $report->conversation_id) {
            return [];
        }

        return Message::query()
            ->where('conversation_id', $report->conversation_id)
            ->with('sender:id,name,first_name,last_name')
            ->orderByDesc('id')
            ->limit(self::CONTEXT_MESSAGE_LIMIT)
            ->get()
            ->reverse()
            ->map(fn (Message $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender ? $this->displayName($message->sender) : '-',
                'is_reported_user' => (int) $message->sender_id === (int) $report->reported_user_id,
                'created_at' => optional($message->created_at)->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    private function searchUser($query, string $search): void
    {
        $query->where('first_name', 'like', "%{$search}%")
            ->orWhere('last_name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%");
    }

    private function statusCounts(string $type = ''): array
    {
        $query = ChatReport::query();
        if (in_array($type, ['user', 'chat'], true)) {
            $query->where('type', $type);
        }

        $counts = $query
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending' => (int) ($counts['pending'] ?? 0),
            'reviewed' => (int) ($counts['reviewed'] ?? 0),
            'dismissed' => (int) ($counts['dismissed'] ?? 0),
            'total' => (int) $counts->sum(),
        ];
    }

    private function present(ChatReport $report, string $lang = 'en'): array
    {
        return [
            'id' => $report->id,
            'type' => $report->type,
            'reason' => $report->reason,
            'reason_label' => ChatReportReason::label($report->reason, $lang),
            'description' => $report->description,
            'status' => $report->status,
            'priority' => $report->priority,
            'decision_reason' => $report->decision_reason,
            'resolved_by' => $report->resolved_by,
            'resolved_at' => optional($report->resolved_at)->toDateTimeString(),
            'created_at' => optional($report->created_at)->toDateTimeString(),
            'conversation_id' => $report->conversation_id,
            'ad' => $report->conversation?->ad ? [
                'id' => $report->conversation->ad->id,
                'public_id' => $report->conversation->ad->public_id,
                'title' => $report->conversation->ad->title,
            ] : null,
            'reporter' => $report->reporter ? [
                'id' => $report->reporter->id,
                'name' => $this->displayName($report->reporter),
                'email' => $report->reporter->email,
            ] : null,
            'reported_user' => $report->reportedUser ? [
                'id' => $report->reportedUser->id,
                'name' => $this->displayName($report->reportedUser),
                'email' => $report->reportedUser->email,
            ] : null,
        ];
    }

    private function displayName($user): string
    {
        $name = trim(implode(' ', array_filter([$user->first_name, $user->last_name])));

        return $name !== '' ? $name : (string) ($user->name ?? $user->email ?? '-');
    }
}
