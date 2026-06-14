<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Foreign-exchange rate lookups for the catalogue import flow.
 *
 * Backed by https://frankfurter.app — the European Central Bank's daily
 * reference rates, served as JSON, no API key, no rate limit. If the
 * service is unreachable we return null and the caller decides what to
 * do (the OptionImporter records a per-row error so the user can retry).
 *
 * One-hour cache per (base, target) so a 500-row import with mixed
 * currencies only hits the wire once per unique pair.
 */
class FxRateService
{
    private const CACHE_PREFIX = 'fx:rate:';
    private const LAST_PREFIX  = 'fx:last:';
    private const CACHE_TTL    = 86400;        // 1 day — ECB publishes a daily reference rate
    private const LAST_TTL     = 60 * 60 * 24 * 30; // 30 days — last-known-good fallback
    private const ENDPOINT     = 'https://api.frankfurter.app/latest';
    private const TIMEOUT      = 4;    // seconds — keep imports snappy if the API is slow

    /**
     * Return the rate to multiply by to convert `$amount $base` into
     * `$target`. Returns 1.0 when base === target.
     *
     * The rate comes from the live ECB feed, refreshed daily. On a network
     * failure we fall back to the LAST successfully-fetched rate (persisted
     * for 30 days) rather than a hardcoded 1:1 — so a brief API outage never
     * silently prices a USD boat as if it were EUR. Returns null only when
     * the API is down AND we've never fetched this pair before.
     */
    public function rate(string $base, string $target): ?float
    {
        $base   = strtoupper(trim($base));
        $target = strtoupper(trim($target));
        if ($base === '' || $target === '') return null;
        if ($base === $target) return 1.0;

        $fresh = Cache::remember(self::CACHE_PREFIX . "{$base}:{$target}", self::CACHE_TTL, function () use ($base, $target) {
            try {
                $res = Http::timeout(self::TIMEOUT)
                    ->get(self::ENDPOINT, ['from' => $base, 'to' => $target]);
                if (! $res->ok()) {
                    Log::warning('FX rate fetch failed', ['base' => $base, 'target' => $target, 'status' => $res->status()]);
                    return null;
                }
                $rate = data_get($res->json(), "rates.{$target}");
                return is_numeric($rate) ? (float) $rate : null;
            } catch (\Throwable $e) {
                Log::warning('FX rate fetch threw', ['base' => $base, 'target' => $target, 'error' => $e->getMessage()]);
                return null;
            }
        });

        if ($fresh !== null) {
            // Remember the good value so we can survive a future outage.
            Cache::put(self::LAST_PREFIX . "{$base}:{$target}", $fresh, self::LAST_TTL);
            return $fresh;
        }

        // Fetch failed — don't cache the failure, and fall back to the last
        // known-good rate if we have one.
        Cache::forget(self::CACHE_PREFIX . "{$base}:{$target}");
        $last = Cache::get(self::LAST_PREFIX . "{$base}:{$target}");
        return is_numeric($last) ? (float) $last : null;
    }

    /**
     * Convenience: convert $amount from $from currency to $to currency.
     * Returns null when the rate is unavailable so callers can distinguish
     * "no conversion needed" (rate=1) from "we failed to fetch."
     */
    public function convert(float $amount, string $from, string $to): ?float
    {
        $rate = $this->rate($from, $to);
        return $rate === null ? null : round($amount * $rate, 2);
    }
}
