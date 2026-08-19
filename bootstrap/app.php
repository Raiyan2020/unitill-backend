<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureFeatureIsAvailable;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RedirectIfNotAdmin;
use App\Http\Middleware\RedirectIfNotSchool;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLanguage;
use App\Http\Middleware\ValidateSignature;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // routes/admin.php (the old Blade panel) is deliberately not loaded: the
        // React dashboard now owns /admin/* and is served by the SPA catch-all in
        // routes/web.php. Loading it here would win those URLs back.
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            // A plain URL, not a named route: /admin/login is a client-side React
            // route with no server-side name to resolve.
            return url('/admin/login');
        });

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // No statefulApi(): every API caller authenticates with a Sanctum bearer
        // token — the mobile app, the React dashboard (frontend/src/lib/api.ts)
        // and broadcasting/auth alike. Nothing sends cookies, so nothing sets
        // withCredentials. statefulApi() would attach session + CSRF to /api/*
        // for any request whose Origin matches a sanctum.stateful domain, which
        // is what made the dashboard's cross-domain POST /api/admin/login fail
        // with 419 CSRF token mismatch.
        $middleware->throttleApi('api');

        // Must run before controllers and FormRequests so __() and validation
        // messages both resolve in the caller's language.
        $middleware->api(prepend: [
            SetApiLocale::class,
        ]);

        $middleware->replace(
            ValidateCsrfToken::class,
            VerifyCsrfToken::class,
        );

        $middleware->replace(
            Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
            PreventRequestsDuringMaintenance::class,
        );

        $middleware->web(append: [
            LaravelLocalizationRoutes::class,
            LocaleSessionRedirect::class,
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'signed' => ValidateSignature::class,
            'localize' => LaravelLocalizationRoutes::class,
            'localizationRedirect' => LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect' => LocaleSessionRedirect::class,
            'localeCookieRedirect' => LocaleCookieRedirect::class,
            'localeViewPath' => LaravelLocalizationViewPath::class,
            'SetLanguage' => SetLanguage::class,
            'admin.auth' => RedirectIfNotAdmin::class,
            'school.auth' => RedirectIfNotSchool::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'feature.available' => EnsureFeatureIsAvailable::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->call(function () {
            Artisan::call('route:cache');
        })->weekly();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);

        $exceptions->render(function (Throwable $e, Request $request) {
            // HttpResponseException already carries the response the thrower
            // wants returned (throttle limits, FormRequest::failedValidation).
            // Without this it falls through to the catch-all below and every
            // such response is rewritten as a 500.
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }

            if ($e instanceof AuthenticationException) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return sendError('Unauthenticated', [], 401);
                }

                return redirect()->to(url('/admin/login'));
            }

            if ($e instanceof ValidationException) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return sendError(
                        $e->validator->errors()->first(),
                        $e->errors(),
                        422
                    );
                }

                return null;
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                if ($e instanceof HttpExceptionInterface) {
                    return sendError($e->getMessage() ?: 'Error', [], $e->getStatusCode());
                }

                return sendError($e->getMessage() ?: 'Server error', [], 500);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->view('errors.405', [], 405);
            }

            if ($e instanceof NotFoundHttpException) {
                if (
                    ! ($request->is('ar/admin*') || $request->is('en/admin*'))
                    && ! $request->is('telescope*')
                ) {
                    return response()->view('errors.404', [], 404);
                }
            }

            return null;
        });
    })->create();
