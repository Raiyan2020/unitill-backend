<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContactUsMessage;
use Illuminate\Http\Request;

class ContactUsAdminController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 10), 100));
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');

        $query = ContactUsMessage::query()
            ->with(['user', 'contactReason.translations', 'closedBy'])
            ->latest('id');

        if (in_array($status, ['open', 'closed'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('contactReason.translations', function ($rq) use ($search) {
                        $rq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $lang = (string) $request->header('lang', 'en');

        $rows = $query->paginate($perPage)->through(function (ContactUsMessage $row) use ($lang) {
            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'user_name' => $row->user?->name ?? '-',
                'user_email' => $row->user?->email ?? '-',
                // Follows the dashboard language; nameForLanguageCode already
                // falls back to English when a translation is missing.
                'reason' => $row->contactReason?->nameForLanguageCode($lang) ?: '-',
                'message' => $row->message,
                'mail_sent' => $row->mail_sent_at !== null,
                'mail_sent_at' => $row->mail_sent_at?->toDateTimeString(),
                'mail_error' => $row->mail_error,
                'status' => $row->status,
                'closed_at' => $row->closed_at?->toDateTimeString(),
                'closed_by' => $row->closedBy ? [
                    'id' => $row->closedBy->id,
                    'name' => $row->closedBy->name,
                ] : null,
                'created_at' => $row->created_at?->toDateTimeString(),
            ];
        });

        return sendResponse($rows, 'Contact us messages fetched');
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:open,closed'],
        ]);

        $message = ContactUsMessage::findOrFail($id);
        $isClosing = $validated['status'] === 'closed';
        $message->forceFill([
            'status' => $validated['status'],
            'closed_at' => $isClosing ? ($message->closed_at ?? now()) : null,
            'closed_by' => $isClosing ? $request->user()?->id : null,
        ])->save();

        return sendResponse($message->fresh(['closedBy']), 'Contact us message updated');
    }
}
