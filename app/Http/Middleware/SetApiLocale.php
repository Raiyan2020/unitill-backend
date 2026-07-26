<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Applies the mobile app's `lang` header to the request locale so __() resolves
 * against lang/{code}/.
 *
 * Separate from SetLanguage, which reads the session and serves the admin panel.
 * The API is stateless, so the header is the only signal available per request.
 */
class SetApiLocale
{
    /**
     * Mirrors LanguageSeeder. Anything outside this list falls back to English
     * rather than being passed to setLocale(), where an unknown code would
     * silently return the key itself instead of a translation.
     */
    public const SUPPORTED = ['en', 'ar', 'fr', 'es', 'zh'];

    public function handle(Request $request, Closure $next)
    {
        $requested = strtolower(trim((string) $request->header('lang')));

        // Tolerate regional tags such as "en-GB" or "zh-Hans" by taking the
        // primary subtag: clients send whatever the device reports.
        if ($requested !== '' && str_contains($requested, '-')) {
            $requested = explode('-', $requested)[0];
        }

        $locale = in_array($requested, self::SUPPORTED, true)
            ? $requested
            : config('app.fallback_locale', 'en');

        App::setLocale($locale);

        return $next($request);
    }
}
