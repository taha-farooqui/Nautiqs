<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * Spec §17.3 — apply the dealership's configured timezone for the duration
 * of the request so all Carbon date casts (created_at, sent_at, etc.) and
 * date formatters render in the dealer's local TZ. The DB still stores
 * UTC; only display flips.
 *
 * Falls back silently to the app default when the user has no company or
 * the stored value is invalid.
 */
class SetCompanyTimezone
{
    public function handle(Request $request, Closure $next)
    {
        $tz = optional(auth()->user()?->company)->timezone;

        if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
            // PHP global default — Carbon's now() and date casts pick this up.
            date_default_timezone_set($tz);
            // Carbon-specific override so any frozen instances honour it too.
            Date::setLocale(config('app.locale'));
        }

        return $next($request);
    }
}
