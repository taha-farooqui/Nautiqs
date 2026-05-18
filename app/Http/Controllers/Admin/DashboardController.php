<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyBrand;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Spec §4.4 — superadmin platform overview. Same visual language as the
 * tenant dashboard (KPI tiles with MoM deltas, pipeline-trend chart,
 * recent-quotes table, top quoted models) but unscoped: every figure is
 * a roll-up across all tenants.
 *
 * Counts use Model::count() directly because TenantScope auto-disables
 * for the superadmin role.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $now            = Carbon::now();
        $startOfMonth   = $now->copy()->startOfMonth();
        $startLastMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $endLastMonth   = $now->copy()->subMonthNoOverflow()->endOfMonth();

        // ── KPI 1: Quotes this month (vs last month delta) ─────────────
        $quotesThisMonth = Quote::where('created_at', '>=', $startOfMonth)->count();
        $quotesLastMonth = Quote::whereBetween('created_at', [$startLastMonth, $endLastMonth])->count();
        $quotesDelta     = $quotesThisMonth - $quotesLastMonth;

        // ── KPI 2: Total quoted excl. VAT this month ───────────────────
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

        // ── KPI 4: Active dealerships (vs total) ───────────────────────
        $dealersActive = Company::where('status', '!=', 'suspended')->count();
        $dealersTotal  = Company::count();
        $newDealersThisMonth = Company::where('created_at', '>=', $startOfMonth)->count();

        // ── Pipeline trend (last 6 months) ─────────────────────────────
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

        // ── Recent quotes across all tenants (with company name) ───────
        $recent = Quote::orderBy('created_at', 'desc')->limit(8)
            ->get(['number', 'company_id', 'client_snapshot', 'model_snapshot', 'totals', 'status', 'created_at']);
        $companyIds = $recent->pluck('company_id')->unique()->filter()->values();
        $companies = Company::whereIn('_id', $companyIds)->get(['name'])->keyBy('_id');
        $recent->each(function ($q) use ($companies) {
            $q->company_name = $companies->get((string) $q->company_id)?->name ?? '—';
        });

        // ── Top quoted models (this month) ─────────────────────────────
        $topModels = Quote::where('created_at', '>=', $startOfMonth)
            ->get(['model_snapshot'])
            ->groupBy(fn ($q) => ($q->model_snapshot['brand'] ?? '—') . ' · ' . ($q->model_snapshot['name'] ?? '—'))
            ->map(fn ($group) => [
                'name'  => $group->first()->model_snapshot['name']  ?? '—',
                'brand' => $group->first()->model_snapshot['brand'] ?? '—',
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values();
        $topModelMax = $topModels->max('count') ?: 1;

        // ── Most-activated brands (top 5 across tenants) ───────────────
        $brandCounts = CompanyBrand::where('is_active', true)
            ->get(['name', 'company_id'])
            ->groupBy('name')
            ->map(fn ($rows) => $rows->pluck('company_id')->unique()->count())
            ->sortDesc()
            ->take(5);

        // ── Recent platform activity (audit log) ───────────────────────
        $recentAudit = AuditLog::orderBy('created_at', 'desc')->take(8)->get();

        // ── Stats strip ───────────────────────────────────────────────
        $kpis = [
            'quotes_this_month'     => $quotesThisMonth,
            'quotes_delta'          => $quotesDelta,
            'total_quoted'          => $totalQuotedThisMonth,
            'total_quoted_delta'    => $totalQuotedDeltaPct,
            'revenue_won'           => $revenueWonThisMonth,
            'revenue_won_delta'     => $revenueWonDeltaPct,
            'dealers_active'        => $dealersActive,
            'dealers_total'         => $dealersTotal,
            'new_dealers'           => $newDealersThisMonth,
            'tenant_users'          => User::where('role', '!=', User::ROLE_SUPERADMIN)->count(),
        ];

        return view('admin.dashboard', compact(
            'kpis', 'trend', 'recent', 'topModels', 'topModelMax', 'brandCounts', 'recentAudit'
        ));
    }
}
