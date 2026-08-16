<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureIsAvailable
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $restriction = $request->user()?->activeFeatureRestriction($feature);

        if ($restriction) {
            // Published clients keep the legacy error envelope. V2 clients get
            // structured restriction details so they can disable one feature.
            if (! $request->is('api/v2/*')) {
                return sendError('This feature is temporarily unavailable for your account.', [], 403);
            }

            return sendError('This feature is temporarily unavailable for your account.', [
                'error_code' => 'feature_restricted',
                'feature' => $restriction->feature,
                'reason' => $restriction->reason,
                'restricted_until' => $restriction->ends_at?->toIso8601String(),
                'restriction_id' => $restriction->id,
            ], 403);
        }

        return $next($request);
    }
}
