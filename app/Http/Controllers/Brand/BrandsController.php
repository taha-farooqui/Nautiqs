<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use App\Models\BrandAccess;
use App\Models\BrandEngine;
use App\Models\BrandModel;
use App\Models\ManagedBrand;
use App\Services\Brand\BrandSync;
use Illuminate\Http\Request;

/**
 * The brands an account manages. Brand managers add and rename them
 * themselves (an importer taking on a new line does not wait for Nautiqs).
 * A brand name belongs to one account only, across the whole platform.
 */
class BrandsController extends Controller
{
    public function index()
    {
        $account = $this->account();
        $class = $account->isEngine() ? BrandEngine::class : BrandModel::class;

        return view('brand.brands', [
            'account' => $account,
            'brands' => ManagedBrand::orderBy('position')->orderBy('name')->get(),
            'itemCounts' => $class::where('is_archived', '!=', true)->get(['managed_brand_id'])->countBy(fn ($m) => (string) $m->managed_brand_id),
            'dealerCounts' => $this->accesses()->where('status', BrandAccess::STATUS_ACTIVE)->get()
                ->flatMap(fn ($a) => $a->brandIds())->countBy(),
            'connected' => $this->accesses()->where('status', BrandAccess::STATUS_ACTIVE)->count(),
            'globalBrands' => ManagedBrand::globalBrandNames(),
        ]);
    }

    public function store(Request $request)
    {
        // Brands are created by Nautiqs (superadmin) only — a brand manager
        // can no longer add one from their portal.
        abort(403);
    }

    public function update(string $id, Request $request)
    {
        $account = $this->account();
        $brand = ManagedBrand::findOrFail($id);
        $data = $request->validate(['name' => 'required|string|max:120']);
        $name = trim($data['name']);
        if (mb_strtolower($name) !== mb_strtolower((string) $brand->name)) {
            // Already on this account?
            $key = mb_strtolower($name);
            if (ManagedBrand::where('_id', '!=', $id)->get(['name'])->contains(fn ($b) => mb_strtolower(trim((string) $b->name)) === $key)) {
                return back()->withErrors(['name' => __('You already manage a brand named ":name".', ['name' => $name])]);
            }
            // Taken by another account in an overlapping territory?
            if (ManagedBrand::territoryConflicts([$name], $account->country, $account->region, (string) $account->_id)) {
                return back()->withErrors(['name' => __('":name" is already managed on Nautiqs for this territory. Contact Nautiqs if it is yours.', ['name' => $name])]);
            }
        }
        $brand->update(['name' => $name]);
        app(BrandSync::class)->syncAccount($account, notify: false);

        return back()->with('status', __('Brand renamed.'));
    }

    private function accesses()
    {
        return BrandAccess::where('brand_account_id', (string) $this->account()->_id);
    }

    private function account()
    {
        return auth()->user()->brandAccount;
    }
}
