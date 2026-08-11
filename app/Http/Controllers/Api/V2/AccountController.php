<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\AccountClosureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * V2 account closure: the two options as separate actions.
 *
 * V1 exposes a single "delete" that is really a reversible deactivation, and the
 * published app tells users exactly that. Rather than change what that endpoint
 * means — which would permanently erase accounts for anyone on an old build —
 * the new behaviour lives here, on its own routes, its own service and its own
 * translation file. No existing V1 or V2 code is touched.
 *
 *   POST   /v2/deactivate-account   reversible, undone by signing in
 *   DELETE /v2/delete-account       permanent, irreversible
 */
class AccountController extends Controller
{
    public function __construct(private readonly AccountClosureService $accounts)
    {
    }

    /**
     * Reversible closure. Signing in again restores the account, its ads and its
     * chats — handled by the existing login flow, which needs no change because
     * this leaves the account in the same shape V1's soft delete does.
     */
    public function deactivate(Request $request)
    {
        $deactivatedAt = $this->accounts->deactivate(Auth::user());

        return sendResponse([
            'deactivated_at' => $deactivatedAt->toIso8601String(),
            'restorable' => true,
        ], __('account_v2.deactivated'));
    }

    /**
     * Permanent deletion. The account and its data are erased; only records kept
     * for legal or security reasons survive, detached from the person. The user
     * cannot sign back in and nothing is recoverable.
     */
    public function destroy(Request $request)
    {
        $purgedAt = $this->accounts->purge(Auth::user());

        return sendResponse([
            'deleted_at' => $purgedAt->toIso8601String(),
            'restorable' => false,
        ], __('account_v2.deleted_permanently'));
    }
}
