<?php

namespace App\Services\V2;

use App\Models\Ad;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * V2 account closure, split into the two options the policy asks for.
 *
 * Deliberately standalone: V1's AccountDeletionService is left exactly as it is,
 * because the published app depends on its behaviour. Nothing here calls into
 * it and nothing here changes it.
 *
 *   deactivate() — reversible. Same shape as V1's soft delete, so the existing
 *                  sign-in restore path revives the account with no change to
 *                  the auth controllers.
 *   purge()      — permanent. The account is erased and cannot come back.
 */
class AccountClosureService
{
    /**
     * Reversible closure.
     *
     * Everything the user owns is soft-deleted with the SAME deleted_at
     * timestamp as the user row. That shared timestamp is what makes
     * reactivation faithful: only rows carrying it are brought back, so content
     * the user had already deleted themselves stays deleted.
     */
    public function deactivate(User $user): Carbon
    {
        $deactivatedAt = now();

        DB::transaction(function () use ($user, $deactivatedAt) {
            // Conversations leave THIS user's inbox only. Soft-deleting the row
            // would take the thread away from the other participant, who has not
            // closed their account.
            Conversation::query()
                ->where('buyer_id', $user->id)
                ->whereNull('buyer_deleted_at')
                ->update(['buyer_deleted_at' => $deactivatedAt]);

            Conversation::query()
                ->where('seller_id', $user->id)
                ->whereNull('seller_deleted_at')
                ->update(['seller_deleted_at' => $deactivatedAt]);

            Ad::query()
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $deactivatedAt]);

            $this->revokeAccess($user);

            $user->deleted_at = $deactivatedAt;
            $user->save();
        });

        Log::info('Account deactivated (v2)', [
            'user_id' => $user->id,
            'deactivated_at' => $deactivatedAt->toIso8601String(),
        ]);

        return $deactivatedAt;
    }

    /**
     * Permanent deletion. Irreversible: no row survives to sign in with, and the
     * freed email address can be used to register again from scratch.
     *
     * DESTROYED — the users row, and everything the database cascades from it:
     * ads (with images, attribute values and favourites), devices, all tokens,
     * notifications, support messages, trusted-seller applications, and the
     * ratings the account received.
     *
     * KEPT, detached from the person by the SET NULL rules installed in
     * 2026_08_11_000000_detach_retained_records_from_users:
     *   - orders and coupon redemptions — financial records (tax, accounting)
     *   - ad reports and chat reports — safety and moderation records
     *   - ratings the user LEFT — another member's public score
     *   - login logs — security and fraud history
     *   - conversations and messages — the other participant's own record
     *
     * Uploaded files are removed explicitly: a database cascade deletes rows,
     * not the photographs sitting in public storage.
     */
    public function purge(User $user): Carbon
    {
        $purgedAt = now();
        $userId = $user->id;

        DB::transaction(function () use ($user) {
            $this->deleteUploadedFiles($user);
            $this->revokeAccess($user);

            $user->forceDelete();
        });

        Log::info('Account permanently deleted (v2)', [
            'user_id' => $userId,
            'purged_at' => $purgedAt->toIso8601String(),
        ]);

        return $purgedAt;
    }

    /**
     * The avatar, plus every ad photo including covers, removed from disk before
     * the rows pointing at them disappear.
     */
    protected function deleteUploadedFiles(User $user): void
    {
        $this->deleteFile($user->image);

        $ads = Ad::withTrashed()->where('user_id', $user->id)->get(['id', 'cover_image']);

        foreach ($ads as $ad) {
            $this->deleteFile($ad->cover_image);

            foreach ($ad->images()->pluck('path') as $path) {
                $this->deleteFile($path);
            }
        }
    }

    protected function deleteFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        // Stored values carry a leading /storage/ prefix (see helpers.php),
        // which is the public URL rather than the path on the disk.
        $relative = ltrim(Str::after($path, '/storage/'), '/');

        if ($relative !== '' && Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }

    /**
     * A closed account must not keep working sessions, so access is revoked
     * outright in both flows rather than soft-deleted.
     */
    protected function revokeAccess(User $user): void
    {
        $user->tokens()->delete();
        $user->refreshTokens()->delete();
        $user->biometricTokens()->delete();
    }
}
