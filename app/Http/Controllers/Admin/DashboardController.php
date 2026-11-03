<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountRequest;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Spec §4.4 — superadmin platform overview, in the same Figma style as the
 * dealer and brand dashboards. Every figure is a roll-up across all tenants
 * for the chosen period, compared with the period just before.
 *
 * TenantScope auto-disables for the superadmin role, so queries are unscoped.
 * Client names stay masked: a dealer's clients are the dealer's.
 */
class DashboardController extends Controller
{
    public const PERIODS = ['month' => 0, '6m' => 5, 'year' => 11];

    public function index(Request $request)
    {
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : '6m';
        $now = Carbon::now();
        $since = $now->copy()->startOfMonth()->subMonthsNoOverflow(self::PERIODS[$period]);
        $prevSince = $since->copy()->subMonthsNoOverflow(self::PERIODS[$period] + 1);

        $fields = ['number', 'company_id', 'status', 'created_at', 'won_at', 'totals', 'model_snapshot'];
        $created = Quote::where('created_at', '>=', $prevSince)->get($fields);
        $won = Quote::where('status', Quote::STATUS_WON)->where('won_at', '>=', $prevSince)->get($fields);

        $cur = fn (Collection $c, string $f) => $c->filter(fn ($q) => $q->{$f} && $q->{$f} >= $since);
        $prev = fn (Collection $c, string $f) => $c->filter(fn ($q) => $q->{$f} && $q->{$f} < $since);
        $ht = fn (Collection $c) => (float) $c->sum(fn ($q) => (float) ($q->totals['total_ht'] ?? 0));
        $delta = fn ($a, $b) => $b > 0 ? round(($a - $b) / $b * 100, 1) : null;

        $rows = $cur($created, 'created_at');
        $before = $prev($created, 'created_at');
        $wonNow = $cur($won, 'won_at');

        $newDealers = Company::where('created_at', '>=', $since)->count();
        $newDealersPrev = Company::where('created_at', '>=', $prevSince)->where('created_at', '<', $since)->count();

        $kpis = [
            'quotes'       => $rows->count(),
            'quotes_prev'  => $before->count(),
            'quotes_delta' => $delta($rows->count(), $before->count()),
            'quoted'       => $ht($rows),
            'quoted_prev'  => $ht($before),
            'quoted_delta' => $delta($ht($rows), $ht($before)),
            'won'          => $ht($wonNow),
            'won_count'    => $wonNow->count(),
            'won_delta'    => $delta($ht($wonNow), $ht($prev($won, 'won_at'))),
            'dealers_active' => Company::where('status', '!=', 'suspended')->count(),
            'dealers_total'  => Company::count(),
            'new_dealers'    => $newDealers,
            'new_dealers_delta' => $delta($newDealers, $newDealersPrev),
        ];

        // Quotes created per month across the period (single bar chart).
        $trend = [];
        for ($m = $since->copy(); $m <= $now; $m = $m->copy()->addMonthNoOverflow()) {
            $trend[$m->format('Y-m')] = ['label' => $m->translatedFormat('M'), 'count' => 0];
        }
        foreach ($rows as $q) {
            $k = $q->created_at->format('Y-m');
            if (isset($trend[$k])) $trend[$k]['count']++;
        }
        $trend = array_values($trend);

        $statuses = [];
        foreach ([Quote::STATUS_SENT, Quote::STATUS_WON, Quote::STATUS_DRAFT, Quote::STATUS_LOST] as $s) {
            $statuses[$s] = $rows->where('status', $s)->count();
        }

        // Most active dealerships and most quoted models of the period.
        $byCompany = $rows->groupBy(fn ($q) => (string) $q->company_id);
        $names = Company::whereIn('_id', $byCompany->keys()->filter()->values()->all())->get(['name'])
            ->mapWithKeys(fn ($c) => [(string) $c->_id => $c->name]);
        $topDealers = $byCompany->map(fn ($g, $cid) => [
            'name' => $names[$cid] ?? '—', 'quotes' => $g->count(), 'quoted' => $ht($g),
        ])->sortByDesc('quotes')->take(5)->values();

        $topModels = $rows
            ->groupBy(fn ($q) => ($q->model_snapshot['brand'] ?? '—') . ' · ' . ($q->model_snapshot['name'] ?? '—'))
            ->map(fn ($g) => [
                'name'  => $g->first()->model_snapshot['name']  ?? '—',
                'brand' => $g->first()->model_snapshot['brand'] ?? '—',
                'count' => $g->count(),
            ])->sortByDesc('count')->take(5)->values();

        // Recent quotes across all tenants (with company name).
        $recent = Quote::orderBy('created_at', 'desc')->limit(6)
            ->get(['number', 'company_id', 'client_snapshot', 'model_snapshot', 'totals', 'status', 'created_at']);
        $recentNames = Company::whereIn('_id', $recent->pluck('company_id')->unique()->filter()->values()->all())->get(['name'])
            ->mapWithKeys(fn ($c) => [(string) $c->_id => $c->name]);
        $recent->each(fn ($q) => $q->company_name = $recentNames[(string) $q->company_id] ?? '—');

        return view('admin.dashboard', [
            'period' => $period,
            'kpis' => $kpis, 'trend' => $trend, 'statuses' => $statuses,
            'topDealers' => $topDealers, 'topModels' => $topModels, 'recent' => $recent,
            'recentAudit' => AuditLog::orderBy('created_at', 'desc')->take(5)->get(),
            'pendingRequests' => AccountRequest::where('status', AccountRequest::STATUS_PENDING)->count(),
        ]);
    }
}
