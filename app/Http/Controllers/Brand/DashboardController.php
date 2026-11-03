<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use App\Models\BrandAccess;
use App\Models\BrandEngine;
use App\Models\BrandModel;
use App\Models\Company;
use App\Models\ManagedBrand;
use App\Models\Quote;
use App\Services\Brand\BrandQuotePresenter;
use App\Services\Xlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The brand's view of its market: how its connected dealers quote its
 * products. Every figure comes from BrandQuotePresenter rows, never from the
 * quotes themselves. Each KPI is compared with the period just before.
 */
class DashboardController extends Controller
{
    public const PERIODS = ['month' => 0, '6m' => 5, 'year' => 11];

    public function __invoke(Request $request)
    {
        $account = auth()->user()->brandAccount;
        [$period, $brandId, $dealerId, $since] = $this->filters($request);
        $months = self::PERIODS[$period] + 1;
        $prevSince = $since->copy()->subMonthsNoOverflow($months);

        $presenter = new BrandQuotePresenter($account);
        $all  = $this->rows($presenter, $prevSince, $brandId, $dealerId);
        $rows = $all->filter(fn ($r) => $r['date'] && $r['date'] >= $since)->values();
        $prev = $all->filter(fn ($r) => $r['date'] && $r['date'] < $since)->values();

        $stats = fn (Collection $c) => [
            'quoted' => $c->sum('value_ht'),
            'won'    => $c->where('status', Quote::STATUS_WON)->sum('value_ht'),
            'won_n'  => $c->where('status', Quote::STATUS_WON)->count(),
            'sent'   => $c->whereIn('status', [Quote::STATUS_SENT, Quote::STATUS_WON, Quote::STATUS_LOST])->count(),
            'rate'   => ($closed = $c->whereIn('status', [Quote::STATUS_WON, Quote::STATUS_LOST])->count())
                ? round($c->where('status', Quote::STATUS_WON)->count() / $closed * 100) : null,
        ];
        $now = $stats($rows);
        $before = $stats($prev);
        $delta = fn ($a, $b) => $b > 0 ? (int) round(($a - $b) / $b * 100) : null;

        $targets = is_array($account->targets) ? $account->targets : [];
        $monthlyTarget = (float) ($targets['monthly_quoted'] ?? 0);
        $rateTarget = isset($targets['win_rate']) ? (float) $targets['win_rate'] : null;

        $kpis = [
            ['label' => __('Quoted value (HT)'), 'value' => $now['quoted'], 'money' => true,
                'delta' => $delta($now['quoted'], $before['quoted']), 'unit' => '%',
                'note' => __('vs. :v', ['v' => $this->eur($before['quoted'])])],
            ['label' => __('Won (HT)'), 'value' => $now['won'], 'money' => true,
                'delta' => $delta($now['won'], $before['won']), 'unit' => '%',
                'note' => trans_choice('{0}no signed quote|{1}:count signed quote|[2,*]:count signed quotes', $now['won_n'], ['count' => $now['won_n']])],
            ['label' => __('Conversion rate'), 'value' => $now['rate'], 'suffix' => ' %',
                'delta' => $now['rate'] !== null && $before['rate'] !== null ? $now['rate'] - $before['rate'] : null, 'unit' => ' pts',
                'note' => $rateTarget !== null ? __('target :v %', ['v' => rtrim(rtrim(number_format($rateTarget, 1, ',', ''), '0'), ',')]) : __('won ÷ (won + lost)')],
            ['label' => __('Quotes sent'), 'value' => $now['sent'],
                'delta' => $delta($now['sent'], $before['sent']), 'unit' => '%',
                'note' => trans_choice('{0}vs. no quote|{1}vs. :count quote|[2,*]vs. :count quotes', $before['sent'], ['count' => $before['sent']])],
        ];

        // Per month, the four series of the pipeline chart.
        $series = [];
        for ($m = $since->copy(); $m <= now(); $m = $m->copy()->addMonthNoOverflow()) {
            $series[$m->format('Y-m')] = ['label' => $m->translatedFormat('M'), 'quoted' => 0, 'won' => 0, 'count' => 0, 'gross' => 0, 'net' => 0];
        }
        foreach ($rows as $r) {
            $k = $r['date']->format('Y-m');
            if (! isset($series[$k])) continue;
            $series[$k]['quoted'] += $r['value_ht'];
            $series[$k]['count']++;
            $series[$k]['gross'] += $r['gross_ht'];
            $series[$k]['net'] += $r['value_ht'];
            if ($r['status'] === Quote::STATUS_WON) $series[$k]['won'] += $r['value_ht'];
        }
        $series = array_values(array_map(fn ($s) => [
            'label' => $s['label'], 'quoted' => round($s['quoted']), 'won' => round($s['won']), 'count' => $s['count'],
            'discount' => $s['gross'] > 0 ? round((1 - $s['net'] / $s['gross']) * 100, 1) : 0,
        ], $series));
        $avgDiscount = fn (Collection $c) => ($g = $c->sum('gross_ht')) > 0 ? round((1 - $c->sum('value_ht') / $g) * 100, 1) : 0;
        $chartTotals = [
            'quoted'   => [$now['quoted'], $delta($now['quoted'], $before['quoted'])],
            'won'      => [$now['won'], $delta($now['won'], $before['won'])],
            'count'    => [$rows->count(), $delta($rows->count(), $prev->count())],
            'discount' => [$avgDiscount($rows), null],
        ];

        $statuses = collect([Quote::STATUS_SENT, Quote::STATUS_WON, Quote::STATUS_DRAFT, Quote::STATUS_LOST])
            ->mapWithKeys(fn ($s) => [$s => $rows->where('status', $s)->count()]);

        // Funnel: made → sent to the client → in negotiation (sent, with a discount) → signed.
        $created = $rows->count();
        $funnel = [
            ['key' => 'created', 'n' => $created],
            ['key' => 'sent', 'n' => $now['sent']],
            ['key' => 'nego', 'n' => $rows->where('status', Quote::STATUS_SENT)->filter(fn ($r) => $r['discount_pct'] > 0)->count()],
            ['key' => 'won', 'n' => $now['won_n']],
        ];

        // Best dealerships of the period.
        $accesses = BrandAccess::where('brand_account_id', (string) $account->_id)->get();
        $levels = $accesses->keyBy(fn ($a) => (string) $a->company_id);
        $grouped = $rows->groupBy('company_id');
        $companies = Company::whereIn('_id', $grouped->keys()->filter()->values()->all())->get()->keyBy(fn ($c) => (string) $c->_id);
        $topDealers = $grouped->map(fn ($g, $cid) => [
            'name' => $g->first()['dealer'], 'quotes' => $g->count(), 'quoted' => $g->sum('value_ht'),
            'city' => $this->city($companies[$cid]->address ?? null),
            'level' => $account->levelName($levels[$cid]->level ?? null),
        ])->sortByDesc('quotes')->take(3)->values();

        // Getting started: what a new brand still has to do before stats appear.
        $connected = $accesses->where('status', BrandAccess::STATUS_ACTIVE)->count();
        $items = $account->isEngine() ? BrandEngine::count() : BrandModel::count();
        $levelsSet = $account->isExactPricing() || collect($account->levelsResolved())->contains(fn ($l) => array_sum($l['pct']) > 0);
        $steps = [
            ['done' => $items > 0, 'icon' => $account->isEngine() ? 'fan' : 'sailboat',
                'title' => __('Add your catalogue'), 'text' => __('By hand or from your Excel price list.'),
                'href' => route($account->isEngine() ? 'brand.engines.index' : 'brand.catalogue.index')],
            ['done' => $levelsSet, 'icon' => 'sliders',
                'title' => __('Set your price levels'), 'text' => __('The discount each level of dealer gets.'),
                'href' => route('brand.levels.edit')],
            ['done' => $accesses->isNotEmpty(), 'icon' => 'user-plus',
                'title' => __('Invite your dealers'), 'text' => __('They receive your catalogue at their level.'),
                'href' => route('brand.dealers.index')],
            ['done' => $connected > 0, 'icon' => 'handshake',
                'title' => __('First dealer connected'), 'text' => __('Their quotes start appearing here.'),
                'href' => route('brand.dealers.index', ['tab' => 'waiting'])],
        ];

        return view('brand.dashboard', [
            'account' => $account, 'period' => $period, 'brandId' => $brandId, 'dealerId' => $dealerId,
            'brands' => ManagedBrand::orderBy('position')->orderBy('name')->get(),
            'dealerOptions' => $this->dealerOptions($accesses),
            'kpis' => $kpis, 'series' => $series, 'chartTotals' => $chartTotals,
            'monthlyTarget' => $monthlyTarget, 'rateTarget' => $rateTarget,
            'periodTarget' => $monthlyTarget * $months, 'periodQuoted' => $now['quoted'],
            'statuses' => $statuses, 'funnel' => $funnel, 'topDealers' => $topDealers,
            'steps' => $steps, 'connected' => $connected,
            'latest' => $rows->take(6),
        ]);
    }

