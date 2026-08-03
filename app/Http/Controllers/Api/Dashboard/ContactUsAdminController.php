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

        $query = ContactUsMessage::query()
            ->with(['user', 'contactReason.translations'])
            ->latest('id');

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

        $rows = $query->paginate($perPage)->through(function (ContactUsMessage $row) {
            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'user_name' => $row->user?->name ?? '-',
                'user_email' => $row->user?->email ?? '-',
                'reason' => $row->contactReason?->nameForLanguageCode('en')
                    ?: $row->contactReason?->nameForLanguageCode('ar')
                    ?: '-',
                'message' => $row->message,
                'mail_sent' => $row->mail_sent_at !== null,
                'mail_sent_at' => $row->mail_sent_at?->toDateTimeString(),
                'mail_error' => $row->mail_error,
                'created_at' => $row->created_at?->toDateTimeString(),
            ];
        });

        return sendResponse($rows, 'Contact us messages fetched');
    }
}
