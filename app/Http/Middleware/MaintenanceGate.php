<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Application-level maintenance mode (separate from Laravel's down file).
 * Toggled by the superadmin in /admin/settings. Locks out tenant users
 * with a friendly page; superadmins keep full access so they can flip
 * the toggle back off. Login and the maintenance page itself must stay
 * reachable, so this is applied on the post-auth route group only.
 */
class MaintenanceGate
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = PlatformSetting::singleton();
        if (! $settings->maintenance_mode) {
            return $next($request);
        }

        // Superadmin bypasses entirely — they need the platform area
        // open in order to switch maintenance back off.
        if ($request->user()?->role === User::ROLE_SUPERADMIN) {
            return $next($request);
        }

        return response()->view('maintenance', [
            'message' => $settings->maintenance_message,
        ], 503);
    }
}
