<?php

namespace App\Services\Brand;

use App\Models\BrandAccount;
use App\Models\ManagedBrand;

/**
 * A dealer's purchase price for a brand catalogue item, from the dealer's
 * level (1..3):
 *
 *   1. the exact price the brand typed for that level on the item, else
 *   2. the public price minus the level's % for the item's category —
 *      the brand's per-brand override when it has one, else the account's.
 *
 * In "exact" pricing mode the % do not apply: an item without a price for
 * the level is sold to the dealer at the public price (and flagged to the
 * brand in its catalogue).
 *
 * Categories: versions | options (boats), engines | kits | propellers.
 */
class BrandPricing
{
    public function __construct(private BrandAccount $account) {}

    public function purchasePrice(object $item, string $category, int $level, ?ManagedBrand $brand = null): ?float
    {
        $exact = method_exists($item, 'exactLevelPrice') ? $item->exactLevelPrice($level) : null;
        if ($exact !== null) {
            return round($exact, 2);
        }

        $public = $item->price ?? null;
        if ($public === null || $public === '') {
            return null;
        }

        return round((float) $public * (1 - $this->percent($category, $level, $brand) / 100), 2);
    }

    /** The discount % that applies to a category at a level. */
    public function percent(string $category, int $level, ?ManagedBrand $brand = null): float
    {
        if ($this->account->isExactPricing()) {
            return 0.0;
        }

        $override = $brand && is_array($brand->level_pcts) ? ($brand->level_pcts[$level][$category] ?? $brand->level_pcts[(string) $level][$category] ?? null) : null;
        if ($override !== null && $override !== '') {
            return max(0.0, min(100.0, (float) $override));
        }

        return max(0.0, min(100.0, (float) ($this->account->levelsResolved()[$level]['pct'][$category] ?? 0)));
    }

    /**
     * How many live products lack an exact price for at least one level —
     * what exact mode needs filled in. Runs under the manager's BrandScope.
     */
    public function itemsMissingLevelPrices(): int
    {
        $classes = $this->account->isEngine()
            ? [\App\Models\BrandEngine::class, \App\Models\BrandEngineAccessory::class]
            : [\App\Models\BrandVariant::class, \App\Models\BrandOption::class];
        $n = 0;
        foreach ($classes as $class) {
            foreach ($class::where('is_archived', '!=', true)->get() as $item) {
                for ($l = 1; $l <= $this->account->levelCount(); $l++) {
                    if ($item->exactLevelPrice($l) === null) { $n++; break; }
                }
            }
        }

        return $n;
    }

    /** Category of an engine accessory for the level %. */
    public static function accessoryCategory(?string $type): string
    {
        return $type === 'kit' ? 'kits' : 'propellers';
    }
}