    /** The brand's own targets: a monthly amount to quote and a conversion rate. */
    public function targets(Request $request)
    {
        $data = $request->validate([
            'monthly_quoted' => 'nullable|numeric|min:0|max:1000000000',
            'win_rate'       => 'nullable|numeric|min:0|max:100',
        ]);
        auth()->user()->brandAccount->update(['targets' => [
            'monthly_quoted' => isset($data['monthly_quoted']) && $data['monthly_quoted'] !== '' ? (float) $data['monthly_quoted'] : null,
            'win_rate'       => isset($data['win_rate']) && $data['win_rate'] !== '' ? (float) $data['win_rate'] : null,
        ]]);

        return back()->with('status', __('Targets saved.'));
    }

    /** The quotes behind the dashboard, as an Excel file (brand-safe columns only). */
    public function export(Request $request)
    {
        $account = auth()->user()->brandAccount;
        [$period, $brandId, $dealerId, $since] = $this->filters($request);
        $rows = $this->rows(new BrandQuotePresenter($account), $since, $brandId, $dealerId);
        $status = ['draft' => __('Draft'), 'sent' => __('Sent'), 'won' => __('Won'), 'lost' => __('Lost')];

        $path = Xlsx::write(['DEVIS' => [
            'headers' => ['DATE', 'DEVIS', 'CONCESSION', 'VENDEUR', 'MODELE', 'VERSION', 'MOTEURS', 'CLIENT', 'REMISE %', 'MONTANT HT (VOS PRODUITS)', 'TOTAL DEVIS HT', 'STATUT'],
            'rows' => $rows->map(fn ($r) => [
                $r['date']?->format('d/m/Y'), $r['number'], $r['dealer'], $r['salesperson'], $r['boat'], $r['version'],
                implode(', ', $r['fitted_engines']), $r['client'], (float) $r['discount_pct'], (float) $r['value_ht'], (float) $r['total_ht'],
                $status[$r['status']] ?? $r['status'],
            ])->all(),
            'widths' => [11, 14, 26, 20, 26, 22, 30, 18, 10, 18, 16, 12],
        ]], 'nautiqs-brand-');

        return response()->download($path, 'nautiqs-devis-' . Str::slug($account->name) . '-' . now()->format('Y-m-d') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /* ------------------------------------------------------------ helpers */

    private function filters(Request $request): array
    {
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : '6m';

        return [
            $period,
            (string) $request->query('brand', ''),
            (string) $request->query('dealer', ''),
            now()->startOfMonth()->subMonthsNoOverflow(self::PERIODS[$period]),
        ];
    }

    private function rows(BrandQuotePresenter $presenter, Carbon $since, string $brandId, string $dealerId): Collection
    {
        return $presenter->query()->where('created_at', '>=', $since)->orderBy('created_at', 'desc')->get()
            ->map(fn (Quote $q) => $presenter->summary($q))
            ->when($brandId !== '', fn ($c) => $c->where('managed_brand_id', $brandId))
            ->when($dealerId !== '', fn ($c) => $c->where('company_id', $dealerId))
            ->values();
    }

    private function dealerOptions(Collection $accesses): Collection
    {
        $ids = $accesses->where('status', BrandAccess::STATUS_ACTIVE)->pluck('company_id')->filter()->map(fn ($i) => (string) $i)->unique()->values()->all();

        return Company::whereIn('_id', $ids)->orderBy('name')->get()->mapWithKeys(fn ($c) => [(string) $c->_id => $c->name]);
    }

    /** "12 quai de la Marine\n62500 Saint-Omer" → "Saint-Omer". */
    private function city(?string $address): ?string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', (string) $address))));
        if (! $lines) return null;
        $last = preg_replace('/^\d{4,5}\s*/', '', end($lines));

        return $last !== '' ? $last : null;
    }

    private function eur(float $v): string
    {
        return number_format($v, 0, ',', ' ') . ' €';
    }
}
