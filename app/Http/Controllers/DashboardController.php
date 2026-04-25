<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Quote;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Dashboard KPIs per spec §16.3. Every query is transparently filtered by
 * the authenticated user's company_id via the TenantScope global scope.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        // §16.3 Quotes today
        $quotesTodayCount = Quote::whereBetween('created_at', [$today, $today->copy()->endOfDay()])->count();

        // Won this month
        $wonThisMonth = Quote::where('status', Quote::STATUS_WON)
            ->where('won_at', '>=', $startOfMonth)
            ->count();

        // Conversion rate (won ÷ sent+won+lost, all closed-or-received quotes)
        $totalOut = Quote::whereIn('status', [Quote::STATUS_SENT, Quote::STATUS_WON, Quote::STATUS_LOST])->count();
        $wonCount = Quote::where('status', Quote::STATUS_WON)->count();
        $conversionRate = $totalOut > 0 ? round(($wonCount / $totalOut) * 100, 1) : 0;

        // Active clients = clients who've had at least one quote in the last 90 days
        $ninetyDaysAgo = Carbon::now()->subDays(90);
        $activeClientIds = Quote::where('created_at', '>=', $ninetyDaysAgo)
            ->pluck('client_id')
            ->unique()
            ->filter();
        $activeClientsCount = $activeClientIds->count();

        // §16.3 Quotes by status bar chart
        $statusCounts = [
            Quote::STATUS_DRAFT => Quote::where('status', Quote::STATUS_DRAFT)->count(),
            Quote::STATUS_SENT  => Quote::where('status', Quote::STATUS_SENT)->count(),
            Quote::STATUS_WON   => Quote::where('status', Quote::STATUS_WON)->count(),
            Quote::STATUS_LOST  => Quote::where('status', Quote::STATUS_LOST)->count(),
        ];

        // §16.3 Most quoted models (top 5 this month)
        $mostQuoted = Quote::where('created_at', '>=', $startOfMonth)
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

        // Recent quotes (last 5)
        $recent = Quote::orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['number', 'client_snapshot', 'model_snapshot', 'totals', 'status', 'created_at']);

        // 30-day quote volume for the line chart (created per day).
        $volume = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $volume[] = [
                'label' => $day->format('M j'),
                'count' => Quote::whereBetween('created_at', [$day, $day->copy()->endOfDay()])->count(),
            ];
        }

        // Average quote value (last 30 days) — replaces the removed Total MTD card
        $avgValue = Quote::where('created_at', '>=', Carbon::now()->subDays(30))
            ->get(['totals'])
            ->avg(fn ($q) => (float) ($q->totals['total_ttc'] ?? 0));
        $avgValue = $avgValue ? round($avgValue) : 0;

        $totalQuotes = Quote::count();
        $hasAnyVolume = collect($volume)->sum('count') > 0;
        $hasAnyStatus = array_sum($statusCounts) > 0;

        return view('dashboard', [
            'quotesTodayCount'   => $quotesTodayCount,
            'wonThisMonth'       => $wonThisMonth,
            'conversionRate'     => $conversionRate,
            'activeClientsCount' => $activeClientsCount,
            'statusCounts'       => $statusCounts,
            'mostQuoted'         => $mostQuoted,
            'recent'             => $recent,
            'volume'             => $volume,
            'avgValue'           => $avgValue,
            'totalQuotes'        => $totalQuotes,
            'hasAnyVolume'       => $hasAnyVolume,
            'hasAnyStatus'       => $hasAnyStatus,
        ]);
    }
}
