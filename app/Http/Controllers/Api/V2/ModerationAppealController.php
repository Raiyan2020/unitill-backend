<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ModerationAppeal;
use App\Models\User;
use App\Models\UserModerationAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ModerationAppealController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'moderation_action_id' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:3000'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return sendError(__('api.auth.wrong_password'), [], 401);
        }

        $action = UserModerationAction::query()
            ->whereKey($validated['moderation_action_id'])
            ->where('user_id', $user->id)
            ->first();
        if (! $action) {
            return sendError('Moderation decision not found', [], 404);
        }

        $appeal = ModerationAppeal::firstOrCreate(
            ['user_id' => $user->id, 'moderation_action_id' => $action->id],
            ['message' => $validated['message'], 'status' => 'pending'],
        );

        if (! $appeal->wasRecentlyCreated) {
            return sendError('An appeal already exists for this decision', [], 409);
        }

        return sendResponse(['id' => $appeal->id, 'status' => $appeal->status], 'Appeal submitted');
    }
}
