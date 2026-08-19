<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ModerationAppeal;
use App\Models\User;
use App\Models\UserModerationAction;
use App\Services\UserModerationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModerationAdminController extends Controller
{
    public function __construct(protected UserModerationService $moderation) {}

    public function storeAction(Request $request, int $userId)
    {
        $user = User::find($userId);
        if (! $user) return sendError('User not found', [], 404);

        $data = $request->validate([
            'action' => ['required', Rule::in(['warning', 'temporary_suspension', 'permanent_suspension', 'reactivated'])],
            'reason' => ['required', 'string', 'max:3000'],
            'duration_days' => ['required_if:action,temporary_suspension', 'nullable', 'integer', 'min:1', 'max:365'],
            'source_type' => ['nullable', Rule::in(['chat_report', 'ad_report'])],
            'source_id' => ['nullable', 'integer'],
        ]);

        $action = $this->moderation->apply(
            $user,
            $data['action'],
            $data['reason'],
            $request->user()?->id,
            $data['duration_days'] ?? null,
            $data['source_type'] ?? null,
            $data['source_id'] ?? null,
        );

        return sendResponse($action->load('admin:id,name,email'), 'Moderation action recorded');
    }

    public function userActions(Request $request, int $userId)
    {
        $rows = UserModerationAction::with('admin:id,name,email')
            ->where('user_id', $userId)
            ->latest('id')
            ->paginate(max(1, min((int) $request->input('per_page', 20), 100)));

        return sendResponse($rows, 'Moderation history fetched');
    }

    public function appeals(Request $request)
    {
        $query = ModerationAppeal::with(['user:id,name,email', 'action', 'resolver:id,name,email'])
            ->latest('id');
        if ($request->filled('status')) $query->where('status', $request->input('status'));

        return sendResponse($query->paginate(max(1, min((int) $request->input('per_page', 20), 100))), 'Appeals fetched');
    }

    public function decideAppeal(Request $request, int $id)
    {
        $appeal = ModerationAppeal::with(['user', 'action'])->find($id);
        if (! $appeal) return sendError('Appeal not found', [], 404);
        if ($appeal->status !== 'pending') return sendError('Appeal already decided', [], 409);

        $data = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'rejected'])],
            'decision_reason' => ['required', 'string', 'max:3000'],
        ]);

        $appeal->update([
            'status' => $data['status'],
            'decision_reason' => $data['decision_reason'],
            'resolved_by' => $request->user()?->id,
            'resolved_at' => now(),
        ]);

        if ($data['status'] === 'accepted' && in_array($appeal->action->action, ['temporary_suspension', 'permanent_suspension'], true)) {
            $this->moderation->apply($appeal->user, 'reactivated', $data['decision_reason'], $request->user()?->id);
        }

        return sendResponse($appeal->fresh(['user', 'action', 'resolver']), 'Appeal decision recorded');
    }
}
