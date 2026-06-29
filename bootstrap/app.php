<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role'         => \App\Http\Middleware\EnsureUserHasRole::class,
            'superadmin'   => \App\Http\Middleware\RequireSuperadmin::class,
            'maintenance'  => \App\Http\Middleware\MaintenanceGate::class,
        ]);

        // Apply the dealership's configured timezone (§17.3) to every web
        // request so dates render in the dealer's local TZ.
        // SetLocale reads the `locale` cookie so __() resolves to FR or EN
        // on every request.
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SetCompanyTimezone::class,
        ]);

        // Trust Railway's edge proxy so generated URLs use https://, cookies
        // get the secure flag, and OAuth callbacks match the production URL.
        $middleware->trustProxies(at: '*', headers:
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A stale CSRF token (419 Page Expired) — typically a tab left open
        // past the session lifetime, most visibly when logging out. Rather
        // than show the bare 419 page, complete the logout (if that's what
        // was attempted) and send the user to the login screen to re-auth.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->routeIs('logout') || $request->is('logout')) {
                \Illuminate\Support\Facades\Auth::guard('web')->logout();
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            }

            return redirect()->route('login')
                ->with('status', __('Your session expired — please sign in again.'));
        });
    })->create();
