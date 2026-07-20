<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Closing and reopening a UniTill account.
 *
 * Everything the user owns is soft-deleted with the SAME deleted_at timestamp
 * as the user row. That shared timestamp is what makes reactivation faithful:
 * on restore only the rows carrying it are brought back, so content the user
 * had already deleted themselves before closing the account stays deleted.
 * A blanket restore() would wrongly resurrect it.
 *
 * Reports, ratings, orders, contact messages and login logs are intentionally
 * left untouched — they are moderation records or belong to the other party.
 */
class AccountDeletionService
{
    public function delete(User $user): Carbon
    {
        $deletedAt = now();

        DB::transaction(function () use ($user, $deletedAt) {
            // Conversations are removed from THIS user's inbox only, using the
            // per-side columns the chat feature already uses. Soft-deleting the
            // row itself would take the thread away from the other participant,
            // who has not closed their account.
            // Only rows still null are stamped, so a chat the user had already
            // deleted keeps its original timestamp and is not restored later.
            Conversation::query()
                ->where('buyer_id', $user->id)
                ->whereNull('buyer_deleted_at')
                ->update(['buyer_deleted_at' => $deletedAt]);

            Conversation::query()
                ->where('seller_id', $user->id)
                ->whereNull('seller_deleted_at')
                ->update(['seller_deleted_at' => $deletedAt]);

            Ad::query()
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $deletedAt]);

            // Access is revoked outright rather than soft-deleted: a closed
            // account must not keep working sessions.
            $user->tokens()->delete();
            $user->refreshTokens()->delete();
            $user->biometricTokens()->delete();

            $user->deleted_at = $deletedAt;
            $user->save();
        });

        Log::info('Account soft deleted', ['user_id' => $user->id, 'deleted_at' => $deletedAt->toIso8601String()]);

        return $deletedAt;
    }

    /**
     * Reverses a deletion, restoring only what that deletion took.
     */
    public function restore(User $user): void
    {
        $deletedAt = $user->deleted_at;

        if ($deletedAt === null) {
            return;
        }

        DB::transaction(function () use ($user, $deletedAt) {
            Conversation::query()
                ->where('buyer_id', $user->id)
                ->where('buyer_deleted_at', $deletedAt)
                ->update(['buyer_deleted_at' => null]);

            Conversation::query()
                ->where('seller_id', $user->id)
                ->where('seller_deleted_at', $deletedAt)
                ->update(['seller_deleted_at' => null]);

            Ad::withTrashed()
                ->where('user_id', $user->id)
                ->where('deleted_at', $deletedAt)
                ->update(['deleted_at' => null]);

            $user->deleted_at = null;
            $user->save();
        });

        Log::info('Account restored', ['user_id' => $user->id]);
    }
}
