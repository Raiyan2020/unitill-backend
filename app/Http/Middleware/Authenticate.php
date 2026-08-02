<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * @param  array<int, string|null>  $guards
     */
    protected function unauthenticated($request, array $guards): void
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            throw new AuthenticationException('Unauthenticated.', $guards);
        }

        parent::unauthenticated($request, $guards);
    }

    protected function redirectTo(Request $request): ?string
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }

        return url('/admin/login');
    }
}
