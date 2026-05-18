<x-admin-layout :title="__('Platform overview')" :header="__('Platform overview')">

    {{-- KPI tiles --}}
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        {{-- Quotes this month --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> {{ __('Quotes this month') }}
                </span>
                <i class="ri-file-list-3-line text-gray-300 text-lg"></i>
            </div>
            <div class="mt-3 text-3xl font-bold text-gray-900">{{ number_format($kpis['quotes_this_month'], 0, ',', ' ') }}</div>
            <div class="text-xs mt-1.5
                {{ $kpis['quotes_delta'] > 0 ? 'text-emerald-600' : ($kpis['quotes_delta'] < 0 ? 'text-red-500' : 'text-gray-500') }}">
                @if ($kpis['quotes_delta'] !== 0)
                    <i class="ri-arrow-{{ $kpis['quotes_delta'] > 0 ? 'up' : 'down' }}-line"></i>
                    {{ $kpis['quotes_delta'] > 0 ? '+' : '' }}{{ $kpis['quotes_delta'] }} {{ __('vs last month') }}
                @else
                    <span class="text-gray-400">{{ __('Same as last month') }}</span>
                @endif
            </div>
        </div>

        {{-- Total quoted --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> {{ __('Total quoted (excl. VAT)') }}
                </span>
                <i class="ri-money-euro-circle-line text-gray-300 text-lg"></i>
            </div>
            <div class="mt-3 text-3xl font-bold text-gray-900">€{{ number_format($kpis['total_quoted'] / 1000, 0, ',', ' ') }}k</div>
            <div class="text-xs mt-1.5
                {{ ($kpis['total_quoted_delta'] ?? 0) > 0 ? 'text-emerald-600' : (($kpis['total_quoted_delta'] ?? 0) < 0 ? 'text-red-500' : 'text-gray-500') }}">
                @if ($kpis['total_quoted_delta'] !== null && $kpis['total_quoted_delta'] != 0)
                    <i class="ri-arrow-{{ $kpis['total_quoted_delta'] > 0 ? 'up' : 'down' }}-line"></i>
                    {{ $kpis['total_quoted_delta'] > 0 ? '+' : '' }}{{ $kpis['total_quoted_delta'] }}% {{ __('vs last month') }}
                @else
                    <span class="text-gray-400">—</span>
                @endif
            </div>
        </div>

        {{-- Revenue won --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> {{ __('Revenue won this month') }}
                </span>
                <i class="ri-trophy-line text-gray-300 text-lg"></i>
            </div>
            <div class="mt-3 text-3xl font-bold text-gray-900">€{{ number_format($kpis['revenue_won'] / 1000, 0, ',', ' ') }}k</div>
            <div class="text-xs mt-1.5
                {{ ($kpis['revenue_won_delta'] ?? 0) > 0 ? 'text-emerald-600' : (($kpis['revenue_won_delta'] ?? 0) < 0 ? 'text-red-500' : 'text-gray-500') }}">
                @if ($kpis['revenue_won_delta'] !== null && $kpis['revenue_won_delta'] != 0)
                    <i class="ri-arrow-{{ $kpis['revenue_won_delta'] > 0 ? 'up' : 'down' }}-line"></i>
                    {{ $kpis['revenue_won_delta'] > 0 ? '+' : '' }}{{ $kpis['revenue_won_delta'] }}% {{ __('vs last month') }}
                @else
                    <span class="text-gray-400">—</span>
                @endif
            </div>
        </div>

        {{-- Active dealerships --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span> {{ __('Active dealers') }}
                </span>
                <i class="ri-store-2-line text-gray-300 text-lg"></i>
            </div>
            <div class="mt-3 text-3xl font-bold text-gray-900">{{ $kpis['dealers_active'] }}</div>
            <div class="text-xs mt-1.5 text-gray-500">
                @if ($kpis['new_dealers'] > 0)
                    <span class="text-emerald-600">
                        <i class="ri-arrow-up-line"></i> +{{ $kpis['new_dealers'] }} {{ __('new this month') }}
                    </span>
                @else
                    {{ $kpis['dealers_total'] }} {{ __('total') }} · {{ $kpis['tenant_users'] }} {{ __('users') }}
                @endif
            </div>
        </div>
    </section>

    {{-- Pipeline trend chart --}}
    <section class="bg-white rounded-2xl border border-gray-200 p-5 mb-4">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="font-semibold text-gray-900">{{ __('Pipeline trend') }}</h3>
                <p class="text-sm text-gray-500">{{ __('Last 6 months · sent vs won vs lost (all dealers)') }}</p>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> {{ __('Sent') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> {{ __('Won') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> {{ __('Lost') }}</span>
            </div>
        </div>
        <div class="h-64">
            <canvas id="adminPipelineTrendChart"></canvas>
        </div>
    </section>

    @push('scripts')
        <script>
            const adminTrend = @json($trend);
            @php
                $adminTrendLabels = [
                    'sent' => __('Sent'),
                    'won'  => __('Won'),
                    'lost' => __('Lost'),
                ];
            @endphp
            const adminTrendLabels = @json($adminTrendLabels);

            document.addEventListener('DOMContentLoaded', () => {
                const ctx = document.getElementById('adminPipelineTrendChart');
                if (!ctx || !window.Chart) return;

                Chart.defaults.font.family = "'Geist', sans-serif";
                Chart.defaults.color = '#64748b';

                const grad = (color) => {
                    const g = ctx.getContext('2d').createLinearGradient(0, 0, 0, 250);
                    g.addColorStop(0, color + '55');
                    g.addColorStop(1, color + '00');
                    return g;
                };

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: adminTrend.map(d => d.label),
                        datasets: [
                            { label: adminTrendLabels.sent, data: adminTrend.map(d => d.sent),
                              borderColor: '#3b82f6', backgroundColor: grad('#3b82f6'),
                              fill: true, tension: 0.35, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 5 },
                            { label: adminTrendLabels.won, data: adminTrend.map(d => d.won),
                              borderColor: '#10b981', backgroundColor: grad('#10b981'),
                              fill: true, tension: 0.35, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 5 },
                            { label: adminTrendLabels.lost, data: adminTrend.map(d => d.lost),
                              borderColor: '#ef4444', backgroundColor: grad('#ef4444'),
                              fill: true, tension: 0.35, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 5 },
                        ],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8, displayColors: true },
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                            y: { beginAtZero: true, grid: { color: 'rgba(15,23,42,0.06)' }, ticks: { font: { size: 11 }, stepSize: 1, precision: 0 } },
                        },
                    },
                });
            });
        </script>
    @endpush

    {{-- Main grid: recent quotes (left) + side rail (right) --}}
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4 items-start">
        {{-- Recent quotes (cross-tenant) --}}
        <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900">{{ __('Recent quotes across all dealers') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('Latest activity from every tenant') }}</p>
                </div>
            </div>
            @if ($recent->isEmpty())
                <div class="px-5 py-12 text-center">
                    <i class="ri-file-list-3-line text-4xl text-gray-300"></i>
                    <p class="text-sm text-gray-600 mt-2">{{ __('No quotes yet across the platform.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                            <tr>
                                <th class="px-5 py-3 font-semibold">{{ __('Dealer') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('Client') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('Ref.') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('Boat') }}</th>
                                <th class="px-5 py-3 font-semibold text-right">{{ __('Amount excl. VAT') }}</th>
                                <th class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($recent as $q)
                                @php
                                    $clientName = trim(($q->client_snapshot['first_name'] ?? '') . ' ' . ($q->client_snapshot['last_name'] ?? '')) ?: __('Guest');
                                @endphp
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-5 py-3">
                                        <div class="text-sm font-medium text-gray-900 truncate max-w-[180px]">{{ $q->company_name }}</div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="text-sm text-gray-900">{{ $clientName }}</div>
                                        <div class="text-[11px] text-gray-500 truncate max-w-[200px]">{{ $q->client_snapshot['email'] ?? '' }}</div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="font-mono text-xs px-2 py-1 rounded bg-gray-100 text-gray-700">{{ $q->number }}</span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="text-sm text-gray-900">{{ $q->model_snapshot['name'] ?? '—' }}</div>
                                        <div class="text-[11px] text-gray-500">{{ $q->model_snapshot['brand'] ?? '' }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-right font-semibold text-gray-900">
                                        €{{ number_format($q->totals['total_ht'] ?? 0, 0, ',', ' ') }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-app.status-pill :status="$q->status ?? 'draft'" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Right rail: top models + top brands --}}
        <div class="space-y-4">
            {{-- Top quoted models --}}
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">{{ __('Top quoted models') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('This month') }}</p>
                </div>
                @if ($topModels->isEmpty())
                    <div class="px-5 py-6 text-center text-sm text-gray-500">
                        {{ __('No quotes yet this month.') }}
                    </div>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($topModels as $m)
                            <li class="px-5 py-3">
                                <div class="flex items-center justify-between gap-3 mb-1">
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-gray-900 truncate">{{ $m['name'] }}</div>
                                        <div class="text-[11px] text-gray-500 truncate">{{ $m['brand'] }}</div>
                                    </div>
                                    <span class="text-xs font-bold text-primary-800 shrink-0">{{ $m['count'] }}</span>
                                </div>
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-primary-800 rounded-full"
                                        style="width: {{ round(($m['count'] / $topModelMax) * 100) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Most-activated brands --}}
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">{{ __('Most activated brands') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('Across all active dealers') }}</p>
                </div>
                @if ($brandCounts->isEmpty())
                    <div class="px-5 py-6 text-center text-sm text-gray-500">
                        {{ __('No brand activations yet.') }}
                    </div>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($brandCounts as $brand => $count)
                            <li class="px-5 py-3 flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-900 truncate">{{ $brand }}</span>
                                <span class="text-xs font-semibold text-primary-800 bg-primary-50 px-2 py-0.5 rounded-full">
                                    {{ $count }} {{ \Illuminate\Support\Str::plural(__('dealer'), $count) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>

    {{-- Recent platform activity --}}
    <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-900">{{ __('Recent platform activity') }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('Audit trail of superadmin actions') }}</p>
            </div>
            <a href="{{ route('admin.audit.index') }}" class="text-xs font-medium text-primary-800 hover:underline">{{ __('View all') }}</a>
        </div>
        @if ($recentAudit->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-gray-500">
                <i class="ri-history-line text-2xl text-gray-300"></i>
                <p class="mt-2">{{ __('No activity yet.') }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ __('Actions you take in the platform area will appear here.') }}</p>
            </div>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($recentAudit as $row)
                    <li class="px-5 py-3 flex items-start gap-3">
                        <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                            <i class="ri-history-line"></i>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-gray-900">
                                <span class="font-medium">{{ $row->actor_email ?? __('Unknown') }}</span>
                                <span class="text-gray-500">·</span>
                                <span class="font-mono text-xs">{{ $row->action }}</span>
                                @if ($row->target_label)
                                    <span class="text-gray-500">·</span>
                                    <span class="text-gray-700">{{ $row->target_label }}</span>
                                @endif
                            </div>
                            <div class="text-[11px] text-gray-400 mt-0.5">{{ $row->created_at?->diffForHumans() }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-admin-layout>
