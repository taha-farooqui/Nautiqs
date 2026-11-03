{{-- Dealer dashboard. Our own figures, laid out and styled like Clement's Figma
     (same cards, type sizes, colours and Phosphor icons as the brand dashboard). --}}
<x-app-layout :title="__('Dashboard')" :header="__('Dashboard')">
    @php
        $eur = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' €';
        $firstName = explode(' ', trim(auth()->user()->name))[0];

        // Partner brand invitations waiting for an answer. Read live, not from
        // the cached dashboard data, so it disappears as soon as it's answered.
        $brandInvites = auth()->user()->company_id
            ? app(\App\Services\Brand\BrandInvitations::class)->pendingFor((string) auth()->user()->company_id)
            : collect();

        // Trend badge: [label, up?] or null when there is nothing to compare.
        $pctBadge = fn ($d) => $d === null || (float) $d == 0 ? null : [($d > 0 ? '+' : '−') . rtrim(rtrim(number_format(abs($d), 1, ',', ''), '0'), ',') . ' %', $d > 0];
        $kpis = [
            ['label' => __('Quotes this month'), 'value' => number_format($quotesThisMonth, 0, ',', ' '),
                'badge' => $quotesDelta ? [($quotesDelta > 0 ? '+' : '−') . abs($quotesDelta), $quotesDelta > 0] : null,
                'note' => $quotesDelta ? __('vs last month') : __('Same as last month')],
            ['label' => __('Total quoted (excl. VAT)'), 'value' => $eur($totalQuotedThisMonth),
                'badge' => $pctBadge($totalQuotedDeltaPct),
                'note' => $totalQuotedDeltaPct === null ? __('No data last month') : __('vs last month')],
            ['label' => __('Revenue won this month'), 'value' => $eur($revenueWonThisMonth),
                'badge' => $pctBadge($revenueWonDeltaPct),
                'note' => $revenueWonDeltaPct === null ? __('No data last month') : __('vs last month')],
            ['label' => __('Awaiting response'), 'value' => number_format($awaitingResponse, 0, ',', ' '),
                'badge' => $expiredThisWeek > 0 ? [(string) $expiredThisWeek, false] : null,
                'note' => $expiredThisWeek > 0 ? $expiredThisWeek . ' ' . __('expired this week') : __('None expired this week')],
        ];

        $hasTrend = collect($trend)->sum('sent') > 0;
        // Quote status donut — same colours as the brand dashboard.
        $statuses = $statuses ?? ['sent' => 0, 'won' => 0, 'draft' => 0, 'lost' => 0];
        $statusTotal = array_sum($statuses);
        $statusMeta = [
            'sent'  => [__('Sent'), '#0e4f79'],
            'won'   => [__('Won'), '#2b8fd6'],
            'draft' => [__('Drafts'), '#e59a1a'],
            'lost'  => [__('Lost'), '#e8e8eb'],
        ];

        // Quote status tags (Figma "Tag": 24 px, dot + 12 px medium label).
        $tags = [
            'draft' => [__('Draft'), 'bg-nq-muted border-nq-border text-nq-text-2', 'bg-nq-text-3'],
            'sent'  => [__('Sent'), 'bg-nq-info-bg border-[#d4e9fb] text-[#1d6aa5]', 'bg-nq-chart2'],
            'won'   => [__('Won'), 'bg-nq-success-bg border-nq-success-border text-nq-success-text', 'bg-nq-success'],
            'lost'  => [__('Lost'), 'bg-nq-error-bg border-[#fbd5d6] text-nq-error-text', 'bg-nq-error'],
        ];
        $quick = [
            ['plus', 'bg-nq-primary-bg text-primary-800', __('New quote'), __('Build a quote in under 2 minutes.'), route('quotes.create')],
            ['user-plus', 'bg-nq-info-bg text-nq-chart2', __('Add client'), __('Save a client to reuse on your quotes.'), route('clients.create')],
            ['sailboat', 'bg-nq-warning-bg text-nq-chart3', __('Catalogue'), __('Your boats, versions, options and prices.'), route('catalogue.models')],
            ['file-text', 'bg-nq-success-bg text-nq-success', __('All quotes'), __('Follow, resend and convert your quotes.'), route('quotes.index')],
        ];
        $initials = fn ($s) => mb_strtoupper(collect(preg_split('/\s+/', trim((string) $s)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('')) ?: '—';

        $card = 'bg-white border border-nq-border rounded-xl';
        $btn = 'h-9 inline-flex items-center gap-2 px-4 rounded-lg text-sm leading-[22px] font-medium transition-colors whitespace-nowrap';
        $btnSecondary = $btn . ' bg-white border border-nq-border text-nq-text hover:bg-nq-subtle';
        $cardTitle = 'text-base leading-6 font-medium text-nq-text';
        $link = 'inline-flex items-center gap-1 text-sm leading-[22px] font-medium text-primary-800 hover:underline';
    @endphp

    <div class="flex flex-col gap-4">
        {{-- Partner brand invitation --}}
        @if ($brandInvites->isNotEmpty())
            <div class="{{ $card }} px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 text-sm leading-[22px] text-nq-text">
                    <span class="w-8 h-8 shrink-0 rounded-lg bg-nq-primary-bg text-primary-800 flex items-center justify-center"><i class="ph-bold ph-handshake text-base"></i></span>
                    <span>{{ trans_choice('{1}A brand wants to share its catalogue with you.|[2,*]:count brands want to share their catalogue with you.', $brandInvites->count(), ['count' => $brandInvites->count()]) }}</span>
                </div>
                <a href="{{ $brandInvites->count() === 1 ? route('partner-brands.show', $brandInvites->first()->_id) : route('partner-brands.index') }}"
                    class="{{ $btn }} bg-primary-800 text-white hover:bg-nq-active">{{ __('See the invitation') }}</a>
            </div>
        @endif

        {{-- Catalogue update (only when there are pending updates) --}}
        @if ($pendingUpdatesCount > 0)
            <div class="{{ $card }} px-4 py-3 flex flex-wrap items-center gap-3">
                <span class="w-8 h-8 shrink-0 rounded-lg bg-nq-warning-bg text-nq-chart3 flex items-center justify-center"><i class="ph-bold ph-arrows-clockwise text-base"></i></span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm leading-[22px] font-medium text-nq-text">{{ __('Catalogue update available') }}{{ $pendingUpdatesBrand ? ' — ' . $pendingUpdatesBrand : '' }}</p>
                    <p class="text-xs leading-5 text-nq-text-3">{{ $pendingUpdatesCount }} {{ $pendingUpdatesCount === 1 ? __('change') : __('changes') }} {{ __('from the platform — review before applying') }}.</p>
                </div>
                <button class="{{ $btnSecondary }}">{{ __('Dismiss') }}</button>
                <button class="{{ $btn }} bg-primary-800 text-white hover:bg-nq-active">{{ __('Review & apply') }}</button>
            </div>
        @endif

        {{-- Page heading --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl leading-8 font-semibold tracking-[-0.24px] text-nq-text">{{ __('Hello :name', ['name' => $firstName]) }}</h2>
                    @if ($awaitingResponse > 0)
                        <span class="h-6 inline-flex items-center gap-1.5 px-2 rounded-md bg-nq-success-bg border border-nq-success-border text-xs leading-5 font-medium text-nq-success-text">
                            <span class="w-1.5 h-1.5 rounded-full bg-nq-success"></span>
                            {{ $awaitingResponse }} {{ mb_strtolower(__('Awaiting response')) }}
                        </span>
                    @endif
                </div>
                <p class="text-sm leading-[22px] text-nq-text-2">{{ now()->translatedFormat('l j F Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('clients.create') }}" class="{{ $btnSecondary }}"><i class="ph-bold ph-user-plus text-base"></i> {{ __('Add client') }}</a>
                <a href="{{ route('quotes.create') }}" class="{{ $btn }} bg-primary-800 text-white hover:bg-nq-active"><i class="ph-bold ph-plus text-base"></i> {{ __('New quote') }}</a>
            </div>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach ($kpis as $i => $k)
                <div class="rise {{ $card }} pt-4 px-4 pb-[30px] flex flex-col gap-6" style="--d: {{ $i }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm leading-[22px] font-medium text-nq-text-2">{{ $k['label'] }}</p>
                        @if ($k['badge'])
                            @php [$bLabel, $bUp] = $k['badge']; @endphp
                            <span class="inline-flex items-center gap-[3px] px-1.5 py-0.5 rounded-md border text-xs leading-5 font-medium whitespace-nowrap {{ $bUp ? 'bg-nq-success-bg border-nq-success text-nq-success-text' : 'bg-nq-error-bg border-nq-error text-nq-error-text' }}">
                                <i class="ph-bold {{ $bUp ? 'ph-trend-up' : 'ph-trend-down' }} text-xs"></i>{{ $bLabel }}</span>
                        @endif
                    </div>
                    <div>
                        <p class="text-2xl leading-8 font-semibold text-nq-text tabular-nums">{{ $k['value'] }}</p>
                        <p class="text-xs leading-5 text-nq-text-3">{{ $k['note'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Quotes per month + conversion --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <section class="rise xl:col-span-2 {{ $card }} p-4 flex flex-col gap-4" style="--d: 4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="{{ $cardTitle }}">{{ __('Quotes per month') }}</h3>
                    <div class="flex items-center gap-2">
                        <span class="text-xs leading-5 text-nq-text-3">{{ __('Total:') }}</span>
                        <span class="text-sm leading-[22px] font-medium text-nq-text tabular-nums">{{ collect($trend)->sum('sent') }}</span>
                    </div>
                </div>
                @if ($hasTrend)
                    <div class="relative h-[268px] rounded-[10px] bg-nq-subtle p-3"><canvas id="dealerTrend"></canvas></div>
                @else
                    <div class="rounded-[10px] bg-nq-subtle">
                        <x-app.empty-state icon="ri-bar-chart-2-line" size="sm" class="h-[268px]" :title="__('No quotes yet')"
                            :message="__('Start by creating a quote — it takes under 2 minutes.')" />
                    </div>
                @endif
            </section>

            <section class="rise {{ $card }} p-4 flex flex-col gap-4" style="--d: 5">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="{{ $cardTitle }}">{{ __('Quote status') }}</h3>
                    <span class="text-xs leading-5 text-nq-text-3">{{ __('Last 6 months') }}</span>
                </div>
                <div class="flex-1 rounded-[10px] bg-nq-subtle px-4 py-6 flex items-center justify-center">
                    @if ($statusTotal > 0)
                        <div class="w-full flex flex-col sm:flex-row xl:flex-col 2xl:flex-row items-center justify-center gap-4">
                            <div class="relative w-[156px] h-[156px] shrink-0"><canvas id="dealerStatus"></canvas>
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

        {{-- Top models + quick actions --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <section class="rise {{ $card }} p-4 flex flex-col gap-2" style="--d: 6">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="{{ $cardTitle }}">{{ __('Top quoted models') }}</h3>
                    <span class="text-xs leading-5 text-nq-text-3">{{ __('This month') }}</span>
                </div>
                <div>
                    @forelse ($topModels as $i => $m)
                        <div class="flex items-center gap-3 py-2">
                            <span class="w-8 h-8 shrink-0 rounded-full text-xs font-semibold flex items-center justify-center {{ $i % 3 === 2 ? 'bg-nq-accent-bg text-nq-accent-text' : 'bg-nq-primary-bg text-primary-800' }}">{{ $initials($m['name']) }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm leading-[22px] font-medium text-nq-text truncate">{{ $m['name'] }}</p>
                                <p class="text-xs leading-5 text-nq-text-3 truncate">{{ $m['brand'] }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-nq-subtle border border-nq-border text-xs leading-5 font-medium text-nq-text-2 whitespace-nowrap"><i class="ph-bold ph-check text-xs"></i>{{ trans_choice('{1}:count quote|[2,*]:count quotes', $m['count'], ['count' => $m['count']]) }}</span>
                        </div>
                    @empty
                        <x-app.empty-state icon="ri-trophy-line" size="sm" :title="__('No quotes this month')" :message="__('Top models will appear here as you build quotes.')" />
                    @endforelse
                </div>
            </section>

            <section class="rise {{ $card }} p-4 flex flex-col gap-2" style="--d: 7">
                <h3 class="{{ $cardTitle }}">{{ __('Quick actions') }}</h3>
                <ul>
                    @foreach ($quick as [$icon, $tone, $title, $text, $href])
                        <li class="{{ $loop->last ? '' : 'border-b border-nq-border-2' }}">
                            <a href="{{ $href }}" class="group flex items-center gap-3.5 py-3">
                                <span class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center {{ $tone }}"><i class="ph-bold ph-{{ $icon }} text-base"></i></span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-base leading-6 text-nq-text group-hover:text-primary-800">{{ $title }}</p>
                                    <p class="text-sm leading-[22px] text-nq-text-2">{{ $text }}</p>
                                </div>
                                <i class="ph-bold ph-arrow-right text-base text-nq-text-3 group-hover:text-primary-800"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        {{-- Recent quotes --}}
        <section class="rise {{ $card }} overflow-hidden" style="--d: 8">
            <div class="p-4 flex items-center justify-between gap-2">
                <h3 class="{{ $cardTitle }}">{{ __('Recent quotes') }}</h3>
                <a href="{{ route('quotes.index') }}" class="{{ $link }}">{{ __('View all') }} <i class="ph-bold ph-arrow-right text-base"></i></a>
            </div>
            @if ($recent->isEmpty())
                <x-app.empty-state icon="ri-file-list-3-line" :title="__('No quotes yet')" :message="__('Start by creating a quote — it takes under 2 minutes.')"
                    :ctaLabel="__('New quote')" ctaHref="{{ route('quotes.create') }}" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead>
                            <tr class="bg-nq-subtle text-left text-xs leading-5 text-nq-text-2">
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2">{{ __('Client') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[130px]">{{ __('Ref.') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2">{{ __('Boat') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[150px] text-right">{{ __('Amount excl. VAT') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[140px]">{{ __('Status') }}</th>
                                <th class="h-9 px-4 border-y border-nq-border-2 w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $q)
                                @php
                                    $first = $q->client_snapshot['first_name'] ?? '';
                                    $last  = $q->client_snapshot['last_name']  ?? '';
                                    $clientName = trim($first . ' ' . $last) ?: __('Guest');
                                    $ini = mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1)) ?: 'G';
                                    [$tLabel, $tCls, $tDot] = $tags[$q->status] ?? $tags['draft'];
                                    $rowBorder = $loop->last ? '' : 'border-b border-nq-border-2';
                                @endphp
                                <tr class="hover:bg-nq-subtle cursor-pointer" data-href="{{ route('quotes.show', $q->_id) }}"
                                    onclick="if (!event.target.closest('a, button, form, input')) window.location = this.dataset.href">
                                    <td class="h-14 px-4 {{ $rowBorder }}">
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 h-6 shrink-0 rounded-full bg-nq-primary-bg text-primary-800 text-[10px] font-semibold flex items-center justify-center">{{ $ini }}</span>
                                            <div class="min-w-0">
                                                <div class="text-sm leading-[22px] font-medium text-nq-text truncate">{{ $clientName }}</div>
                                                @if (! empty($q->client_snapshot['email']))
                                                    <div class="text-xs leading-5 text-nq-text-3 truncate">{{ $q->client_snapshot['email'] }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="h-14 px-4 whitespace-nowrap {{ $rowBorder }}">
                                        <a href="{{ route('quotes.show', $q->_id) }}" class="font-mono text-xs px-1.5 py-0.5 rounded-[5px] bg-nq-muted border border-nq-border text-nq-text-2 hover:text-primary-800">{{ $q->number }}</a>
                                    </td>
                                    <td class="h-14 px-4 {{ $rowBorder }}">
                                        <div class="text-sm leading-[22px] text-nq-text">{{ $q->boatLabel() ?: '—' }}</div>
                                        @if (! empty($q->model_snapshot['brand']))
                                            <div class="text-xs leading-5 text-nq-text-3">{{ $q->model_snapshot['brand'] }}</div>
                                        @endif
                                    </td>
                                    <td class="h-14 px-4 text-right whitespace-nowrap {{ $rowBorder }}">
                                        <div class="text-sm leading-[22px] font-medium text-nq-text tabular-nums">{{ $eur($q->totals['total_ht'] ?? 0) }}</div>
                                        <div class="text-xs leading-5 text-nq-text-3 tabular-nums">{{ __('VAT incl.') }} {{ $eur($q->totals['total_ttc'] ?? 0) }}</div>
                                    </td>
                                    <td class="h-14 px-4 {{ $rowBorder }}">
                                        <span class="h-6 inline-flex items-center gap-1.5 px-2 rounded-md border text-xs leading-5 font-medium whitespace-nowrap {{ $tCls }}"><span class="w-1.5 h-1.5 rounded-full {{ $tDot }}"></span>{{ $tLabel }}</span>
                                    </td>
                                    <td class="h-14 px-4 text-right {{ $rowBorder }}">
                                        <a href="{{ route('quotes.show', $q->_id) }}" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-nq-text-3 hover:bg-nq-muted hover:text-nq-text" title="{{ __('Open') }}"><i class="ph-bold ph-dots-three-vertical text-base"></i></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    @push('scripts')
        <script>
            // One series only: quotes created per month. Past months hatched,
            // the current month in navy with its value on top (Figma node 19:228).
            document.addEventListener('DOMContentLoaded', () => {
                const el = document.getElementById('dealerTrend');
                if (!el) return;
                const draw = () => {
                    if (!window.Chart || el.dataset.drawn) return;
                    el.dataset.drawn = '1';
                    const trend = @json($trend);
                    const data = trend.map(d => d.sent);
                    const last = data.length - 1;
                    const hatch = document.createElement('canvas'); hatch.width = hatch.height = 7;
                    const h = hatch.getContext('2d'); h.fillStyle = '#f0f0f2'; h.fillRect(0, 0, 7, 7);
                    h.strokeStyle = '#e4e4e7'; h.lineWidth = 1.5; h.beginPath(); h.moveTo(0, 7); h.lineTo(7, 0); h.stroke();
                    const pattern = el.getContext('2d').createPattern(hatch, 'repeat');
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
                    new Chart(el, {
                        type: 'bar',
                        data: { labels: trend.map(d => d.label), datasets: [{ label: @js(__('Quotes')), data,
                            backgroundColor: data.map((_, i) => i === last ? '#0e4f79' : pattern),
                            hoverBackgroundColor: data.map((_, i) => i === last ? '#093a5c' : '#e8e8eb'),
                            borderRadius: 8, borderSkipped: false, maxBarThickness: 84, categoryPercentage: 0.8, barPercentage: 0.95 }] },
                        options: {
                            responsive: true, maintainAspectRatio: false, layout: { padding: { top: 38 } },
                            animation: { duration: 800, easing: 'easeOutQuart', delay: c => c.type === 'data' ? c.dataIndex * 60 : 0 },
                            plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0b0b0d', padding: 10, cornerRadius: 8, titleFont: { family: 'Geist' }, bodyFont: { family: 'Geist' } } },
                            scales: {
                                x: { grid: { display: false }, border: { display: false }, ticks: { color: '#73737c', font: { family: 'Geist', size: 12 } } },
                                y: { beginAtZero: true, border: { display: false }, grid: { display: false }, ticks: { color: '#73737c', font: { family: 'Geist', size: 12 }, precision: 0, maxTicksLimit: 6 } },
                            },
                        },
                        plugins: [valuePill],
                    });
                };
                window.Chart ? draw() : window.addEventListener('load', draw, { once: true });
            });

            document.addEventListener('DOMContentLoaded', () => {
                const el = document.getElementById('dealerStatus');
                if (!el) return;
                const draw = () => {
                    if (!window.Chart || el.dataset.drawn) return;
                    el.dataset.drawn = '1';
                    const st = @json($statuses);
                    new Chart(el, {
                        type: 'doughnut',
                        data: { labels: [@js(__('Sent')), @js(__('Won')), @js(__('Drafts')), @js(__('Lost'))],
                            datasets: [{ data: [st.sent, st.won, st.draft, st.lost], backgroundColor: ['#0e4f79', '#2b8fd6', '#e59a1a', '#e8e8eb'], borderWidth: 4, borderColor: '#fbfbfb', borderRadius: 6, hoverOffset: 4 }] },
                        options: { responsive: true, maintainAspectRatio: false, cutout: '62%', animation: { animateRotate: true, duration: 900 },
                            plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0b0b0d', padding: 10, cornerRadius: 8, titleFont: { family: 'Geist' }, bodyFont: { family: 'Geist' } } } },
                    });
                };
                window.Chart ? draw() : window.addEventListener('load', draw, { once: true });
            });
        </script>
    @endpush
</x-app-layout>
