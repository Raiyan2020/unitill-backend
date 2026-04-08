<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login'); // أو 'admin.login' حسب الراوت الموجود عندك
            }

            return route('admin.login'); // الافتراضي
        }
        return null; // إذا كان الطلب يتوقع JSON، لا حاجة لإعادة التوجيه
    }
}
