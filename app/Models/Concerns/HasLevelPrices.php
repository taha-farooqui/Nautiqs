<?php

namespace App\Models\Concerns;

/**
 * A brand catalogue item with a public price and, optionally, an exact
 * purchase price per level ("level_prices": [L1, L2, L3], null = use the
 * level's %). BrandPricing turns these into a dealer's purchase price.
 */
trait HasLevelPrices
{
    /** The exact price typed for this level, or null when the % applies. */
    public function exactLevelPrice(int $level): ?float
    {
        $prices = is_array($this->level_prices) ? array_values($this->level_prices) : [];
        $v = $prices[$level - 1] ?? null;

        return ($v === null || $v === '') ? null : (float) $v;
    }

    /** Normalise posted level prices to [float|null, float|null, float|null]. */
    public static function cleanLevelPrices($posted): array
    {
        $posted = is_array($posted) ? array_values($posted) : [];
        $out = [];
        for ($i = 0; $i < \App\Models\BrandAccount::LEVEL_COUNT; $i++) {
            $v = $posted[$i] ?? null;
            $v = is_string($v) ? str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($v)) : $v;
            $out[] = ($v === null || $v === '' || ! is_numeric($v)) ? null : round((float) $v, 2);
        }
        return $out;
    }
}
