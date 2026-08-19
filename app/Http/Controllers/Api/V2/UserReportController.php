<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ChatReport;
use App\Models\User;
use App\Support\ChatReportReason;
use App\Support\ReportPriority;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserReportController extends Controller
{
    public function store(Request $request, int $userId)
    {
        $reporter = $request->user();
        $reportedUser = User::find($userId);

        if (! $reportedUser) {
            return sendError('User not found', [], 404);
        }
        if ((int) $reporter->id === $userId) {
            return sendError(__('api.chat.cannot_report_self'), [], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', Rule::in(ChatReportReason::allowed())],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $duplicate = ChatReport::query()
            ->where('reporter_id', $reporter->id)
            ->where('reported_user_id', $userId)
            ->whereNull('conversation_id')
            ->where('status', 'pending')
            ->exists();

        if ($duplicate) {
            return sendError(__('api.chat.report_pending_exists'), [], 409);
        }

        $report = ChatReport::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUser->id,
            'conversation_id' => null,
            'type' => 'user',
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
            'priority' => ReportPriority::fromReason($validated['reason']),
        ]);

        return sendResponse(['id' => $report->id, 'status' => $report->status], __('api.chat.report_submitted'));
    }
}
