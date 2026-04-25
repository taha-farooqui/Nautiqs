<x-app-layout title="Dashboard" header="Dashboard">

    {{-- Welcome banner --}}
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary-900 to-primary-700 text-white p-6 mb-6">
        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <p class="text-sm text-white/70">{{ now()->format('l, F j, Y') }}</p>
                <h2 class="text-2xl font-bold mt-1">Welcome back, {{ auth()->user()->name }}</h2>
                <p class="text-white/80 mt-1 max-w-xl">
                    Here's what's happening in your dealership workspace today.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('quotes.create') }}"
                    class="inline-flex items-center gap-2 bg-white text-primary-900 font-semibold px-4 py-2.5 rounded-lg hover:bg-white/90 transition">
                    <i class="ri-add-line"></i> New quote
                </a>
                <a href="{{ route('clients.create') }}"
                    class="inline-flex items-center gap-2 bg-white/10 border border-white/20 text-white font-medium px-4 py-2.5 rounded-lg hover:bg-white/20 transition">
                    <i class="ri-user-add-line"></i> Add client
                </a>
            </div>
        </div>
        <div class="absolute right-0 top-0 opacity-10 pointer-events-none">
            <i class="ri-sailboat-line" style="font-size: 220px;"></i>
        </div>
    </section>

    {{-- Stat cards --}}
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <x-app.stat-card
            label="Quotes today"
            :value="(string) $quotesTodayCount"
            icon="ri-file-list-3-line"
            iconColor="bg-primary-50 text-primary-800" />

        <x-app.stat-card
            label="Conversion rate"
            :value="$conversionRate . '%'"
            icon="ri-exchange-line"
            iconColor="bg-emerald-50 text-emerald-600" />

        <x-app.stat-card
            label="Won this month"
            :value="(string) $wonThisMonth"
            icon="ri-trophy-line"
            iconColor="bg-amber-50 text-amber-600" />

        <x-app.stat-card
            label="Active clients"
            :value="(string) $activeClientsCount"
            icon="ri-user-smile-line"
            iconColor="bg-indigo-50 text-indigo-600" />
    </section>

    {{-- Charts row --}}
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
        <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="font-semibold text-gray-900">Quote volume</h3>
                    <p class="text-sm text-gray-500">Last 30 days · all statuses</p>
                </div>
            </div>
            @if ($hasAnyVolume)
                <div class="h-64">
                    <canvas id="quoteVolumeChart"></canvas>
                </div>
            @else
                <x-app.empty-state
                    icon="ri-line-chart-line"
                    title="No quote activity yet"
                    message="Once you start creating quotes, you'll see your 30-day trend here."
                    ctaLabel="Create your first quote"
                    ctaHref="{{ route('quotes.create') }}"
                    class="h-64" />
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="font-semibold text-gray-900">Quotes by status</h3>
                    <p class="text-sm text-gray-500">Current pipeline</p>
                </div>
            </div>
            @if ($hasAnyStatus)
                <div class="h-64">
                    <canvas id="quotesByStatusChart"></canvas>
                </div>
            @else
                <x-app.empty-state
                    icon="ri-bar-chart-2-line"
                    title="No pipeline yet"
                    message="Your quote pipeline will appear here."
                    size="sm"
                    class="h-64" />
            @endif
        </div>
    </section>

    {{-- Bottom row --}}
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        {{-- Most quoted models --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-semibold text-gray-900">Most quoted models</h3>
                    <p class="text-sm text-gray-500">Top 5 this month</p>
                </div>
                <i class="ri-trophy-line text-amber-500 text-xl"></i>
            </div>
            @if ($mostQuoted->isEmpty())
                <x-app.empty-state
                    icon="ri-trophy-line"
                    title="No quotes this month"
                    message="Top models will rank here once you have quotes."
                    size="sm" />
            @else
                <ul class="space-y-3">
                    @foreach ($mostQuoted as $i => $m)
                        <li class="flex items-center gap-3">
                            <span class="w-6 h-6 shrink-0 rounded-full bg-gray-100 text-gray-600 text-xs font-bold flex items-center justify-center">
                                {{ $i + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $m['name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $m['brand'] }}</p>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ $m['count'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Recent quotes --}}
        <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900">Recent quotes</h3>
                    <p class="text-sm text-gray-500">Latest 5 activity</p>
                </div>
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
                                <th class="px-5 py-3 text-left font-semibold">Quote</th>
                                <th class="px-5 py-3 text-left font-semibold">Client</th>
                                <th class="px-5 py-3 text-left font-semibold">Model</th>
                                <th class="px-5 py-3 text-right font-semibold">Amount</th>
                                <th class="px-5 py-3 text-left font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($recent as $q)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 font-medium text-gray-900">
                                        <a href="{{ route('quotes.show', $q->_id) }}" class="hover:text-primary-800">{{ $q->number }}</a>
                                    </td>
                                    <td class="px-5 py-3 text-gray-700">
                                        {{ trim(($q->client_snapshot['first_name'] ?? '') . ' ' . ($q->client_snapshot['last_name'] ?? '')) ?: '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-gray-700">{{ $q->model_snapshot['name'] ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right font-semibold text-gray-900">
                                        €{{ number_format($q->totals['total_ttc'] ?? 0, 0, ',', ' ') }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-app.status-pill :status="$q->status" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    @push('scripts')
        <script>
            const volumeData = @json($volume);
            const statusCounts = @json($statusCounts);

            document.addEventListener('DOMContentLoaded', () => {
                const primary = '#0e4f79';
                const gridColor = 'rgba(15, 23, 42, 0.06)';
                Chart.defaults.font.family = "'Inter', sans-serif";
                Chart.defaults.color = '#64748b';

                const lineCtx = document.getElementById('quoteVolumeChart');
                if (lineCtx) {
                    const gradient = lineCtx.getContext('2d').createLinearGradient(0, 0, 0, 250);
                    gradient.addColorStop(0, 'rgba(14, 79, 121, 0.25)');
                    gradient.addColorStop(1, 'rgba(14, 79, 121, 0)');

                    new Chart(lineCtx, {
                        type: 'line',
                        data: {
                            labels: volumeData.map(d => d.label),
                            datasets: [{
                                label: 'Quotes',
                                data: volumeData.map(d => d.count),
                                borderColor: primary,
                                backgroundColor: gradient,
                                borderWidth: 2.5,
                                pointBackgroundColor: primary,
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 0,
                                pointHoverRadius: 5,
                                fill: true,
                                tension: 0.35,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8, displayColors: false } },
                            scales: {
                                x: { grid: { display: false }, ticks: { maxTicksLimit: 6, font: { size: 11 } } },
                                y: { beginAtZero: true, grid: { color: gridColor, drawBorder: false }, ticks: { font: { size: 11 }, stepSize: 1 } },
                            },
                        },
                    });
                }

                const barCtx = document.getElementById('quotesByStatusChart');
                if (barCtx) {
                    new Chart(barCtx, {
                        type: 'bar',
                        data: {
                            labels: ['Draft', 'Sent', 'Won', 'Lost'],
                            datasets: [{
                                label: 'Quotes',
                                data: [statusCounts.draft, statusCounts.sent, statusCounts.won, statusCounts.lost],
                                backgroundColor: [
                                    'rgba(100, 116, 139, 0.85)',
                                    'rgba(14, 79, 121, 0.85)',
                                    'rgba(16, 185, 129, 0.85)',
                                    'rgba(239, 68, 68, 0.85)',
                                ],
                                borderRadius: 8,
                                borderSkipped: false,
                                maxBarThickness: 48,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8, displayColors: false } },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { size: 12 } } },
                                y: { beginAtZero: true, grid: { color: gridColor, drawBorder: false }, ticks: { font: { size: 11 }, stepSize: 1 } },
                            },
                        },
                    });
                }
            });
        </script>
    @endpush
</x-app-layout>
