<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyBrand;
use App\Models\Quote;
use App\Models\User;

/**
 * Spec §4.4 — superadmin dashboard. Platform-wide KPIs (cross-tenant) that
 * the regular tenant dashboard cannot show.
 *
 * All counts here are unscoped: the TenantScope auto-disables for the
 * superadmin role, so a bare Model::count() already returns global totals.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $monthStart = now()->startOfMonth();

        $kpis = [
            'tenants_active' => Company::where('status', '!=', 'suspended')->count(),
            'tenants_total'  => Company::count(),
            'quotes_month'   => Quote::where('created_at', '>=', $monthStart)->count(),
            'users_total'    => User::where('role', '!=', User::ROLE_SUPERADMIN)->count(),
        ];

        // Most-activated brands (top 5) — group CompanyBrand by name, count
        // distinct companies. Aggregation kept simple; for 100s of tenants
        // this is still <50ms on Mongo.
        $brandCounts = CompanyBrand::where('is_active', true)
            ->get(['name', 'company_id'])
            ->groupBy('name')
            ->map(fn ($rows) => $rows->pluck('company_id')->unique()->count())
            ->sortDesc()
            ->take(5);

        // Last 10 audit events for the recent-activity panel.
        $recentAudit = AuditLog::orderBy('created_at', 'desc')->take(10)->get();

        // Quote breakdown for the small status chart.
        $quoteStatusCounts = [
            'draft' => Quote::withTrashed()->where('status', Quote::STATUS_DRAFT)->count(),
            'sent'  => Quote::withTrashed()->where('status', Quote::STATUS_SENT)->count(),
            'won'   => Quote::withTrashed()->where('status', Quote::STATUS_WON)->count(),
            'lost'  => Quote::withTrashed()->where('status', Quote::STATUS_LOST)->count(),
        ];

        return view('admin.dashboard', compact('kpis', 'brandCounts', 'recentAudit', 'quoteStatusCounts'));
    }
}
