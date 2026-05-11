<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Apply the user's chosen UI locale to every request.
 *
 * Source of truth: a `locale` cookie (1-year TTL) set by the /locale route.
 * Defaults to French — Nautiqs ships FR-first per the V1 spec, EN is the
 * alternate. Only `fr` and `en` are accepted; anything else falls back
 * silently.
 */
class SetLocale
{
    private const SUPPORTED = ['en', 'fr'];

    public function handle(Request $request, Closure $next)
    {
        $locale = $request->cookie('locale', config('app.locale', 'fr'));
        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'fr';
        }
        App::setLocale($locale);
        return $next($request);
    }
}
