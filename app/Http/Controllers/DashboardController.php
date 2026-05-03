<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Dashboard KPIs aligned with the client reference mockup
 * (nautiqs_dashboard_EN.html).
 *
 * Every query is filtered by company_id via TenantScope.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $now            = Carbon::now();
        $startOfMonth   = $now->copy()->startOfMonth();
        $startLastMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $endLastMonth   = $now->copy()->subMonthNoOverflow()->endOfMonth();

        // ── KPI 1: Quotes this month ───────────────────────────────────
        $quotesThisMonth = Quote::where('created_at', '>=', $startOfMonth)->count();
        $quotesLastMonth = Quote::whereBetween('created_at', [$startLastMonth, $endLastMonth])->count();
        $quotesDelta     = $quotesThisMonth - $quotesLastMonth;

        // ── KPI 2: Total quoted (excl. VAT) this month ──────────────────
        $totalQuotedThisMonth = (float) Quote::where('created_at', '>=', $startOfMonth)
            ->get(['totals'])
            ->sum(fn ($q) => (float) ($q->totals['total_ht'] ?? 0));
        $totalQuotedLastMonth = (float) Quote::whereBetween('created_at', [$startLastMonth, $endLastMonth])
            ->get(['totals'])
            ->sum(fn ($q) => (float) ($q->totals['total_ht'] ?? 0));
        $totalQuotedDeltaPct = $totalQuotedLastMonth > 0
            ? round((($totalQuotedThisMonth - $totalQuotedLastMonth) / $totalQuotedLastMonth) * 100, 1)
            : null;

        // ── KPI 3: Revenue won this month ──────────────────────────────
        $revenueWonThisMonth = (float) Quote::where('status', Quote::STATUS_WON)
            ->where('won_at', '>=', $startOfMonth)
            ->get(['totals'])
            ->sum(fn ($q) => (float) ($q->totals['total_ht'] ?? 0));
        $revenueWonLastMonth = (float) Quote::where('status', Quote::STATUS_WON)
            ->whereBetween('won_at', [$startLastMonth, $endLastMonth])
            ->get(['totals'])
            ->sum(fn ($q) => (float) ($q->totals['total_ht'] ?? 0));
        $revenueWonDeltaPct = $revenueWonLastMonth > 0
            ? round((($revenueWonThisMonth - $revenueWonLastMonth) / $revenueWonLastMonth) * 100, 1)
            : null;

        // ── KPI 4: Awaiting response (sent quotes not yet won/lost) ────
        $awaitingResponse = Quote::where('status', Quote::STATUS_SENT)->count();
        $expiredThisWeek  = Quote::where('status', Quote::STATUS_SENT)
            ->where('expires_at', '<', $now)
            ->where('expires_at', '>=', $now->copy()->subWeek())
            ->count();

        // ── Top quoted models (this month) ─────────────────────────────
        $topModels = Quote::where('created_at', '>=', $startOfMonth)
            ->get(['model_snapshot'])
            ->groupBy(fn ($q) => $q->model_snapshot['code'] ?? 'UNKNOWN')
            ->map(fn ($group) => [
                'code'  => $group->first()->model_snapshot['code']  ?? '—',
                'name'  => $group->first()->model_snapshot['name']  ?? '—',
                'brand' => $group->first()->model_snapshot['brand'] ?? '—',
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values();
        $topModelMax = $topModels->max('count') ?: 1;

        // ── Conversion rate (this month) ───────────────────────────────
        $sentThisMonth = Quote::where('sent_at', '>=', $startOfMonth)->count();
        $wonThisMonth  = Quote::where('status', Quote::STATUS_WON)
            ->where('won_at', '>=', $startOfMonth)
            ->count();
        $lostThisMonth = Quote::where('status', Quote::STATUS_LOST)
            ->where('lost_at', '>=', $startOfMonth)
            ->count();
        $closedThisMonth   = $wonThisMonth + $lostThisMonth;
        $conversionRate    = $closedThisMonth > 0 ? round(($wonThisMonth / $closedThisMonth) * 100) : 0;

        // Avg time to close (sent → won, this month)
        $closedQuotes = Quote::where('status', Quote::STATUS_WON)
            ->where('won_at', '>=', $startOfMonth)
            ->whereNotNull('sent_at')
            ->get(['sent_at', 'won_at']);
        $avgDaysToClose = $closedQuotes->isEmpty() ? null : round($closedQuotes->avg(
            fn ($q) => $q->sent_at->diffInDays($q->won_at)
        ), 1);

        // ── Pipeline trend (last 6 months: sent / won / lost per month) ─
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonthsNoOverflow($i)->startOfMonth();
            $monthEnd   = $monthStart->copy()->endOfMonth();
            $trend[] = [
                'label' => $monthStart->format('M y'),
                'sent'  => Quote::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                'won'   => Quote::where('status', Quote::STATUS_WON)->whereBetween('won_at', [$monthStart, $monthEnd])->count(),
                'lost'  => Quote::where('status', Quote::STATUS_LOST)->whereBetween('lost_at', [$monthStart, $monthEnd])->count(),
            ];
        }

        // ── Recent quotes (last 5) ─────────────────────────────────────
        $recent = Quote::orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['number', 'client_snapshot', 'model_snapshot', 'totals', 'status', 'tracking', 'sent_at', 'created_at']);

        // ── Pending catalogue updates (banner trigger) ─────────────────
        // For now, just a placeholder count — populated when the
        // catalogue notification module is built.
        $pendingUpdatesCount = 0;
        $pendingUpdatesBrand = null;

        return view('dashboard', [
            'quotesThisMonth'       => $quotesThisMonth,
            'quotesDelta'           => $quotesDelta,

            'totalQuotedThisMonth'  => $totalQuotedThisMonth,
            'totalQuotedDeltaPct'   => $totalQuotedDeltaPct,

            'revenueWonThisMonth'   => $revenueWonThisMonth,
            'revenueWonDeltaPct'    => $revenueWonDeltaPct,

            'awaitingResponse'      => $awaitingResponse,
            'expiredThisWeek'       => $expiredThisWeek,

            'topModels'             => $topModels,
            'topModelMax'           => $topModelMax,

            'conversionRate'        => $conversionRate,
            'sentThisMonth'         => $sentThisMonth,
            'wonThisMonth'          => $wonThisMonth,
            'lostThisMonth'         => $lostThisMonth,
            'avgDaysToClose'        => $avgDaysToClose,

            'recent'                => $recent,
            'trend'                 => $trend,

            'pendingUpdatesCount'   => $pendingUpdatesCount,
            'pendingUpdatesBrand'   => $pendingUpdatesBrand,
        ]);
    }
}
