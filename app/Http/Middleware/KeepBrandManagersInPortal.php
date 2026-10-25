<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The dealer pages only check that someone is signed in. A brand manager has
 * no dealership, so on those pages they would see nothing — and anything they
 * created would belong to no one. Send them back to their portal instead;
 * only their own pages and the account basics stay reachable.
 */
class KeepBrandManagersInPortal
{
    private const ALLOWED = [
        'brand.*', 'profile.*', 'logout', 'locale.switch', 'notifications.*',
        'verification.*', 'password.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isBrandManager() && ! $request->routeIs(...self::ALLOWED)) {
            return redirect()->route('brand.dashboard');
        }

        return $next($request);
    }
}
