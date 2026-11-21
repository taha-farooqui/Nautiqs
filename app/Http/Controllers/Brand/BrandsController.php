<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use App\Models\BrandAccess;
use App\Models\BrandEngine;
use App\Models\BrandModel;
use App\Models\ManagedBrand;
use Illuminate\Http\Request;

/**
 * The brands an account manages — read-only from the portal. Only Nautiqs
 * (superadmin) adds, renames or removes a brand; a brand manager works inside
 * the brands they are given. A brand name belongs to one account per territory.
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
        // Brand names are set by Nautiqs (superadmin) only — a brand manager
        // can no longer rename one from their portal.
        abort(403);
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
