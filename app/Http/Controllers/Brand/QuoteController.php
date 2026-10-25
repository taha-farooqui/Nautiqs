<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use App\Models\BrandAccess;
use App\Models\ManagedBrand;
use App\Models\Quote;
use App\Services\Brand\BrandQuotePresenter;
use Illuminate\Http\Request;

/**
 * Quotes made by connected dealers with the brand's products, client
 * identity hidden. Everything shown passes through BrandQuotePresenter.
 */
class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $account   = auth()->user()->brandAccount;
        $presenter = new BrandQuotePresenter($account);
        $period    = array_key_exists($request->query('period'), DashboardController::PERIODS) ? $request->query('period') : '6m';
        $since     = now()->startOfMonth()->subMonthsNoOverflow(DashboardController::PERIODS[$period]);

        $all = $presenter->query()->where('created_at', '>=', $since)->orderBy('created_at', 'desc')->get()
            ->map(fn (Quote $q) => $presenter->summary($q));

        $filters = [
            'dealer' => (string) $request->query('dealer', ''),
            'brand'  => (string) $request->query('brand', ''),
            'model'  => (string) $request->query('model', ''),
            'status' => (string) $request->query('status', ''),
            'period' => $period,
        ];
        $rows = $all
            ->when($filters['dealer'] !== '', fn ($c) => $c->where('company_id', $filters['dealer']))
            ->when($filters['brand'] !== '', fn ($c) => $c->where('managed_brand_id', $filters['brand']))
            ->when($filters['model'] !== '', fn ($c) => $c->where('boat', $filters['model']))
            ->when($filters['status'] !== '', fn ($c) => $c->where('status', $filters['status']))
            ->values();

        return view('brand.quotes.index', [
            'account' => $account, 'rows' => $rows, 'filters' => $filters,
            'dealers' => $all->pluck('dealer', 'company_id')->sort(),
            'models'  => $all->where('is_boat', true)->pluck('boat')->unique()->sort()->values(),
            'brands'  => ManagedBrand::orderBy('position')->orderBy('name')->get(),
            'stats'   => [
                'total' => $rows->count(),
                'open'  => $rows->where('status', Quote::STATUS_SENT)->count(),
                'won'   => $rows->where('status', Quote::STATUS_WON)->count(),
                'lost'  => $rows->where('status', Quote::STATUS_LOST)->count(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $account   = auth()->user()->brandAccount;
        $presenter = new BrandQuotePresenter($account);
        $quote     = $presenter->query()->where('_id', $id)->firstOrFail();

        $detail = $presenter->detail($quote);
        $access = BrandAccess::where('brand_account_id', (string) $account->_id)->where('company_id', (string) $quote->company_id)->first();
        $detail['level'] = $access ? $account->levelName($access->level) : null;

        return view('brand.quotes.show', ['q' => $detail, 'account' => $account]);
    }
}
