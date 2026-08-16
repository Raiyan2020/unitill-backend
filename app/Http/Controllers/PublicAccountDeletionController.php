<?php

namespace App\Http\Controllers;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Http\Request;

class PublicAccountDeletionController extends Controller
{
    public function create(Request $request)
    {
        $lang = $this->locale($request);
        app()->setLocale($lang);

        return view('public.delete-account', compact('lang'));
    }

    public function store(Request $request)
    {
        $lang = $this->locale($request);
        app()->setLocale($lang);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'confirm' => ['required', 'accepted'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $user = User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user && ! AccountDeletionRequest::where('user_id', $user->id)->where('status', 'pending')->exists()) {
            AccountDeletionRequest::create([
                'user_id' => $user->id,
                'email' => $email,
                'reason' => $data['reason'] ?? null,
                'status' => 'pending',
                'requested_at' => now(),
                'ip_address' => $request->ip(),
            ]);
        }

        // The same response is deliberately shown for known and unknown emails,
        // preventing this public form from becoming an account-discovery tool.
        return back()->with('deletion_request_received', true);
    }

    private function locale(Request $request): string
    {
        $requested = $request->query('lang', $request->input('lang'));
        if (in_array($requested, ['en', 'ar'], true)) {
            return $requested;
        }

        return str_starts_with(strtolower((string) $request->header('Accept-Language')), 'ar')
            ? 'ar'
            : 'en';
    }
}
