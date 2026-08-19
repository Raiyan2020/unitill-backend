<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Services\V2\AccountClosureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountDeletionRequestAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = AccountDeletionRequest::with(['user:id,name,email', 'resolver:id,name,email'])->latest('requested_at');
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return sendResponse($query->paginate(max(1, min((int) $request->input('per_page', 20), 100))));
    }

    public function resolve(Request $request, int $id, AccountClosureService $accounts)
    {
        $deletion = AccountDeletionRequest::with('user')->find($id);
        if (! $deletion) {
            return sendError('Deletion request not found', [], 404);
        }
        if ($deletion->status !== 'pending') {
            return sendError('Deletion request already resolved', [], 409);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['completed', 'rejected'])],
            'resolution_note' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($deletion, $data, $request, $accounts) {
            $deletion->forceFill([
                'status' => $data['status'],
                'resolved_at' => now(),
                'resolved_by' => $request->user()?->id,
                'resolution_note' => $data['resolution_note'] ?? null,
            ])->save();

            if ($data['status'] === 'completed' && $deletion->user) {
                $accounts->purge($deletion->user);
            }
        });

        return sendResponse($deletion->fresh('resolver:id,name,email'), 'Deletion request resolved');
    }
}
