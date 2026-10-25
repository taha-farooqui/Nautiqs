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
        $override = $brand && is_array($brand->level_pcts) ? ($brand->level_pcts[$level][$category] ?? $brand->level_pcts[(string) $level][$category] ?? null) : null;
        if ($override !== null && $override !== '') {
            return max(0.0, min(100.0, (float) $override));
        }

        return max(0.0, min(100.0, (float) ($this->account->levelsResolved()[$level]['pct'][$category] ?? 0)));
    }

    /** Category of an engine accessory for the level %. */
    public static function accessoryCategory(?string $type): string
    {
        return $type === 'kit' ? 'kits' : 'propellers';
    }
}
