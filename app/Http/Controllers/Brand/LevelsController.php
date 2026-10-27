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

        // As many levels as the page posts (1..MAX_LEVELS), numbered 1..n.
        $count = max(1, min(BrandAccount::MAX_LEVELS, count((array) $request->input('levels', []))));
        $rules = ['levels' => 'required|array', 'level_action' => 'nullable|in:add,remove',
            'pricing_mode' => 'nullable|in:' . BrandAccount::PRICING_PERCENT . ',' . BrandAccount::PRICING_EXACT];
        for ($n = 1; $n <= $count; $n++) {
            $rules["levels.$n.name"] = 'required|string|max:60';
            foreach ($cats as $c) $rules["levels.$n.pct.$c"] = 'nullable|numeric|min:0|max:100';
        }
        $rules['overrides'] = 'nullable|array';
        $rules['overrides.*.custom'] = 'nullable|boolean';
        $rules['overrides.*.pct'] = 'nullable|array';
        $rules['overrides.*.pct.*.*'] = 'nullable|numeric|min:0|max:100';
        $data = $request->validate($rules);

        $levels = [];
        for ($n = 1; $n <= $count; $n++) {
            $pct = [];
            foreach ($cats as $c) $pct[$c] = round((float) ($data['levels'][$n]['pct'][$c] ?? 0), 2);
            $levels[] = ['name' => trim($data['levels'][$n]['name']), 'pct' => $pct];
        }

        // Add a level (a copy of the last one's discounts) or remove the last.
        $msg = __('Price levels saved. Purchase prices updated for your connected dealers.');
        if (($data['level_action'] ?? null) === 'add' && $count < BrandAccount::MAX_LEVELS) {
            $levels[] = ['name' => __('Level :n', ['n' => $count + 1]), 'pct' => end($levels)['pct']];
            $count++;
            $msg = __('Level :n added. Name it, set its discounts, then save.', ['n' => $count]);
        } elseif (($data['level_action'] ?? null) === 'remove' && $count > 1) {
            $onIt = BrandAccess::where('brand_account_id', (string) $account->_id)->where('level', $count)
                ->whereIn('status', array_merge([BrandAccess::STATUS_ACTIVE], BrandAccess::OPEN_STATUSES))->count();
            if ($onIt) {
                return back()->withInput()->withErrors(['levels' => trans_choice(
                    '{1}:count dealer is on :level. Move them to another level first.|[2,*]:count dealers are on :level. Move them to another level first.',
                    $onIt, ['count' => $onIt, 'level' => $levels[$count - 1]['name']])]);
            }
            array_pop($levels);
            $count--;
            $msg = __('Last level removed.');
        }
        $account->update(['levels' => $levels, 'pricing_mode' => $data['pricing_mode'] ?? BrandAccount::PRICING_PERCENT]);

        foreach (ManagedBrand::all() as $brand) {
            $o = $data['overrides'][(string) $brand->_id] ?? null;
            if (! $o || empty($o['custom'])) {
                $brand->update(['level_pcts' => null]);
                continue;
            }
            $pcts = [];
            for ($n = 1; $n <= $count; $n++) {
                foreach ($cats as $c) {
                    $v = $o['pct'][$n][$c] ?? null;
                    $pcts[$n][$c] = ($v === null || $v === '') ? (float) $levels[$n - 1]['pct'][$c] : round((float) $v, 2);
                }
            }
            $brand->update(['level_pcts' => $pcts]);
        }

        app(BrandSync::class)->syncAccount($account->fresh());

        return redirect()->route('brand.levels.edit')->with('status', $msg);
    }
}
