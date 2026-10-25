<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the /brand/* portal: brand managers of an active brand account only.
 * Anyone else gets a plain 404, like the admin area.
 */
class RequireBrandManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== User::ROLE_BRAND_MANAGER || ! $user->brand_account_id) {
            abort(404);
        }

        $account = $user->brandAccount;
        if (! $account) {
            abort(404);
        }
        if (! $account->is_active) {
            abort(403, __('This brand account is suspended. Contact Nautiqs.'));
        }

        return $next($request);
    }
}
