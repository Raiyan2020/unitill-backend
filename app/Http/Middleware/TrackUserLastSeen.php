<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Stamps users.last_seen_at on authenticated API traffic so is_online can be
 * derived from it (see ChatParticipantResource). Skipped when it was updated
 * within the last minute so an active user doesn't write on every request.
 */
class TrackUserLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinute()))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
