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
            'globalBrands' => ManagedBrand::availableGlobalNames(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'share' => 'nullable|boolean']);
        $name = trim($data['name']);
        if ($this->taken($name)) {
            return back()->withInput()->withErrors(['name' => __('":name" is already managed on Nautiqs. Contact Nautiqs if it is yours.', ['name' => $name])]);
        }

        $brand = ManagedBrand::create(ManagedBrand::fromName($name) + ['position' => ManagedBrand::count(), 'is_active' => true]);
        $name = $brand->name;

        // Add it to the dealers already connected, so its catalogue reaches them as it is built.
        $shared = 0;
        if ($request->boolean('share')) {
            $sync = app(BrandSync::class);
            foreach ($this->accesses()->where('status', BrandAccess::STATUS_ACTIVE)->get() as $access) {
                $access->update(['managed_brand_ids' => array_values(array_unique(array_merge($access->brandIds(), [(string) $brand->_id])))]);
                $sync->syncAccess($access, $this->account());
                $shared++;
            }
        }

        return redirect()->route('brand.brands.index')->with('status', $shared
            ? trans_choice('{1}Brand :name added and shared with your connected dealer.|[2,*]Brand :name added and shared with your :count connected dealers.', $shared, ['name' => $name, 'count' => $shared])
            : __('Brand :name added.', ['name' => $name]));
    }

    public function update(string $id, Request $request)
    {
        $brand = ManagedBrand::findOrFail($id);
        $data = $request->validate(['name' => 'required|string|max:120']);
        $name = trim($data['name']);
        if (mb_strtolower($name) !== mb_strtolower((string) $brand->name) && $this->taken($name)) {
            return back()->withErrors(['name' => __('":name" is already managed on Nautiqs. Contact Nautiqs if it is yours.', ['name' => $name])]);
        }
        $brand->update(['name' => $name]);
        app(BrandSync::class)->syncAccount($this->account(), notify: false);

        return back()->with('status', __('Brand renamed.'));
    }

    /** Taken by any account (this one included), case-insensitive. */
    private function taken(string $name): bool
    {
        $key = mb_strtolower(trim($name));

        return ManagedBrand::withoutGlobalScopes()->get(['name'])->contains(fn ($b) => mb_strtolower(trim((string) $b->name)) === $key);
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
