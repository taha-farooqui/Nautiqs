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
    private const CACHE_TTL    = 3600; // 1 hour
    private const ENDPOINT     = 'https://api.frankfurter.app/latest';
    private const TIMEOUT      = 4;    // seconds — keep imports snappy if the API is slow

    /**
     * Return the rate to multiply by to convert `$amount $base` into
     * `$target`. Returns 1.0 when base === target. Returns null on any
     * network / parse error.
     */
    public function rate(string $base, string $target): ?float
    {
        $base   = strtoupper(trim($base));
        $target = strtoupper(trim($target));
        if ($base === '' || $target === '') return null;
        if ($base === $target) return 1.0;

        return Cache::remember(self::CACHE_PREFIX . "{$base}:{$target}", self::CACHE_TTL, function () use ($base, $target) {
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
