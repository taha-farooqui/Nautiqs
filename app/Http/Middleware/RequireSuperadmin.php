<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the /admin/* area. Returns 404 instead of 403 on auth failure so
 * the panel's existence isn't leaked to scraping or unauthenticated probes.
 * A signed-in tenant user hitting /admin sees the same not-found page they'd
 * see for any other unknown URL.
 */
class RequireSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== User::ROLE_SUPERADMIN) {
            abort(404);
        }

        return $next($request);
    }
}
