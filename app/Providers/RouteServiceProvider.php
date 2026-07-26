<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/admin/index';

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // A personal-data export reads 15 tables and serialises the lot, so it is
        // far heavier than a normal request. Keyed by user id rather than IP:
        // the cost is per account, and a shared campus network must not let one
        // student's exports lock out everyone else's.
        RateLimiter::for('data-export', function (Request $request) {
            return Limit::perHour(5)
                ->by($request->user()?->id ?: $request->ip())
                // $headers carries Retry-After and the X-RateLimit-* pair; without
                // forwarding them the client has no way to know when to try again.
                ->response(function ($request, array $headers) {
                    return sendError(__('api.account.export_rate_limited'), [], 429)
                        ->withHeaders($headers);
                });
        });
    }
}
