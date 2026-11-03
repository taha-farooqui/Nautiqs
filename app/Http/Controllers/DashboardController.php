<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\User;
use App\Services\Xlsx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Dealer dashboard. The toolbar filters (period, brand, salesperson) drive
 * every figure; each KPI is compared with the period just before.
 *
 * Every query is filtered by company_id via TenantScope.
 */
class DashboardController extends Controller
{
    /** Period key => months back from the current month (same as the brand portal). */
    public const PERIODS = ['month' => 0, '6m' => 5, 'year' => 11];

    private const FIELDS = ['number', 'status', 'created_at', 'won_at', 'lost_at', 'sent_at', 'expires_at',
        'totals', 'model_snapshot', 'client_snapshot', 'created_by_user_id'];

    public function __invoke(Request $request): View|RedirectResponse
    {
        // Superadmins don't belong to a tenant and shouldn't see this
        // tenant-scoped dashboard — bounce them to /admin overview.
        if (auth()->user()?->role === User::ROLE_SUPERADMIN) {
            return redirect()->route('admin.dashboard');
        }

        $f = $this->filters($request);

        // Cache the figures for 60 s per company and filter set so a refresh
        // doesn't re-hammer Atlas. Recent quotes are always fetched fresh.
        $companyId = auth()->user()->company_id ?? 'guest';
        $cacheKey  = 'dashboard:v4:' . $companyId . ':' . md5(json_encode([$f['period'], $f['brand'], $f['user']])) . ':' . now()->format('YmdHi');
        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, fn () => $this->buildData($f));

