<?php

namespace App\Services\Brand;

use App\Models\BrandAccount;
use App\Models\ManagedBrand;

/**
 * Brand names that belong to a brand account. Once a manufacturer or
 * importer manages a brand on Nautiqs, it is the only one who publishes it:
 * dealers can no longer add boats (or engines) of that brand themselves —
 * they receive it from the brand. What dealers already have is left alone.
 *
 * Boat and engine names are separate: an engine account owning "Yamaha"
 * does not block a "Yamaha" boat brand, and the reverse.
 */
class ClaimedBrands
{
    /** @var array<string, array<string, string>>|null kind => [lowercase name => display name] */
    private static ?array $cache = null;

    /** @return array<string, string> lowercase name => display name */
    public static function names(string $kind): array
    {
        if (self::$cache === null) {
            $kinds = BrandAccount::all(['_id', 'kind'])->mapWithKeys(fn ($a) => [(string) $a->_id => $a->kind ?: BrandAccount::KIND_BOAT]);
            self::$cache = [BrandAccount::KIND_BOAT => [], BrandAccount::KIND_ENGINE => []];
            foreach (ManagedBrand::withoutGlobalScopes()->get(['name', 'brand_account_id']) as $b) {
                $k = $kinds[(string) $b->brand_account_id] ?? null;
                if ($k && isset(self::$cache[$k])) {
                    self::$cache[$k][self::key($b->name)] = (string) $b->name;
                }
            }
        }

        return self::$cache[$kind] ?? [];
    }

    public static function isClaimed(?string $name, string $kind): bool
    {
        $key = self::key($name);

        return $key !== '' && isset(self::names($kind)[$key]);
    }

    /** Forget the cache (after a brand is added or renamed in this request). */
    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function key(?string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $name)));
    }
}
