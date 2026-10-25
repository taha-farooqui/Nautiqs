<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use App\Models\BrandAccess;
use App\Models\BrandEngine;
use App\Models\BrandModel;
use App\Models\ManagedBrand;
use App\Models\Quote;
use App\Services\Brand\BrandQuotePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The brand's view of its market: how its connected dealers quote its
 * products. Every figure comes from BrandQuotePresenter rows, never from the
 * quotes themselves.
 */
class DashboardController extends Controller
{
    public const PERIODS = ['month' => 0, '6m' => 5, 'year' => 11];

    public function __invoke(Request $request)
    {
        $account = auth()->user()->brandAccount;
        $period  = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : '6m';
        $brandId = (string) $request->query('brand', '');
        $since   = now()->startOfMonth()->subMonthsNoOverflow(self::PERIODS[$period]);

        $presenter = new BrandQuotePresenter($account);
        $rows = $presenter->query()->where('created_at', '>=', $since)->orderBy('created_at', 'desc')->get()
            ->map(fn (Quote $q) => $presenter->summary($q))
            ->when($brandId !== '', fn ($c) => $c->where('managed_brand_id', $brandId))
            ->values();

        $won    = $rows->where('status', Quote::STATUS_WON);
        $closed = $rows->whereIn('status', [Quote::STATUS_WON, Quote::STATUS_LOST])->count();

        $kpis = [
            'quotes'   => $rows->count(),
            'quoted'   => $rows->sum('value_ht'),
            'won'      => $won->sum('value_ht'),
            'won_n'    => $won->count(),
            'win_rate' => $closed ? round($won->count() / $closed * 100) : null,
            'avg'      => $rows->count() ? $rows->sum('value_ht') / $rows->count() : 0,
        ];

        // Monthly: quotes made, and quotes won (by creation month).
        $months = [];
        for ($m = Carbon::parse($since); $m <= now(); $m = $m->copy()->addMonthNoOverflow()) {
            $key = $m->format('Y-m');
            $months[$key] = ['label' => $m->translatedFormat('M'), 'quoted' => 0, 'won' => 0];
        }
        foreach ($rows as $r) {
            $key = $r['date']?->format('Y-m');
            if (isset($months[$key])) {
                $months[$key]['quoted']++;
                if ($r['status'] === Quote::STATUS_WON) $months[$key]['won']++;
            }
        }

        // Most quoted: boat versions for boat brands, engines for engine brands.
        $top = $account->isEngine()
            ? $rows->flatMap(fn ($r) => $r['engines'])->countBy()->sortDesc()->take(6)
            : $rows->where('is_boat', true)->map(fn ($r) => $r['version'] ?: $r['boat'])->countBy()->sortDesc()->take(6);

        // Per dealer.
        $levels = BrandAccess::where('brand_account_id', (string) $account->_id)->get()
            ->keyBy(fn ($a) => (string) $a->company_id);
        $dealers = $rows->groupBy('company_id')->map(function ($g, $cid) use ($levels, $account) {
            $w = $g->where('status', Quote::STATUS_WON);
            $c = $g->whereIn('status', [Quote::STATUS_WON, Quote::STATUS_LOST])->count();
            $gross = $g->sum('gross_ht');

            return [
                'name' => $g->first()['dealer'], 'level' => $account->levelName($levels[$cid]->level ?? null),
                'quotes' => $g->count(), 'quoted' => $g->sum('value_ht'), 'won' => $w->count(),
                'win_rate' => $c ? round($w->count() / $c * 100) : null,
                'discount' => $gross > 0 ? round((1 - $g->sum('value_ht') / $gross) * 100, 1) : 0,
            ];
        })->sortByDesc('quoted')->values();

        // Quotes by status, for the donut.
        $statuses = collect([Quote::STATUS_DRAFT, Quote::STATUS_SENT, Quote::STATUS_WON, Quote::STATUS_LOST])
            ->mapWithKeys(fn ($s) => [$s => $rows->where('status', $s)->count()]);

        // Getting started: what a new brand still has to do before stats appear.
        $accesses  = BrandAccess::where('brand_account_id', (string) $account->_id)->get();
        $connected = $accesses->where('status', BrandAccess::STATUS_ACTIVE)->count();
        $items = $account->isEngine() ? BrandEngine::count() : BrandModel::count();
        $levelsSet = collect($account->levelsResolved())->contains(fn ($l) => array_sum($l['pct']) > 0);
        $steps = [
            ['done' => $items > 0, 'icon' => $account->isEngine() ? 'ri-settings-3-line' : 'ri-sailboat-line',
                'title' => __('Add your catalogue'), 'text' => __('By hand or from your Excel price list.'),
                'href' => route($account->isEngine() ? 'brand.engines.index' : 'brand.catalogue.index')],
            ['done' => $levelsSet, 'icon' => 'ri-price-tag-3-line',
                'title' => __('Set your price levels'), 'text' => __('The discount each level of dealer gets.'),
                'href' => route('brand.levels.edit')],
            ['done' => $accesses->isNotEmpty(), 'icon' => 'ri-user-add-line',
                'title' => __('Invite your dealers'), 'text' => __('They receive your catalogue at their level.'),
                'href' => route('brand.dealers.index')],
            ['done' => $connected > 0, 'icon' => 'ri-links-line',
                'title' => __('First dealer connected'), 'text' => __('Their quotes start appearing here.'),
                'href' => route('brand.dealers.index', ['tab' => 'waiting'])],
        ];

        return view('brand.dashboard', [
            'account' => $account, 'period' => $period, 'brandId' => $brandId,
            'brands' => ManagedBrand::orderBy('position')->orderBy('name')->get(),
            'kpis' => $kpis, 'months' => array_values($months), 'top' => $top, 'dealers' => $dealers,
            'statuses' => $statuses, 'steps' => $steps,
            'latest' => $rows->take(8),
            'connected' => $connected,
            'pending' => $accesses->whereIn('status', [BrandAccess::STATUS_PENDING, BrandAccess::STATUS_INVITED])->count(),
        ]);
    }
}