        $data['recent'] = $this->scoped(Quote::query(), $f)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['number', 'client_snapshot', 'model_snapshot', 'totals', 'status', 'tracking', 'sent_at', 'created_at']);

        // Toolbar options: brands quoted by this dealership, and its team.
        $data['brandOptions'] = Quote::query()->get(['model_snapshot'])
            ->pluck('model_snapshot.brand')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
        $data['userOptions'] = User::where('company_id', auth()->user()->company_id)
            ->whereIn('role', [User::ROLE_TENANT_ADMIN, User::ROLE_TENANT_USER])
            ->orderBy('name')->get(['name'])->mapWithKeys(fn ($u) => [(string) $u->_id => $u->name]);

        return view('dashboard', $data + ['period' => $f['period'], 'brandFilter' => $f['brand'], 'userFilter' => $f['user']]);
    }

    /** The quotes behind the dashboard, as an Excel file. */
    public function export(Request $request)
    {
        $f = $this->filters($request);
        $quotes = $this->scoped(Quote::query(), $f)->where('created_at', '>=', $f['since'])
            ->orderBy('created_at', 'desc')->get(self::FIELDS);
        $users = User::where('company_id', auth()->user()->company_id)->get(['name'])->mapWithKeys(fn ($u) => [(string) $u->_id => $u->name]);
        $status = ['draft' => __('Draft'), 'sent' => __('Sent'), 'won' => __('Won'), 'lost' => __('Lost')];

        $path = Xlsx::write(['DEVIS' => [
            'headers' => ['DATE', 'DEVIS', 'CLIENT', 'VENDEUR', 'MARQUE', 'MODELE', 'MONTANT HT', 'MONTANT TTC', 'STATUT'],
            'rows' => $quotes->map(fn ($q) => [
                $q->created_at?->format('d/m/Y'), $q->number,
                trim(($q->client_snapshot['first_name'] ?? '') . ' ' . ($q->client_snapshot['last_name'] ?? '')),
                $users[(string) $q->created_by_user_id] ?? '',
                $q->model_snapshot['brand'] ?? '', $q->model_snapshot['name'] ?? '',
                (float) ($q->totals['total_ht'] ?? 0), (float) ($q->totals['total_ttc'] ?? 0),
                $status[$q->status] ?? $q->status,
            ])->all(),
            'widths' => [11, 14, 24, 20, 18, 26, 16, 16, 12],
        ]], 'nautiqs-dashboard-');

        return response()->download($path, 'nautiqs-devis-' . now()->format('Y-m-d') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function buildData(array $f): array
    {
        $now = Carbon::now();
        $since = $f['since'];
        $months = self::PERIODS[$f['period']] + 1;
        $prevSince = $since->copy()->subMonthsNoOverflow($months);

        // One read for "created in the period or the one before", one for "won in it".
        $created = $this->scoped(Quote::query(), $f)->where('created_at', '>=', $prevSince)->get(self::FIELDS);
        $won = $this->scoped(Quote::query(), $f)->where('status', Quote::STATUS_WON)->where('won_at', '>=', $prevSince)->get(self::FIELDS);

        $inPeriod = fn (Collection $c, string $field) => $c->filter(fn ($q) => $q->{$field} && $q->{$field} >= $since);
        $inPrev = fn (Collection $c, string $field) => $c->filter(fn ($q) => $q->{$field} && $q->{$field} < $since && $q->{$field} >= $prevSince);
        $ht = fn (Collection $c) => (float) $c->sum(fn ($q) => (float) ($q->totals['total_ht'] ?? 0));
        $delta = fn ($a, $b) => $b > 0 ? round(($a - $b) / $b * 100, 1) : null;

        $rows = $inPeriod($created, 'created_at');
        $prev = $inPrev($created, 'created_at');

        $quotesCount = $rows->count();
        $quotesPrev = $prev->count();
        $quoted = $ht($rows);
        $quotedPrev = $ht($prev);
        $wonValue = $ht($inPeriod($won, 'won_at'));
        $wonPrev = $ht($inPrev($won, 'won_at'));
        $wonCount = $inPeriod($won, 'won_at')->count();

        // Awaiting a reply right now (not bound to the period).
        $awaitingResponse = $this->scoped(Quote::query(), $f)->where('status', Quote::STATUS_SENT)->count();
        $expiredThisWeek = $this->scoped(Quote::query(), $f)->where('status', Quote::STATUS_SENT)
            ->where('expires_at', '<', $now)->where('expires_at', '>=', $now->copy()->subWeek())->count();

        // Quotes created per month across the period (the single bar chart).
        $trend = [];
        for ($m = $since->copy(); $m <= $now; $m = $m->copy()->addMonthNoOverflow()) {
            $trend[$m->format('Y-m')] = ['label' => $m->translatedFormat('M'), 'sent' => 0];
        }
        foreach ($rows as $q) {
            $k = $q->created_at->format('Y-m');
            if (isset($trend[$k])) $trend[$k]['sent']++;
        }

        $statuses = [];
        foreach ([Quote::STATUS_SENT, Quote::STATUS_WON, Quote::STATUS_DRAFT, Quote::STATUS_LOST] as $s) {
            $statuses[$s] = $rows->where('status', $s)->count();
        }

        $topModels = $rows
            ->groupBy(fn ($q) => $q->model_snapshot['code'] ?? 'UNKNOWN')
            ->map(fn ($group) => [
                'code'  => $group->first()->model_snapshot['code']  ?? '—',
                'name'  => $group->first()->model_snapshot['name']  ?? '—',
                'brand' => $group->first()->model_snapshot['brand'] ?? '—',
                'count' => $group->count(),
            ])
            ->sortByDesc('count')->take(5)->values();

        return [
            'quotesCount'      => $quotesCount,
            'quotesPrev'       => $quotesPrev,
            'quotesDeltaPct'   => $delta($quotesCount, $quotesPrev),
            'quoted'           => $quoted,
            'quotedPrev'       => $quotedPrev,
            'quotedDeltaPct'   => $delta($quoted, $quotedPrev),
            'wonValue'         => $wonValue,
            'wonCount'         => $wonCount,
            'wonDeltaPct'      => $delta($wonValue, $wonPrev),
            'awaitingResponse' => $awaitingResponse,
            'expiredThisWeek'  => $expiredThisWeek,
            'trend'            => array_values($trend),
            'statuses'         => $statuses,
            'topModels'        => $topModels,
            // Pending catalogue updates (banner trigger) — placeholder until
            // the catalogue notification module feeds it.
            'pendingUpdatesCount' => 0,
            'pendingUpdatesBrand' => null,
        ];
    }

    private function filters(Request $request): array
    {
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : '6m';

        return [
            'period' => $period,
            'brand'  => (string) $request->query('brand', ''),
            'user'   => (string) $request->query('user', ''),
            'since'  => now()->startOfMonth()->subMonthsNoOverflow(self::PERIODS[$period]),
        ];
    }

    private function scoped($query, array $f)
    {
        return $query
            ->when($f['brand'] !== '', fn ($q) => $q->where('model_snapshot.brand', $f['brand']))
            ->when($f['user'] !== '', fn ($q) => $q->where('created_by_user_id', $f['user']));
    }
}
