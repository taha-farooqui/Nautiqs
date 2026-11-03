{{-- Platform overview, in the same Figma style as the dealer and brand dashboards. --}}
<x-admin-layout :title="__('Platform overview')" :header="__('Platform overview')">
    @php
        $eur = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' €';
        $firstName = explode(' ', trim(auth()->user()->name))[0];
        $periods = ['month' => __('This month'), '6m' => __('Last 6 months'), 'year' => __('Last 12 months')];

        // Trend badge, always shown: [label, 'up' | 'down' | 'flat'].
        $pctBadge = function ($d, $now) {
            if ($d === null) return $now > 0 ? ['+100 %', 'up'] : ['0 %', 'flat'];
            if ((float) $d == 0) return ['0 %', 'flat'];
            return [($d > 0 ? '+' : '−') . rtrim(rtrim(number_format(abs($d), 1, ',', ''), '0'), ',') . ' %', $d > 0 ? 'up' : 'down'];
        };
        $kpiCards = [
            ['label' => __('Quotes'), 'value' => number_format($kpis['quotes'], 0, ',', ' '),
                'badge' => $pctBadge($kpis['quotes_delta'], $kpis['quotes']),
                'note' => trans_choice('{0}vs. no quote|{1}vs. :count quote|[2,*]vs. :count quotes', $kpis['quotes_prev'], ['count' => $kpis['quotes_prev']])],
            ['label' => __('Total quoted (excl. VAT)'), 'value' => $eur($kpis['quoted']),
                'badge' => $pctBadge($kpis['quoted_delta'], $kpis['quoted']),
                'note' => __('vs. :v', ['v' => $eur($kpis['quoted_prev'])])],
            ['label' => __('Won (HT)'), 'value' => $eur($kpis['won']),
                'badge' => $pctBadge($kpis['won_delta'], $kpis['won']),
                'note' => trans_choice('{0}no signed quote|{1}:count signed quote|[2,*]:count signed quotes', $kpis['won_count'], ['count' => $kpis['won_count']])],
            ['label' => __('Active dealers'), 'value' => number_format($kpis['dealers_active'], 0, ',', ' '),
                'badge' => $kpis['new_dealers'] > 0 ? ['+' . $kpis['new_dealers'], 'up'] : ['0', 'flat'],
                'note' => trans_choice('{0}no new dealership|{1}:count new dealership|[2,*]:count new dealerships', $kpis['new_dealers'], ['count' => $kpis['new_dealers']])
                    . ' · ' . $kpis['dealers_total'] . ' ' . __('total')],
        ];

        $hasTrend = collect($trend)->sum('count') > 0;
        $statusTotal = array_sum($statuses);
        $statusMeta = [
            'sent'  => [__('Sent'), '#0e4f79'],
            'won'   => [__('Won'), '#2b8fd6'],
            'draft' => [__('Drafts'), '#e59a1a'],
            'lost'  => [__('Lost'), '#e8e8eb'],
        ];
        $tags = [
            'draft' => [__('Draft'), 'bg-nq-muted border-nq-border text-nq-text-2', 'bg-nq-text-3'],
            'sent'  => [__('Sent'), 'bg-nq-info-bg border-[#d4e9fb] text-[#1d6aa5]', 'bg-nq-chart2'],
            'won'   => [__('Won'), 'bg-nq-success-bg border-nq-success-border text-nq-success-text', 'bg-nq-success'],
            'lost'  => [__('Lost'), 'bg-nq-error-bg border-[#fbd5d6] text-nq-error-text', 'bg-nq-error'],
        ];
        $initials = fn ($s) => mb_strtoupper(collect(preg_split('/\s+/', trim((string) $s)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('')) ?: '—';

        $card = 'bg-white border border-nq-border rounded-xl';
        $btn = 'h-9 inline-flex items-center gap-2 px-4 rounded-lg text-sm leading-[22px] font-medium transition-colors whitespace-nowrap';
        $btnSecondary = $btn . ' bg-white border border-nq-border text-nq-text hover:bg-nq-subtle';
        $cardTitle = 'text-base leading-6 font-medium text-nq-text';
        $link = 'inline-flex items-center gap-1 text-sm leading-[22px] font-medium text-primary-800 hover:underline';
        $pill = 'inline-flex items-center gap-1 px-2 py-1 rounded-md bg-nq-subtle border border-nq-border text-xs leading-5 font-medium text-nq-text-2 whitespace-nowrap';
    @endphp

    <div class="flex flex-col gap-4">
        {{-- Page heading --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl leading-8 font-semibold tracking-[-0.24px] text-nq-text">{{ __('Hello :name', ['name' => $firstName]) }}</h2>
                    <span class="h-6 inline-flex items-center gap-1.5 px-2 rounded-md bg-nq-success-bg border border-nq-success-border text-xs leading-5 font-medium text-nq-success-text">
                        <span class="w-1.5 h-1.5 rounded-full bg-nq-success"></span>
                        {{ trans_choice('{0}no active dealership|{1}:count active dealership|[2,*]:count active dealerships', $kpis['dealers_active'], ['count' => $kpis['dealers_active']]) }}
                    </span>
                </div>
                <p class="text-sm leading-[22px] text-nq-text-2">{{ __('The whole platform at a glance: dealerships, quotes and activity.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.account-requests.index') }}" class="{{ $btnSecondary }}">
                    <i class="ph-bold ph-user-plus text-base"></i> {{ __('Account requests') }}
                    @if ($pendingRequests > 0)
                        <span class="min-w-[18px] h-[18px] px-1.5 rounded-full bg-nq-error text-white text-[11px] font-medium flex items-center justify-center">{{ $pendingRequests }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.dealers.index') }}" class="{{ $btn }} bg-primary-800 text-white hover:bg-nq-active">
                    <i class="ph-bold ph-storefront text-base"></i> {{ __('Dealers') }}</a>
            </div>
        </div>

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-2">
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open" class="{{ $btnSecondary }}">
                    <i class="ph-bold ph-calendar-blank text-base"></i> {{ $periods[$period] }} <i class="ph-bold ph-caret-down text-base"></i>
                </button>
                <div x-show="open" x-cloak x-transition @click.outside="open = false" class="absolute left-0 z-20 mt-1 w-52 rounded-xl border border-nq-border bg-white p-1 shadow-lg">
                    @foreach ($periods as $p => $label)
                        <a href="{{ route('admin.dashboard', ['period' => $p]) }}"
                            class="block rounded-lg px-3 py-2 text-sm {{ $period === $p ? 'bg-nq-primary-bg text-primary-800 font-medium' : 'text-nq-text hover:bg-nq-subtle' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach ($kpiCards as $i => $k)
                @php
                    [$bLabel, $bDir] = $k['badge'];
                    [$bCls, $bIcon] = match ($bDir) {
                        'up'   => ['bg-nq-success-bg border-nq-success text-nq-success-text', 'ph-trend-up'],
                        'down' => ['bg-nq-error-bg border-nq-error text-nq-error-text', 'ph-trend-down'],
                        default => ['bg-nq-muted border-nq-border text-nq-text-2', 'ph-minus'],
                    };
                @endphp
                <div class="rise {{ $card }} pt-4 px-4 pb-[30px] flex flex-col gap-6" style="--d: {{ $i }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm leading-[22px] font-medium text-nq-text-2">{{ $k['label'] }}</p>
                        <span class="inline-flex items-center gap-[3px] px-1.5 py-0.5 rounded-md border text-xs leading-5 font-medium whitespace-nowrap {{ $bCls }}">
                            <i class="ph-bold {{ $bIcon }} text-xs"></i>{{ $bLabel }}</span>
                    </div>
                    <div>
                        <p class="text-2xl leading-8 font-semibold text-nq-text tabular-nums">{{ $k['value'] }}</p>
                        <p class="text-xs leading-5 text-nq-text-3">{{ $k['note'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Quotes per month + status --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <section class="rise xl:col-span-2 {{ $card }} p-4 flex flex-col gap-4" style="--d: 4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="{{ $cardTitle }}">{{ __('Quotes per month') }}</h3>
                    <div class="flex items-center gap-2">
                        <span class="text-xs leading-5 text-nq-text-3">{{ __('Total:') }}</span>
                        <span class="text-sm leading-[22px] font-medium text-nq-text tabular-nums">{{ $kpis['quotes'] }}</span>
                    </div>
                </div>
                @if ($hasTrend)
                    <div class="relative h-[268px] rounded-[10px] bg-nq-subtle p-3"><canvas id="adminTrend"></canvas></div>
                @else
                    <div class="rounded-[10px] bg-nq-subtle">
                        <x-app.empty-state icon="ri-bar-chart-2-line" size="sm" class="h-[268px]" :title="__('No data yet')" :message="__('No quotes yet across the platform.')" />
                    </div>
                @endif
            </section>

            <section class="rise {{ $card }} p-4 flex flex-col gap-4" style="--d: 5">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="{{ $cardTitle }}">{{ __('Quote status') }}</h3>
                    <span class="text-xs leading-5 text-nq-text-3">{{ $periods[$period] }}</span>
                </div>
                <div class="flex-1 rounded-[10px] bg-nq-subtle px-4 py-6 flex items-center justify-center">
                    @if ($statusTotal > 0)
                        <div class="w-full flex flex-col sm:flex-row xl:flex-col 2xl:flex-row items-center justify-center gap-4">
                            <div class="relative w-[156px] h-[156px] shrink-0"><canvas id="adminStatus"></canvas>
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                    <span class="text-2xl leading-8 font-semibold text-nq-text">{{ $statusTotal }}</span><span class="text-xs leading-5 text-nq-text-3">{{ __('quotes') }}</span>
                                </div>
                            </div>
                            <ul class="flex flex-col gap-2">
                                @foreach ($statusMeta as $s => [$label, $color])
                                    <li class="inline-flex items-center gap-1.5 bg-white border border-nq-border-2 rounded-lg pl-2 pr-2.5 py-1.5 whitespace-nowrap">
                                        <span class="w-0.5 h-3 rounded-full" style="background: {{ $s === 'lost' ? '#c9c9cf' : $color }}"></span>
                                        <span class="text-xs leading-5 font-medium text-nq-text">{{ $label }} :</span>
                                        <span class="text-xs leading-5 text-nq-text-3">{{ round($statuses[$s] / $statusTotal * 100) }} % · {{ $statuses[$s] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <x-app.empty-state icon="ri-donut-chart-line" size="sm" class="w-full" :title="__('No data yet')" :message="__('Draft, sent, won and lost quotes will show here.')" />
                    @endif
                </div>
            </section>
        </div>

        {{-- Best dealerships + top models --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <section class="rise {{ $card }} p-4 flex flex-col gap-2" style="--d: 6">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="{{ $cardTitle }}">{{ __('Best dealerships') }}</h3>
                    <a href="{{ route('admin.dealers.index') }}" class="{{ $link }}">{{ __('See all') }} <i class="ph-bold ph-arrow-right text-base"></i></a>
                </div>
                <div>
                    @forelse ($topDealers as $d)
                        <div class="flex items-center gap-3 py-2">
                            <span class="w-8 h-8 shrink-0 rounded-full bg-nq-primary-bg text-primary-800 text-xs font-semibold flex items-center justify-center">{{ $initials($d['name']) }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm leading-[22px] font-medium text-nq-text truncate">{{ $d['name'] }}</p>
                                <p class="text-xs leading-5 text-nq-text-3 truncate">{{ $eur($d['quoted']) }} {{ __('quoted') }}</p>
                            </div>
                            <span class="{{ $pill }}"><i class="ph-bold ph-check text-xs"></i>{{ trans_choice('{1}:count quote|[2,*]:count quotes', $d['quotes'], ['count' => $d['quotes']]) }}</span>
                        </div>
                    @empty
                        <x-app.empty-state icon="ri-store-2-line" size="sm" :title="__('No data yet')" :message="__('Your most active dealerships will rank here.')" />
                    @endforelse
                </div>
            </section>

            <section class="rise {{ $card }} p-4 flex flex-col gap-2" style="--d: 7">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="{{ $cardTitle }}">{{ __('Top quoted models') }}</h3>
                    <span class="text-xs leading-5 text-nq-text-3">{{ $periods[$period] }}</span>
                </div>
                <div>
                    @forelse ($topModels as $m)
                        <div class="flex items-center gap-3 py-2">
                            <span class="w-8 h-8 shrink-0 rounded-full bg-nq-accent-bg text-nq-accent-text text-xs font-semibold flex items-center justify-center">{{ $initials($m['brand']) }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm leading-[22px] font-medium text-nq-text truncate">{{ $m['name'] }}</p>
                                <p class="text-xs leading-5 text-nq-text-3 truncate">{{ $m['brand'] }}</p>
                            </div>
                            <span class="{{ $pill }}"><i class="ph-bold ph-check text-xs"></i>{{ trans_choice('{1}:count quote|[2,*]:count quotes', $m['count'], ['count' => $m['count']]) }}</span>
                        </div>
                    @empty
                        <x-app.empty-state icon="ri-trophy-line" size="sm" :title="__('No data yet')" :message="__('Top models will appear here as you build quotes.')" />
                    @endforelse
                </div>
            </section>
        </div>

        {{-- Recent quotes across all dealers --}}
        <section class="rise {{ $card }} overflow-hidden" style="--d: 8">
            <div class="p-4 flex items-center justify-between gap-2">
                <h3 class="{{ $cardTitle }}">{{ __('Recent quotes across all dealers') }}</h3>
            </div>
            @if ($recent->isEmpty())
                <x-app.empty-state icon="ri-file-list-3-line" size="sm" :title="__('No quotes yet')" :message="__('No quotes yet across the platform.')" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead>
                            <tr class="bg-nq-subtle text-left text-xs leading-5 text-nq-text-2">
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[220px]">{{ __('Dealership') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2">{{ __('Boat') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[150px]">{{ __('Client') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[130px]">{{ __('Ref.') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[130px] text-right">{{ __('Amount HT') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[140px]">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $q)
                                @php
                                    // A dealer's clients are the dealer's, not the platform's:
                                    // the superadmin sees that a quote has a client, never who.
                                    $clientName = \App\Support\Pii\Mask::name(trim(
                                        ($q->client_snapshot['first_name'] ?? '') . ' ' . ($q->client_snapshot['last_name'] ?? '')
                                    )) ?: __('Guest');
                                    [$tLabel, $tCls, $tDot] = $tags[$q->status ?? 'draft'] ?? $tags['draft'];
                                    $rowBorder = $loop->last ? '' : 'border-b border-nq-border-2';
                                @endphp
                                <tr class="hover:bg-nq-subtle">
                                    <td class="h-14 px-4 {{ $rowBorder }}">
                                        <span class="inline-flex items-center gap-2 whitespace-nowrap"><span class="w-6 h-6 shrink-0 rounded-full bg-nq-primary-bg text-primary-800 text-[10px] font-semibold flex items-center justify-center">{{ $initials($q->company_name) }}</span><span class="text-sm leading-[22px] text-nq-text truncate max-w-[160px]">{{ $q->company_name }}</span></span>
                                    </td>
                                    <td class="h-14 px-4 {{ $rowBorder }}">
                                        <div class="text-sm leading-[22px] font-medium text-nq-text whitespace-nowrap">{{ $q->boatLabel() ?: '—' }}</div>
                                        @if (! empty($q->model_snapshot['brand']))
                                            <div class="text-xs leading-5 text-nq-text-3">{{ $q->model_snapshot['brand'] }}</div>
                                        @endif
                                    </td>
                                    <td class="h-14 px-4 text-sm leading-[22px] text-nq-text whitespace-nowrap {{ $rowBorder }}">{{ $clientName }}</td>
                                    <td class="h-14 px-4 whitespace-nowrap {{ $rowBorder }}"><span class="font-mono text-xs px-1.5 py-0.5 rounded-[5px] bg-nq-muted border border-nq-border text-nq-text-2">{{ $q->number }}</span></td>
                                    <td class="h-14 px-4 text-right text-sm leading-[22px] font-medium text-nq-text tabular-nums whitespace-nowrap {{ $rowBorder }}">{{ $eur($q->totals['total_ht'] ?? 0) }}</td>
                                    <td class="h-14 px-4 {{ $rowBorder }}"><span class="h-6 inline-flex items-center gap-1.5 px-2 rounded-md border text-xs leading-5 font-medium whitespace-nowrap {{ $tCls }}"><span class="w-1.5 h-1.5 rounded-full {{ $tDot }}"></span>{{ $tLabel }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Recent platform activity --}}
        <section class="rise {{ $card }} p-4 flex flex-col gap-2" style="--d: 9">
            <div class="flex items-center justify-between gap-2">
                <h3 class="{{ $cardTitle }}">{{ __('Recent platform activity') }}</h3>
                <a href="{{ route('admin.audit.index') }}" class="{{ $link }}">{{ __('View all') }} <i class="ph-bold ph-arrow-right text-base"></i></a>
            </div>
            @if ($recentAudit->isEmpty())
                <x-app.empty-state icon="ri-history-line" size="sm" :title="__('No activity yet.')" :message="__('Actions you take in the platform area will appear here.')" />
            @else
                <ul>
                    @foreach ($recentAudit as $row)
                        <li class="flex items-center gap-3 py-2.5 {{ $loop->last ? '' : 'border-b border-nq-border-2' }}">
                            <span class="w-8 h-8 shrink-0 rounded-lg bg-nq-muted text-nq-text-2 flex items-center justify-center"><i class="ph-bold ph-clock-counter-clockwise text-base"></i></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm leading-[22px] text-nq-text truncate">
                                    <span class="font-medium">{{ $row->actor_email ?? __('Unknown') }}</span>
                                    <span class="text-nq-text-3">·</span>
                                    <span class="font-mono text-xs text-nq-text-2">{{ $row->action }}</span>
                                    @if ($row->target_label)
                                        <span class="text-nq-text-3">·</span> {{ $row->target_label }}
                                    @endif
                                </p>
                            </div>
                            <span class="text-xs leading-5 text-nq-text-3 whitespace-nowrap">{{ $row->created_at?->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    @push('scripts')
        <script>
            // Same chart style as the dealer and brand dashboards (Figma node 19:228).
            document.addEventListener('DOMContentLoaded', () => {
                const when = (el, fn) => { if (!el) return; const go = () => { if (window.Chart && !el.dataset.drawn) { el.dataset.drawn = '1'; fn(); } }; window.Chart ? go() : window.addEventListener('load', go, { once: true }); };
                const tooltip = { backgroundColor: '#0b0b0d', padding: 10, cornerRadius: 8, titleFont: { family: 'Geist' }, bodyFont: { family: 'Geist' } };

                const bars = document.getElementById('adminTrend');
                when(bars, () => {
                    const trend = @json($trend);
                    const data = trend.map(d => d.count);
                    const last = data.length - 1;
                    const hatch = document.createElement('canvas'); hatch.width = hatch.height = 7;
                    const h = hatch.getContext('2d'); h.fillStyle = '#f0f0f2'; h.fillRect(0, 0, 7, 7);
                    h.strokeStyle = '#e4e4e7'; h.lineWidth = 1.5; h.beginPath(); h.moveTo(0, 7); h.lineTo(7, 0); h.stroke();
                    const pattern = bars.getContext('2d').createPattern(hatch, 'repeat');
                    const valuePill = {
                        id: 'valuePill',
                        afterDatasetsDraw(c) {
                            const bar = c.getDatasetMeta(0).data[last];
                            if (!bar || !data[last]) return;
                            const g = c.ctx, label = String(data[last]); g.save(); g.font = '500 12px Geist, sans-serif';
                            const w = g.measureText(label).width + 16, x = bar.x - w / 2, y = bar.y - 34;
                            g.fillStyle = '#f0f0f2'; g.strokeStyle = '#e8e8eb'; g.lineWidth = 1; g.beginPath(); g.roundRect(x, y, w, 26, 6); g.fill(); g.stroke();
                            g.fillStyle = '#0b0b0d'; g.textAlign = 'center'; g.textBaseline = 'middle'; g.fillText(label, bar.x, y + 13); g.restore();
                        },
                    };
                    new Chart(bars, {
                        type: 'bar',
                        data: { labels: trend.map(d => d.label), datasets: [{ label: @js(__('Quotes')), data,
                            backgroundColor: data.map((_, i) => i === last ? '#0e4f79' : pattern),
                            hoverBackgroundColor: data.map((_, i) => i === last ? '#093a5c' : '#e8e8eb'),
                            borderRadius: 8, borderSkipped: false, maxBarThickness: 84, categoryPercentage: 0.8, barPercentage: 0.95 }] },
                        options: {
                            responsive: true, maintainAspectRatio: false, layout: { padding: { top: 38 } },
                            animation: { duration: 800, easing: 'easeOutQuart', delay: c => c.type === 'data' ? c.dataIndex * 60 : 0 },
                            plugins: { legend: { display: false }, tooltip },
                            scales: {
                                x: { grid: { display: false }, border: { display: false }, ticks: { color: '#73737c', font: { family: 'Geist', size: 12 } } },
                                y: { beginAtZero: true, border: { display: false }, grid: { display: false }, ticks: { color: '#73737c', font: { family: 'Geist', size: 12 }, precision: 0, maxTicksLimit: 6 } },
                            },
                        },
                        plugins: [valuePill],
                    });
                });

                const donut = document.getElementById('adminStatus');
                when(donut, () => {
                    const st = @json($statuses);
                    new Chart(donut, {
                        type: 'doughnut',
                        data: { labels: [@js(__('Sent')), @js(__('Won')), @js(__('Drafts')), @js(__('Lost'))],
                            datasets: [{ data: [st.sent, st.won, st.draft, st.lost], backgroundColor: ['#0e4f79', '#2b8fd6', '#e59a1a', '#e8e8eb'], borderWidth: 4, borderColor: '#fbfbfb', borderRadius: 6, hoverOffset: 4 }] },
                        options: { responsive: true, maintainAspectRatio: false, cutout: '62%', animation: { animateRotate: true, duration: 900 }, plugins: { legend: { display: false }, tooltip } },
                    });
                });
            });
        </script>
    @endpush
</x-admin-layout>
