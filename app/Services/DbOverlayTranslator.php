<?php

namespace App\Services;

use App\Models\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Translation\Translator;

/**
 * Extends Laravel's file-based translator with an optional DB override
 * layer. The superadmin Dictionary page writes to the `translations`
 * collection; this class reads from a cached snapshot of that collection
 * and returns DB values when present, falling back to lang/{locale}.json
 * for everything else.
 *
 * Cache strategy:
 *   - Single key per locale ("translations:overlay:fr") holding the full
 *     map. One DB round-trip per locale per cold cache (small payload —
 *     a few hundred KB at most).
 *   - TTL 24h to keep this cheap even if cache busting is forgotten.
 *   - Forget() is called from Translation::saved / Translation::deleted
 *     model observers (registered in AppServiceProvider) so edits show
 *     up instantly.
 */
class DbOverlayTranslator extends Translator
{
    private const CACHE_PREFIX = 'translations:overlay:';
    private const CACHE_TTL    = 86400; // 24h

    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $locale = $locale ?: $this->locale;
        $overrides = $this->overridesFor($locale);

        if (isset($overrides[$key])) {
            return $this->makeReplacements($overrides[$key], $replace);
        }

        return parent::get($key, $replace, $locale, $fallback);
    }

    /**
     * Pull all overrides for a locale, keyed by the original English source
     * string. Cached so __() doesn't hit Mongo on every render.
     */
    private function overridesFor(string $locale): array
    {
        return Cache::remember(self::CACHE_PREFIX . $locale, self::CACHE_TTL, function () use ($locale) {
            return Translation::where('locale', $locale)
                ->get(['key', 'value'])
                ->reduce(function ($carry, $row) {
                    $carry[$row->key] = $row->value;
                    return $carry;
                }, []);
        });
    }

    /**
     * Bust the cache for a single locale. Called from the model observer
     * after any insert/update/delete.
     */
    public static function forget(string $locale): void
    {
        Cache::forget(self::CACHE_PREFIX . $locale);
    }

    public static function forgetAll(): void
    {
        foreach (['fr', 'en'] as $locale) {
            self::forget($locale);
        }
    }
}
