<x-app-layout :title="__('Dashboard')" :header="__('Dashboard')">
    @include('brand.partials.flash')
    @php
        $eur = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' €';
        $hasMonths = collect($months)->sum('quoted') > 0;
        $hasStatus = $statuses->sum() > 0;
        $stepsDone = collect($steps)->where('done', true)->count();
        $firstName = explode(' ', trim(auth()->user()->name))[0];
        $periods = ['month' => __('This month'), '6m' => __('6 months'), 'year' => __('12 months')];
        $q = fn (array $over) => route('brand.dashboard', array_filter(array_merge(['period' => $period, 'brand' => $brandId], $over)));
    @endphp

    {{-- Hero --}}
    <section class="rise relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary-900 via-primary-800 to-primary-600 text-white p-6 sm:p-7 mb-5">
        <svg class="absolute -right-10 -bottom-10 w-80 h-80 text-white/5" viewBox="0 0 200 200" fill="currentColor" aria-hidden="true">
            <path d="M100 10 L100 150 L40 150 Z"/><path d="M108 30 L108 150 L165 150 Z"/><path d="M20 160 Q100 190 180 160 L170 175 Q100 200 30 175 Z"/>
        </svg>
        <div class="relative flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
            <div>
                <p class="text-white/70 text-sm">{{ $account->name }}</p>
                <h2 class="text-2xl sm:text-3xl font-bold mt-1">{{ __('Hello :name', ['name' => $firstName]) }} 👋</h2>
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1"><span class="w-2 h-2 rounded-full bg-emerald-400"></span>{{ trans_choice('{0}No dealer connected yet|{1}:count dealer connected|[2,*]:count dealers connected', $connected, ['count' => $connected]) }}</span>
                    @if ($pending)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1"><span class="w-2 h-2 rounded-full bg-amber-300 animate-pulse"></span>{{ trans_choice('{1}:count invitation pending|[2,*]:count invitations pending', $pending, ['count' => $pending]) }}</span>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route($account->isEngine() ? 'brand.engines.create' : 'brand.catalogue.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-white text-primary-900 px-3.5 py-2 text-sm font-semibold hover:bg-primary-50 transition">
                    <i class="ri-add-line"></i> {{ $account->isEngine() ? __('Add engine') : __('Add model') }}</a>
                <a href="{{ route('brand.dealers.index', ['invite' => 1]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 hover:bg-white/20 px-3.5 py-2 text-sm font-semibold transition">
                    <i class="ri-user-add-line"></i> {{ __('Invite dealers') }}</a>
                <a href="{{ route('brand.levels.edit') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 hover:bg-white/20 px-3.5 py-2 text-sm font-semibold transition">
                    <i class="ri-price-tag-3-line"></i> {{ __('Price levels') }}</a>
            </div>
        </div>
    </section>

    {{-- Getting started — until every step is done --}}
    @if ($stepsDone < count($steps))
        <section class="rise bg-white rounded-2xl border border-gray-200 p-5 mb-5" style="--d: 1">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h3 class="font-semibold text-gray-900">{{ __('Getting started') }}</h3>
                    <p class="text-sm text-gray-500">{{ __(':done of :total steps done', ['done' => $stepsDone, 'total' => count($steps)]) }}</p>
                </div>
                <div class="w-40 h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full rounded-full bg-emerald-500 grow-x" style="width: {{ round($stepsDone / count($steps) * 100) }}%"></div>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                @foreach ($steps as $i => $s)
                    <a href="{{ $s['href'] }}" class="lift group flex items-start gap-3 rounded-xl border p-4 {{ $s['done'] ? 'border-emerald-200 bg-emerald-50/50' : 'border-gray-200 hover:border-primary-300' }}">
                        <span class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center {{ $s['done'] ? 'bg-emerald-500 text-white' : 'bg-primary-50 text-primary-800' }}">
                            <i class="{{ $s['done'] ? 'ri-check-line' : $s['icon'] }} text-lg"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold {{ $s['done'] ? 'text-emerald-800' : 'text-gray-900' }}">{{ $i + 1 }}. {{ $s['title'] }}</span>
                            <span class="block text-xs text-gray-500 mt-0.5">{{ $s['text'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Filters --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="inline-flex rounded-xl border border-gray-200 bg-white p-1 text-sm">
            @foreach ($periods as $p => $label)
                <a href="{{ $q(['period' => $p]) }}" class="px-3 py-1.5 rounded-lg font-medium transition {{ $period === $p ? 'bg-primary-800 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">{{ $label }}</a>
            @endforeach
        </div>
        @if ($brands->count() > 1 && ! $account->isEngine())
            <div class="flex flex-wrap gap-1.5 text-sm">
                <a href="{{ $q(['brand' => null]) }}" class="px-3 py-1.5 rounded-full font-medium border transition {{ $brandId === '' ? 'bg-primary-50 border-primary-200 text-primary-800' : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300' }}">{{ __('All brands') }}</a>
                @foreach ($brands as $b)
                    <a href="{{ $q(['brand' => (string) $b->_id]) }}" class="px-3 py-1.5 rounded-full font-medium border transition {{ $brandId === (string) $b->_id ? 'bg-primary-50 border-primary-200 text-primary-800' : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300' }}">{{ $b->name }}</a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        @foreach ([
            [__('Quotes made'), $kpis['quotes'], '', 'ri-file-list-3-line', 'bg-primary-50 text-primary-800'],
            [__('Quoted value (HT)'), round($kpis['quoted']), ' €', 'ri-money-euro-circle-line', 'bg-sky-50 text-sky-700'],
            [__('Won (HT)'), round($kpis['won']), ' €', 'ri-trophy-line', 'bg-emerald-50 text-emerald-700'],
            [__('Win rate'), $kpis['win_rate'], ' %', 'ri-percent-line', 'bg-amber-50 text-amber-700'],
        ] as $i => [$label, $value, $suffix, $icon, $tone])
            <div class="rise lift bg-white rounded-2xl border border-gray-200 p-5" style="--d: {{ $i + 2 }}">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm text-gray-500">{{ $label }}</p>
                    <span class="w-10 h-10 rounded-xl {{ $tone }} flex items-center justify-center shrink-0"><i class="{{ $icon }} text-xl"></i></span>
                </div>
                @if ($value === null)
                    <p class="text-2xl font-bold text-gray-300 mt-1">—</p>
                @else
                    <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums" x-data="countUp({{ (float) $value }}, { suffix: @js($suffix) })" x-text="display">{{ number_format((float) $value, 0, ',', ' ') }}{{ $suffix }}</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-5">
        <section class="rise xl:col-span-2 bg-white rounded-2xl border border-gray-200 p-5" style="--d: 6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h3 class="font-semibold text-gray-900">{{ __('Quotes per month') }}</h3>
                @if ($hasMonths)
                    <div class="flex gap-4 text-xs text-gray-600">
                        <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-primary-200"></span>{{ __('Quoted') }}</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-primary-800"></span>{{ __('Won') }}</span>
                    </div>
                @endif
            </div>
            @if ($hasMonths)
                <div class="h-64"><canvas id="brandMonths"></canvas></div>
            @else
                <x-app.empty-state icon="ri-bar-chart-2-line" size="sm" class="h-64"
                    :title="__('No data yet')" :message="__('As soon as your dealers make quotes with your products, they appear here month by month.')" />
            @endif
        </section>

        <section class="rise bg-white rounded-2xl border border-gray-200 p-5" style="--d: 7">
            <h3 class="font-semibold text-gray-900 mb-3">{{ __('Quote status') }}</h3>
            @if ($hasStatus)
                <div class="relative h-44"><canvas id="brandStatus"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-2xl font-bold text-gray-900">{{ $statuses->sum() }}</span><span class="text-xs text-gray-500">{{ __('quotes') }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 mt-4 text-sm">
                    @foreach (['draft' => [__('Draft'), 'bg-gray-300'], 'sent' => [__('Sent'), 'bg-sky-500'], 'won' => [__('Won'), 'bg-emerald-500'], 'lost' => [__('Lost'), 'bg-red-400']] as $s => [$label, $dot])
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-1.5">
                            <span class="inline-flex items-center gap-1.5 text-gray-600"><span class="w-2 h-2 rounded-full {{ $dot }}"></span>{{ $label }}</span>
                            <span class="font-semibold text-gray-900">{{ $statuses[$s] }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <x-app.empty-state icon="ri-donut-chart-line" size="sm" class="h-64" :title="__('No data yet')" :message="__('Draft, sent, won and lost quotes will show here.')" />
            @endif
        </section>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-5">
        <section class="rise bg-white rounded-2xl border border-gray-200 p-5" style="--d: 8">
            <h3 class="font-semibold text-gray-900 mb-4">{{ $account->isEngine() ? __('Most quoted engines') : __('Most quoted versions') }}</h3>
            @if ($top->isNotEmpty())
                @php $topMax = max(1, $top->max()); @endphp
                <ol class="space-y-3.5 text-sm">
                    @foreach ($top as $label => $n)
                        <li>
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-2 min-w-0">
                                    <span class="w-6 h-6 shrink-0 rounded-md text-xs font-bold flex items-center justify-center {{ $loop->first ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">{{ $loop->iteration }}</span>
                                    <span class="font-medium text-gray-900 truncate">{{ $label }}</span>
                                </span>
                                <span class="text-gray-500 tabular-nums">{{ $n }}</span>
                            </div>
                            <div class="h-1.5 bg-gray-100 rounded-full mt-1.5 overflow-hidden"><div class="grow-x h-full rounded-full bg-gradient-to-r from-primary-600 to-primary-800" style="width: {{ round($n / $topMax * 100) }}%; --d: {{ $loop->index }}"></div></div>
                        </li>
                    @endforeach
                </ol>
            @else
                <x-app.empty-state :icon="$account->isEngine() ? 'ri-settings-3-line' : 'ri-sailboat-line'" size="sm" :title="__('No data yet')" :message="__('Your best sellers will rank here.')" />
            @endif
        </section>

        <section class="rise xl:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden" style="--d: 9">
            <div class="px-5 py-4 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">{{ __('Dealers') }}</h3>
                <a href="{{ route('brand.dealers.index') }}" class="text-sm font-medium text-primary-800 hover:underline">{{ __('Manage dealers') }} <i class="ri-arrow-right-line"></i></a>
            </div>
            @if ($dealers->isEmpty())
                <x-app.empty-state icon="ri-store-2-line" size="sm" :title="__('No data yet')" :message="__('Each dealer\'s quotes, wins and average discount will show here.')" />
            @else
                @php $dMax = max(1, $dealers->max('quoted')); @endphp
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                            <tr><th class="px-5 py-2.5">{{ __('Dealer') }}</th><th class="px-4 py-2.5 text-right">{{ __('Quotes') }}</th><th class="px-4 py-2.5 w-48">{{ __('Quoted HT') }}</th><th class="px-4 py-2.5 text-right">{{ __('Win rate') }}</th><th class="px-5 py-2.5 text-right">{{ __('Avg. discount') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($dealers as $d)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="w-8 h-8 rounded-full bg-primary-50 text-primary-800 text-xs font-bold flex items-center justify-center shrink-0">{{ mb_strtoupper(mb_substr($d['name'], 0, 2)) }}</span>
                                            <span class="min-w-0"><span class="block font-medium text-gray-900 truncate">{{ $d['name'] }}</span><span class="block text-xs text-gray-500">{{ $d['level'] }}</span></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $d['quotes'] }}</td>
                                    <td class="px-4 py-3">
                                        <div class="text-right tabular-nums font-semibold text-gray-900">{{ $eur($d['quoted']) }}</div>
                                        <div class="h-1 bg-gray-100 rounded-full mt-1 overflow-hidden"><div class="grow-x h-full bg-primary-700 rounded-full" style="width: {{ round($d['quoted'] / $dMax * 100) }}%"></div></div>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $d['win_rate'] !== null ? $d['win_rate'] . ' %' : '—' }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums {{ $d['discount'] > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $d['discount'] > 0 ? '−' . $d['discount'] . ' %' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <div class="rise" style="--d: 10">
        @include('brand.quotes.table', ['rows' => $latest, 'title' => __('Latest quotes'), 'more' => true])
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (!window.Chart) return;
                Chart.defaults.font.family = "'Geist', sans-serif";
                Chart.defaults.color = '#64748b';
                const tooltip = { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8 };

                const months = @json($months);
                const m = document.getElementById('brandMonths');
                if (m) new Chart(m, {
                    type: 'bar',
                    data: {
                        labels: months.map(d => d.label),
                        datasets: [
                            { label: @json(__('Quoted')), data: months.map(d => d.quoted), backgroundColor: '#c4d6e4', hoverBackgroundColor: '#9cbad0', borderRadius: 6, maxBarThickness: 28 },
                            { label: @json(__('Won')), data: months.map(d => d.won), backgroundColor: '#0e4f79', hoverBackgroundColor: '#093a5c', borderRadius: 6, maxBarThickness: 28 },
                        ],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        animation: { duration: 900, easing: 'easeOutQuart', delay: (c) => c.type === 'data' ? c.dataIndex * 60 : 0 },
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { display: false }, tooltip },
                        scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(15,23,42,0.06)' } } },
                    },
                });

                const s = document.getElementById('brandStatus');
                const st = @json($statuses);
                if (s) new Chart(s, {
                    type: 'doughnut',
                    data: {
                        labels: [@json(__('Draft')), @json(__('Sent')), @json(__('Won')), @json(__('Lost'))],
                        datasets: [{ data: [st.draft, st.sent, st.won, st.lost], backgroundColor: ['#d1d5db', '#0ea5e9', '#10b981', '#f87171'], borderWidth: 0, hoverOffset: 6 }],
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '72%', animation: { animateRotate: true, duration: 1000 }, plugins: { legend: { display: false }, tooltip } },
                });
            });
        </script>
    @endpush
</x-app-layout>
