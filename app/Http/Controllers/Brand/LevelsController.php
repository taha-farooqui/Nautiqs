<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use App\Models\BrandAccess;
use App\Models\BrandAccount;
use App\Models\ManagedBrand;
use App\Services\Brand\BrandPricing;
use App\Services\Brand\BrandSync;
use Illuminate\Http\Request;

/**
 * The three purchase-price levels: their names, the discount % per category,
 * and optional per-brand overrides (an importer buys each brand on its own
 * terms). Saving re-prices every connected dealer.
 */
class LevelsController extends Controller
{
    public function edit()
    {
        $account = auth()->user()->brandAccount;
        $brands  = ManagedBrand::orderBy('position')->orderBy('name')->get();
        $counts  = BrandAccess::where('brand_account_id', (string) $account->_id)
            ->where('status', BrandAccess::STATUS_ACTIVE)->get(['level'])->countBy('level');

        return view('brand.levels', [
            'account' => $account, 'levels' => $account->levelsResolved(), 'categories' => $account->categories(),
            'brands' => $brands, 'counts' => $counts,
            'missing' => (new BrandPricing($account))->itemsMissingLevelPrices(),
        ]);
    }

    public function update(Request $request)
    {
        $account = auth()->user()->brandAccount;
        $cats = $account->categories();

        $rules = ['levels' => 'required|array', 'pricing_mode' => 'nullable|in:' . BrandAccount::PRICING_PERCENT . ',' . BrandAccount::PRICING_EXACT];
        for ($n = 1; $n <= BrandAccount::LEVEL_COUNT; $n++) {
            $rules["levels.$n.name"] = 'required|string|max:60';
            foreach ($cats as $c) $rules["levels.$n.pct.$c"] = 'nullable|numeric|min:0|max:100';
        }
        $rules['overrides'] = 'nullable|array';
        $rules['overrides.*.custom'] = 'nullable|boolean';
        $rules['overrides.*.pct'] = 'nullable|array';
        $rules['overrides.*.pct.*.*'] = 'nullable|numeric|min:0|max:100';
        $data = $request->validate($rules);

        $levels = [];
        for ($n = 1; $n <= BrandAccount::LEVEL_COUNT; $n++) {
            $pct = [];
            foreach ($cats as $c) $pct[$c] = round((float) ($data['levels'][$n]['pct'][$c] ?? 0), 2);
            $levels[] = ['name' => trim($data['levels'][$n]['name']), 'pct' => $pct];
        }
        $account->update(['levels' => $levels, 'pricing_mode' => $data['pricing_mode'] ?? BrandAccount::PRICING_PERCENT]);

        foreach (ManagedBrand::all() as $brand) {
            $o = $data['overrides'][(string) $brand->_id] ?? null;
            if (! $o || empty($o['custom'])) {
                $brand->update(['level_pcts' => null]);
                continue;
            }
            $pcts = [];
            for ($n = 1; $n <= BrandAccount::LEVEL_COUNT; $n++) {
                foreach ($cats as $c) {
                    $v = $o['pct'][$n][$c] ?? null;
                    $pcts[$n][$c] = ($v === null || $v === '') ? (float) $levels[$n - 1]['pct'][$c] : round((float) $v, 2);
                }
            }
            $brand->update(['level_pcts' => $pcts]);
        }

        app(BrandSync::class)->syncAccount($account->fresh());

        return redirect()->route('brand.levels.edit')->with('status', __('Price levels saved. Purchase prices updated for your connected dealers.'));
    }
}
