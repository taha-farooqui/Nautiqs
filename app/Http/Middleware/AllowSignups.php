<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns 404 when the platform owner has switched sign-ups off in
 * /admin/settings. Used on /register so the public registration page
 * disappears entirely (not a friendlier "sign-ups closed" message —
 * the point is to look like the route doesn't exist for closed-beta).
 */
class AllowSignups
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PlatformSetting::singleton()->signups_enabled) {
            abort(404);
        }
        return $next($request);
    }
}
