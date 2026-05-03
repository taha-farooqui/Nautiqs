<?php

namespace App\Services;

use App\Models\Company;

/**
 * Central quote math per spec §8.3 (Live financial summary), §9 (discount
 * cascade: item → category → global), §10 (trade-in deduction), §15 (currency).
 *
 * Returns the full totals object used by the builder summary, the persisted
 * Quote.totals snapshot, and the PDF templates (§12, §13).
 */
class QuoteCalculator
{
    /**
     * @param array $input {
     *     base_price: float,
     *     base_cost: float|null,
     *     variant_currency: string,  // EUR | USD
     *     exchange_rate: float|null, // USD->EUR; required if any USD cost
     *     options: [ { category, unit_price, quantity, unit_cost, discount_pct, currency } ],
     *     custom_items: [ { category, amount, cost, discount_pct } ],
     *     category_discounts: [ 'CC Configuration' => 5.0 ],
     *     global_discount_pct: float,
     *     trade_in_value: float,
     *     vat_rate: float,
     * }
     */
    public function compute(array $input, Company $company): array
    {
        $vatRate            = (float) ($input['vat_rate'] ?? $company->default_vat_rate ?? 20);
        $basePrice          = (float) ($input['base_price'] ?? 0);
        $baseCost           = $this->toEur($input['base_cost'] ?? null, $input['variant_currency'] ?? 'EUR', $input['exchange_rate'] ?? null);
        $categoryDiscounts  = $input['category_discounts'] ?? [];
        $boatDiscountPct    = (float) ($input['boat_discount_pct'] ?? 0);
        $optionsDiscountPct = (float) ($input['options_discount_pct'] ?? 0);
        $globalDiscountPct  = (float) ($input['global_discount_pct'] ?? 0);
        $tradeIn            = (float) ($input['trade_in_value'] ?? 0);

        // Hull line (the base boat) — apply boat-level discount
        $hullLine            = $basePrice * (1 - $boatDiscountPct / 100);
        $boatDiscountAmount  = $basePrice - $hullLine;

        // Options
        $optionsRows = [];
        foreach (($input['options'] ?? []) as $opt) {
            $qty         = max(1, (int) ($opt['quantity'] ?? 1));
            $unit        = (float) ($opt['unit_price'] ?? 0);
            $unitCost    = $this->toEur($opt['unit_cost'] ?? null, $opt['currency'] ?? 'EUR', $input['exchange_rate'] ?? null);
            $lineGross   = $unit * $qty;
            $lineCost    = $unitCost !== null ? $unitCost * $qty : null;
            $itemDiscPct = (float) ($opt['discount_pct'] ?? 0);
            $afterItem   = $lineGross * (1 - $itemDiscPct / 100);

            $catPct      = (float) ($categoryDiscounts[$opt['category'] ?? ''] ?? 0);
            $afterCat    = $afterItem * (1 - $catPct / 100);

            $optionsRows[] = [
                'category'     => $opt['category'] ?? 'Options',
                'label'        => $opt['label'] ?? '',
                'quantity'     => $qty,
                'unit_price'   => $unit,
                'line_gross'   => round($lineGross, 2),
                'item_discount_pct' => $itemDiscPct,
                'cat_discount_pct'  => $catPct,
                'line_after_cat'    => round($afterCat, 2),
                'line_cost'         => $lineCost !== null ? round($lineCost, 2) : null,
                'has_real_cost'     => $unitCost !== null,
            ];
        }

        // Custom items (§8.1 step 5)
        $customRows = [];
        foreach (($input['custom_items'] ?? []) as $ci) {
            $amount      = (float) ($ci['amount'] ?? 0);
            $cost        = $ci['cost'] !== null && $ci['cost'] !== '' ? (float) $ci['cost'] : null;
            $itemDiscPct = (float) ($ci['discount_pct'] ?? 0);
            $afterItem   = $amount * (1 - $itemDiscPct / 100);
            // Custom items are their own category ("custom_items" for margin preset).
            $catPct      = (float) ($categoryDiscounts[$ci['category'] ?? 'custom_items'] ?? 0);
            $afterCat    = $afterItem * (1 - $catPct / 100);

            $customRows[] = [
                'category'          => $ci['category'] ?? 'custom_items',
                'label'             => $ci['label'] ?? '',
                'amount'            => $amount,
                'item_discount_pct' => $itemDiscPct,
                'cat_discount_pct'  => $catPct,
                'line_after_cat'    => round($afterCat, 2),
                'line_cost'         => $cost,
                'has_real_cost'     => $cost !== null,
            ];
        }

        $optionsBeforeBlock      = array_sum(array_column($optionsRows, 'line_after_cat'));
        $optionsBlockDiscount    = $optionsBeforeBlock * ($optionsDiscountPct / 100);
        $optionsSubtotal         = $optionsBeforeBlock - $optionsBlockDiscount;
        $customSubtotal          = array_sum(array_column($customRows,  'line_after_cat'));
        $baseSubtotal            = $hullLine;

        $subtotalBeforeGlobal = $baseSubtotal + $optionsSubtotal + $customSubtotal;
        $globalDiscountAmount = $subtotalBeforeGlobal * ($globalDiscountPct / 100);
        $totalHt              = $subtotalBeforeGlobal - $globalDiscountAmount;
        $vatAmount            = $totalHt * ($vatRate / 100);
        $totalTtc             = $totalHt + $vatAmount;
        $netPayable           = $totalTtc - $tradeIn;

        // Margin (§3 cascade + §8.3)
        $realCostTotal = 0.0;
        $hasAnyRealCost = false;
        if ($baseCost !== null) {
            $realCostTotal += $baseCost;
            $hasAnyRealCost = true;
        }
        foreach ($optionsRows as $r) {
            if ($r['has_real_cost']) {
                $realCostTotal += $r['line_cost'];
                $hasAnyRealCost = true;
            }
        }
        foreach ($customRows as $r) {
            if ($r['has_real_cost']) {
                $realCostTotal += $r['line_cost'];
                $hasAnyRealCost = true;
            }
        }

        if ($hasAnyRealCost) {
            $marginAmount = $totalHt - $realCostTotal;
            $marginPct    = $totalHt > 0 ? ($marginAmount / $totalHt) * 100 : 0;
            $marginType   = 'real';
        } else {
            // Estimated via margin presets (§3 priority 2 + 4).
            $estimated = 0.0;
            $estimated += $baseSubtotal  * ($company->marginForCategory('hull') / 100);
            $estimated += $optionsSubtotal * ($company->marginForCategory('options') / 100);
            $estimated += $customSubtotal  * ($company->marginForCategory('custom_items') / 100);
            $marginAmount = $estimated;
            $marginPct    = $totalHt > 0 ? ($marginAmount / $totalHt) * 100 : 0;
            $marginType   = 'estimated';
        }

        return [
            'base_price_gross'        => round($basePrice, 2),
            'boat_discount_pct'       => $boatDiscountPct,
            'boat_discount_amount'    => round($boatDiscountAmount, 2),
            'base_ht'                 => round($baseSubtotal, 2),

            'options_gross'           => round($optionsBeforeBlock, 2),
            'options_discount_pct'    => $optionsDiscountPct,
            'options_discount_amount' => round($optionsBlockDiscount, 2),
            'options_ht'              => round($optionsSubtotal, 2),

            'custom_items_ht'         => round($customSubtotal, 2),
            'subtotal_ht'             => round($subtotalBeforeGlobal, 2),

            'global_discount_pct'     => $globalDiscountPct,
            'global_discount_amount'  => round($globalDiscountAmount, 2),

            'total_ht'                => round($totalHt, 2),
            'vat_rate'                => $vatRate,
            'vat_amount'              => round($vatAmount, 2),
            'total_ttc'               => round($totalTtc, 2),
            'trade_in_deduction'      => round($tradeIn, 2),
            'net_payable'             => round($netPayable, 2),
            'total_cost'              => $hasAnyRealCost ? round($realCostTotal, 2) : null,
            'margin_amount'           => round($marginAmount, 2),
            'margin_pct'              => round($marginPct, 2),
            'margin_type'             => $marginType,
            'options_rows'            => $optionsRows,
            'custom_items_rows'       => $customRows,
        ];
    }

    private function toEur(mixed $amount, string $currency, ?float $rate): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }
        $amount = (float) $amount;
        if (strtoupper($currency) === 'EUR') {
            return $amount;
        }
        if (strtoupper($currency) === 'USD' && $rate) {
            return $amount * $rate;
        }
        return $amount; // fallback — treat unknown currencies as already in EUR
    }

    private function applyItemDiscount(float $amount, float $pct): float
    {
        return $amount * (1 - $pct / 100);
    }
}
