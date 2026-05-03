@php
    if (! function_exists('nautiqs_format_short')) {
        function nautiqs_format_short(float $n): string {
            if ($n >= 1_000_000) return number_format($n / 1_000_000, 1, '.', '') . 'M';
            if ($n >= 1_000)     return number_format($n / 1_000, 0) . 'k';
            return number_format($n, 0);
        }
    }
@endphp

<x-app-layout title="Dashboard" :header="'Dashboard — ' . now()->format('l, F j Y')">

    {{-- Catalogue update banner (only when there are pending updates) --}}
    @if ($pendingUpdatesCount > 0)
        <section class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary-900 to-primary-700 text-white p-5 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-white/15 flex items-center justify-center">
                    <i class="ri-anchor-line text-xl"></i>
                </div>
                <div class="flex-1">
                    <p class="font-semibold">Catalogue update available{{ $pendingUpdatesBrand ? ' — ' . $pendingUpdatesBrand : '' }}</p>
                    <p class="text-sm text-white/80">
                        {{ $pendingUpdatesCount }} {{ $pendingUpdatesCount === 1 ? 'change' : 'changes' }} from the platform — review before applying.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-white font-medium text-sm hover:bg-white/20 transition">
                        Dismiss
                    </button>
                    <button class="px-4 py-2 rounded-lg bg-white text-primary-900 font-semibold text-sm hover:bg-white/90 transition">
                        Review &amp; apply
                    </button>
                </div>
            </div>
        </section>
    @endif

    {{-- KPI cards (4) --}}
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        {{-- 1. Quotes this month --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                <span class="text-sm text-gray-600">Quotes this month</span>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($quotesThisMonth) }}</p>
            <p class="text-xs mt-2 {{ $quotesDelta > 0 ? 'text-emerald-600' : ($quotesDelta < 0 ? 'text-red-600' : 'text-gray-500') }}">
                @if ($quotesDelta > 0)
                    <i class="ri-arrow-up-line"></i> +{{ $quotesDelta }} vs last month
                @elseif ($quotesDelta < 0)
                    <i class="ri-arrow-down-line"></i> {{ $quotesDelta }} vs last month
                @else
                    <i class="ri-subtract-line"></i> Same as last month
                @endif
            </p>
        </div>

        {{-- 2. Total quoted excl. VAT --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-sm text-gray-600">Total quoted (excl. VAT)</span>
            </div>
            <p class="text-3xl font-bold text-gray-900">€{{ nautiqs_format_short($totalQuotedThisMonth) }}</p>
            <p class="text-xs mt-2 {{ ($totalQuotedDeltaPct ?? 0) > 0 ? 'text-emerald-600' : (($totalQuotedDeltaPct ?? 0) < 0 ? 'text-red-600' : 'text-gray-500') }}">
                @if ($totalQuotedDeltaPct === null)
                    <i class="ri-subtract-line"></i> No data last month
                @elseif ($totalQuotedDeltaPct > 0)
                    <i class="ri-arrow-up-line"></i> +{{ $totalQuotedDeltaPct }}% vs last month
                @elseif ($totalQuotedDeltaPct < 0)
                    <i class="ri-arrow-down-line"></i> {{ $totalQuotedDeltaPct }}% vs last month
                @else
                    <i class="ri-subtract-line"></i> Same as last month
                @endif
            </p>
        </div>

        {{-- 3. Revenue won this month --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span class="text-sm text-gray-600">Revenue won this month</span>
            </div>
            <p class="text-3xl font-bold text-gray-900">€{{ nautiqs_format_short($revenueWonThisMonth) }}</p>
            <p class="text-xs mt-2 {{ ($revenueWonDeltaPct ?? 0) > 0 ? 'text-emerald-600' : (($revenueWonDeltaPct ?? 0) < 0 ? 'text-red-600' : 'text-gray-500') }}">
                @if ($revenueWonDeltaPct === null)
                    <i class="ri-subtract-line"></i> No data last month
                @elseif ($revenueWonDeltaPct > 0)
                    <i class="ri-arrow-up-line"></i> +{{ $revenueWonDeltaPct }}% vs last month
                @elseif ($revenueWonDeltaPct < 0)
                    <i class="ri-arrow-down-line"></i> {{ $revenueWonDeltaPct }}% vs last month
                @else
                    <i class="ri-subtract-line"></i> Same as last month
                @endif
            </p>
        </div>

        {{-- 4. Awaiting response --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                <span class="text-sm text-gray-600">Awaiting response</span>
            </div>
            <p class="text-3xl font-bold text-gray-900">{{ number_format($awaitingResponse) }}</p>
            <p class="text-xs mt-2 {{ $expiredThisWeek > 0 ? 'text-red-600' : 'text-gray-500' }}">
                @if ($expiredThisWeek > 0)
                    <i class="ri-arrow-down-line"></i> {{ $expiredThisWeek }} expired this week
                @else
                    <i class="ri-subtract-line"></i> None expired this week
                @endif
            </p>
        </div>
    </section>

    {{-- Pipeline trend --}}
    <section class="bg-white rounded-2xl border border-gray-200 p-5 mb-4">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="font-semibold text-gray-900">Pipeline trend</h3>
                <p class="text-sm text-gray-500">Last 6 months · sent vs won vs lost</p>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Sent</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Won</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> Lost</span>
            </div>
        </div>
        <div class="h-64">
            <canvas id="pipelineTrendChart"></canvas>
        </div>
    </section>

    @push('scripts')
        <script>
            const trend = @json($trend);

            document.addEventListener('DOMContentLoaded', () => {
                const ctx = document.getElementById('pipelineTrendChart');
                if (!ctx || !window.Chart) return;

                Chart.defaults.font.family = "'Inter', sans-serif";
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
                        labels: trend.map(d => d.label),
                        datasets: [
                            {
                                label: 'Sent',
                                data: trend.map(d => d.sent),
                                borderColor: '#3b82f6',
                                backgroundColor: grad('#3b82f6'),
                                fill: true, tension: 0.35, borderWidth: 2.5,
                                pointBackgroundColor: '#3b82f6', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 0, pointHoverRadius: 5,
                            },
                            {
                                label: 'Won',
                                data: trend.map(d => d.won),
                                borderColor: '#10b981',
                                backgroundColor: grad('#10b981'),
                                fill: true, tension: 0.35, borderWidth: 2.5,
                                pointBackgroundColor: '#10b981', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 0, pointHoverRadius: 5,
                            },
                            {
                                label: 'Lost',
                                data: trend.map(d => d.lost),
                                borderColor: '#ef4444',
                                backgroundColor: grad('#ef4444'),
                                fill: true, tension: 0.35, borderWidth: 2.5,
                                pointBackgroundColor: '#ef4444', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 0, pointHoverRadius: 5,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
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

    {{-- Main grid --}}
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-4 items-start">
        {{-- Recent quotes (left, 2 cols) --}}
        <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">Recent quotes</h3>
                <a href="{{ route('quotes.index') }}" class="text-sm font-medium text-primary-800 hover:text-primary-900">
                    View all <i class="ri-arrow-right-line"></i>
                </a>
            </div>
            @if ($recent->isEmpty())
                <x-app.empty-state
                    icon="ri-file-list-3-line"
                    title="No quotes yet"
                    message="Start by creating a quote — it takes under 2 minutes."
                    ctaLabel="New quote"
                    ctaHref="{{ route('quotes.create') }}" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">Client</th>
                                <th class="px-5 py-3 text-left font-semibold">Ref.</th>
                                <th class="px-5 py-3 text-left font-semibold">Boat</th>
                                <th class="px-5 py-3 text-right font-semibold">Amount excl. VAT</th>
                                <th class="px-5 py-3 text-left font-semibold">Status</th>
                                <th class="px-5 py-3 text-left font-semibold">Opened</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($recent as $q)
                                @php
                                    $first = $q->client_snapshot['first_name'] ?? '';
                                    $last  = $q->client_snapshot['last_name']  ?? '';
                                    $clientName = trim($first . ' ' . $last) ?: 'Guest';
                                    $initials = strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1)) ?: 'G';
                                    $opens = (int) ($q->tracking['open_count'] ?? 0);
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="w-8 h-8 rounded-full bg-primary-50 text-primary-800 text-xs font-bold flex items-center justify-center shrink-0">
                                                {{ $initials }}
                                            </span>
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-900 truncate">{{ $clientName }}</div>
                                                <div class="text-xs text-gray-500 truncate">{{ $q->client_snapshot['email'] ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <a href="{{ route('quotes.show', $q->_id) }}"
                                            class="font-mono text-xs px-2 py-1 rounded bg-gray-100 text-gray-700 hover:bg-primary-50 hover:text-primary-800">
                                            {{ $q->number }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="text-gray-900">{{ $q->model_snapshot['name'] ?? '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ $q->model_snapshot['brand'] ?? '' }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="font-semibold text-gray-900">
                                            €{{ number_format($q->totals['total_ht'] ?? 0, 0, ',', ' ') }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            incl. VAT €{{ number_format($q->totals['total_ttc'] ?? 0, 0, ',', ' ') }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-3"><x-app.status-pill :status="$q->status" /></td>
                                    <td class="px-5 py-3">
                                        @if (! $q->sent_at)
                                            <span class="text-xs text-gray-400">Not sent</span>
                                        @elseif ($opens > 0)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold">
                                                <i class="ri-eye-line"></i> {{ $opens }}×
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">0×</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Right column stack --}}
        <div class="space-y-4">
            {{-- Quick actions --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-900 mb-4">Quick actions</h3>
                <div class="grid grid-cols-2 gap-3">
                    <a href="{{ route('quotes.create') }}" class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-200 hover:bg-primary-50 hover:border-primary-200 transition">
                        <i class="ri-file-add-line text-2xl text-primary-800"></i>
                        <span class="text-xs font-medium text-gray-900 text-center">New quote</span>
                    </a>
                    <a href="{{ route('clients.create') }}" class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-200 hover:bg-primary-50 hover:border-primary-200 transition">
                        <i class="ri-user-add-line text-2xl text-primary-800"></i>
                        <span class="text-xs font-medium text-gray-900 text-center">Add client</span>
                    </a>
                    <a href="#" class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-200 hover:bg-primary-50 hover:border-primary-200 transition">
                        <i class="ri-upload-cloud-2-line text-2xl text-primary-800"></i>
                        <span class="text-xs font-medium text-gray-900 text-center">Import catalogue</span>
                    </a>
                    <a href="{{ route('quotes.index') }}" class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-200 hover:bg-primary-50 hover:border-primary-200 transition">
                        <i class="ri-file-list-3-line text-2xl text-primary-800"></i>
                        <span class="text-xs font-medium text-gray-900 text-center">All quotes</span>
                    </a>
                </div>
            </div>

            {{-- Top quoted models --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900">Top quoted models</h3>
                    <span class="text-xs text-gray-500">This month</span>
                </div>
                @if ($topModels->isEmpty())
                    <x-app.empty-state
                        icon="ri-trophy-line"
                        title="No quotes this month"
                        message="Top models will appear here once you have quotes."
                        size="sm" />
                @else
                    <ul class="space-y-3">
                        @foreach ($topModels as $i => $m)
                            <li>
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="font-medium text-gray-900">
                                        <span class="text-gray-400">#{{ $i + 1 }}</span>
                                        {{ $m['name'] }}
                                        <span class="text-gray-500 font-normal">· {{ $m['brand'] }}</span>
                                    </span>
                                    <span class="font-semibold text-gray-900">{{ $m['count'] }}</span>
                                </div>
                                <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-primary-800 to-primary-500"
                                        style="width: {{ ($m['count'] / $topModelMax) * 100 }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Conversion rate --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900">Conversion rate</h3>
                    <span class="text-xs text-gray-500">{{ now()->format('F Y') }}</span>
                </div>
                <p class="text-4xl font-bold text-primary-900">{{ $conversionRate }}%</p>
                <p class="text-xs text-gray-500 mb-3">quotes sent → won</p>
                <div class="h-2 rounded-full bg-gray-100 overflow-hidden mb-4">
                    <div class="h-full bg-emerald-500" style="width: {{ $conversionRate }}%"></div>
                </div>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Quotes sent</dt>
                        <dd class="font-semibold text-gray-900">{{ $sentThisMonth }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Won</dt>
                        <dd class="font-semibold text-emerald-600">{{ $wonThisMonth }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Lost</dt>
                        <dd class="font-semibold text-red-600">{{ $lostThisMonth }}</dd>
                    </div>
                    @if ($avgDaysToClose !== null)
                        <div class="flex justify-between pt-2 border-t border-gray-100 mt-2">
                            <dt class="text-gray-500">Avg. time to close</dt>
                            <dd class="font-semibold text-gray-900">{{ $avgDaysToClose }} days</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </section>
</x-app-layout>

