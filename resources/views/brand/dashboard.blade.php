<x-app-layout :title="__('Dashboard')" :header="__('Dashboard')">
    @include('brand.partials.flash')
    @php
        $eur = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' €';
        $firstName = explode(' ', trim(auth()->user()->name))[0];
        $periods = ['month' => __('This month'), '6m' => __('Last 6 months'), 'year' => __('Last 12 months')];
        $q = fn (array $over) => route('brand.dashboard', array_filter(array_merge(['period' => $period, 'brand' => $brandId, 'dealer' => $dealerId], $over), fn ($v) => $v !== null && $v !== ''));
        $stepsDone = collect($steps)->where('done', true)->count();
        $hasData = collect($series)->sum('count') > 0;
        $statusTotal = $statuses->sum();
        $statusMeta = [
            'sent'  => [__('Sent'), '#0e4f79', 'bg-primary-800'],
            'won'   => [__('Won'), '#3b8fd9', 'bg-sky-500'],
            'draft' => [__('Drafts'), '#e8a33d', 'bg-amber-500'],
            'lost'  => [__('Lost'), '#e5e7eb', 'bg-gray-300'],
        ];
        $funnelMeta = [
            'created' => ['ri-file-text-line', 'bg-primary-50 text-primary-800', __('Quotes created'), __('Configured by your dealerships in the configurator')],
            'sent'    => ['ri-send-plane-line', 'bg-sky-50 text-sky-700', __('Sent to the client'), __('PDF shared with the end client')],
            'nego'    => ['ri-hand-coin-line', 'bg-amber-50 text-amber-700', __('In negotiation'), __('Sent with a discount being discussed')],
            'won'     => ['ri-trophy-line', 'bg-emerald-50 text-emerald-700', __('Signed'), __('Order confirmed by the dealership')],
        ];
        $funnelBase = max(1, $funnel[0]['n']);
        $periodLabel = $period === 'month' ? now()->translatedFormat('F Y') : now()->startOfMonth()->subMonthsNoOverflow(\App\Http\Controllers\Brand\DashboardController::PERIODS[$period])->translatedFormat('M') . ' – ' . now()->translatedFormat('M Y');
        $targetPct = $periodTarget > 0 ? min(100, round($periodQuoted / $periodTarget * 100, 1)) : null;
        $statusPill = function ($r) {
            if ($r['status'] === 'sent' && $r['discount_pct'] > 0) return [__('In negotiation'), 'bg-amber-50 text-amber-700 ring-amber-200', 'bg-amber-500'];
            return match ($r['status']) {
                'won'   => [__('Won'), 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'bg-emerald-500'],
                'lost'  => [__('Lost'), 'bg-red-50 text-red-700 ring-red-200', 'bg-red-500'],
                'draft' => [__('Draft'), 'bg-gray-100 text-gray-600 ring-gray-200', 'bg-gray-400'],
                default => [__('Sent'), 'bg-sky-50 text-sky-700 ring-sky-200', 'bg-sky-500'],
            };
        };
        $initials = fn ($s) => mb_strtoupper(collect(preg_split('/\s+/', trim((string) $s)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('')) ?: '—';
    @endphp

    {{-- Greeting + actions --}}
    <div class="mb-5 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-semibold text-gray-900">{{ __('Hello :name', ['name' => $firstName]) }}</h2>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200 px-2.5 py-0.5 text-xs font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    {{ trans_choice('{0}no active dealership|{1}:count active dealership|[2,*]:count active dealerships', $connected, ['count' => $connected]) }}
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-500">{{ __('Follow your network\'s quotes, your models and your price levels in one place.') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('brand.dealers.index', ['invite' => 1]) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50">
                <i class="ri-user-add-line"></i> {{ __('Invite dealers') }}</a>
            <a href="{{ route($account->isEngine() ? 'brand.engines.create' : 'brand.catalogue.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary-800 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-900">
                <i class="ri-add-line"></i> {{ $account->isEngine() ? __('Add engine') : __('Add model') }}</a>
        </div>
    </div>

    {{-- Getting started — until every step is done --}}
    @if ($stepsDone < count($steps))
        <section class="rise mb-5 rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('Getting started') }} <span class="font-normal text-gray-500">· {{ __(':done of :total steps done', ['done' => $stepsDone, 'total' => count($steps)]) }}</span></h3>
                <div class="w-40 h-1.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full bg-emerald-500 grow-x" style="width: {{ round($stepsDone / count($steps) * 100) }}%"></div></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2">
                @foreach ($steps as $i => $s)
                    <a href="{{ $s['href'] }}" class="flex items-center gap-3 rounded-lg border px-3 py-2.5 {{ $s['done'] ? 'border-emerald-200 bg-emerald-50/50' : 'border-gray-200 hover:border-primary-300' }}">
                        <span class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center {{ $s['done'] ? 'bg-emerald-500 text-white' : 'bg-primary-50 text-primary-800' }}"><i class="{{ $s['done'] ? 'ri-check-line' : $s['icon'] }}"></i></span>
                        <span class="text-sm font-medium {{ $s['done'] ? 'text-emerald-800' : 'text-gray-900' }}">{{ $i + 1 }}. {{ $s['title'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Filters --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div x-data="{ open: false }" class="relative">
            <button type="button" @click="open = !open" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50">
                <i class="ri-equalizer-line"></i> {{ __('Filters') }}
                @if ($dealerId) <span class="w-1.5 h-1.5 rounded-full bg-primary-800"></span> @endif
            </button>
            <div x-show="open" x-cloak x-transition @click.outside="open = false" class="absolute z-20 mt-1 w-64 rounded-xl border border-gray-200 bg-white p-1 shadow-lg max-h-80 overflow-y-auto">
                <p class="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ __('Dealership') }}</p>
                <a href="{{ $q(['dealer' => null]) }}" class="block rounded-lg px-3 py-2 text-sm {{ $dealerId === '' ? 'bg-primary-50 text-primary-800 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">{{ __('All dealers') }}</a>
                @foreach ($dealerOptions as $id => $name)
                    <a href="{{ $q(['dealer' => $id]) }}" class="block rounded-lg px-3 py-2 text-sm truncate {{ $dealerId === (string) $id ? 'bg-primary-50 text-primary-800 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">{{ $name }}</a>
                @endforeach
            </div>
        </div>
        <div x-data="{ open: false }" class="relative">
            <button type="button" @click="open = !open" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50">
                <i class="ri-calendar-line"></i> {{ $periods[$period] }} <i class="ri-arrow-down-s-line text-gray-400"></i>
            </button>
            <div x-show="open" x-cloak x-transition @click.outside="open = false" class="absolute z-20 mt-1 w-52 rounded-xl border border-gray-200 bg-white p-1 shadow-lg">
                @foreach ($periods as $p => $label)
                    <a href="{{ $q(['period' => $p]) }}" class="block rounded-lg px-3 py-2 text-sm {{ $period === $p ? 'bg-primary-50 text-primary-800 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        @if ($brands->count() > 1 && ! $account->isEngine())
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50">
                    <i class="ri-price-tag-3-line"></i> {{ $brandId ? ($brands->first(fn ($b) => (string) $b->_id === $brandId)?->name ?? __('All brands')) : __('All brands') }} <i class="ri-arrow-down-s-line text-gray-400"></i>
                </button>
                <div x-show="open" x-cloak x-transition @click.outside="open = false" class="absolute z-20 mt-1 w-56 rounded-xl border border-gray-200 bg-white p-1 shadow-lg">
                    <a href="{{ $q(['brand' => null]) }}" class="block rounded-lg px-3 py-2 text-sm {{ $brandId === '' ? 'bg-primary-50 text-primary-800 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">{{ __('All brands') }}</a>
                    @foreach ($brands as $b)
                        <a href="{{ $q(['brand' => (string) $b->_id]) }}" class="block rounded-lg px-3 py-2 text-sm {{ $brandId === (string) $b->_id ? 'bg-primary-50 text-primary-800 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">{{ $b->name }}</a>
                    @endforeach
                </div>
            </div>
        @endif
        <a href="{{ route('brand.export', array_filter(['period' => $period, 'brand' => $brandId, 'dealer' => $dealerId])) }}"
            class="ml-auto inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50">
            <i class="ri-share-box-line"></i> {{ __('Export') }}</a>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
        @foreach ($kpis as $i => $k)
            @php
                $up = $k['delta'] !== null && $k['delta'] >= 0;
                $deltaLabel = $k['delta'] === null ? null : ($k['delta'] >= 0 ? '+' : '−') . abs($k['delta']) . $k['unit'];
            @endphp
            <div class="rise rounded-xl border border-gray-200 bg-white p-4" style="--d: {{ $i }}">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm text-gray-600">{{ $k['label'] }}</p>
                    @if ($deltaLabel)
                        <span class="inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $up ? 'text-emerald-700 ring-emerald-300 bg-emerald-50/60' : 'text-red-600 ring-red-300 bg-red-50/60' }}">
                            <i class="{{ $up ? 'ri-arrow-right-up-line' : 'ri-arrow-right-down-line' }}"></i>{{ $deltaLabel }}</span>
                    @endif
                </div>
                @if ($k['value'] === null)
                    <p class="mt-5 text-[28px] font-semibold text-gray-300 leading-none">—</p>
                @else
                    <p class="mt-5 text-[28px] font-semibold text-gray-900 leading-none tabular-nums"
                        x-data="countUp({{ (float) $k['value'] }}, { suffix: @js(! empty($k['money']) ? ' €' : ($k['suffix'] ?? '')) })" x-text="display">{{ ! empty($k['money']) ? $eur($k['value']) : $k['value'] . ($k['suffix'] ?? '') }}</p>
                @endif
                <p class="mt-2 text-xs text-gray-500">{{ $k['note'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Pipeline + status --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-4">
        <section class="rise xl:col-span-2 rounded-xl border border-gray-200 bg-white p-4" style="--d: 4"
            x-data="pipelineChart(@js($series), @js($chartTotals), @js($monthlyTarget))">
            <h3 class="text-[15px] font-semibold text-gray-900 mb-3">{{ __('Quote pipeline (HT)') }}</h3>
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div class="inline-flex rounded-lg bg-gray-100 p-1 text-sm">
                    @foreach (['quoted' => __('Quoted'), 'won' => __('Won'), 'count' => __('No. of quotes'), 'discount' => __('Avg. discount')] as $tab => $label)
                        <button type="button" @click="show('{{ $tab }}')" class="rounded-md px-3 py-1 transition"
                            :class="tab === '{{ $tab }}' ? 'bg-white text-gray-900 font-medium shadow-sm' : 'text-gray-600 hover:text-gray-900'">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <span class="text-gray-500">{{ __('Total:') }}</span>
                    <span class="font-semibold text-gray-900 tabular-nums" x-text="totalLabel()"></span>
                    <template x-if="totals[tab][1] !== null">
                        <span class="rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset" :class="totals[tab][1] >= 0 ? 'text-emerald-700 ring-emerald-300' : 'text-red-600 ring-red-300'"
                            x-text="(totals[tab][1] >= 0 ? '+' : '−') + Math.abs(totals[tab][1]) + ' %'"></span>
                    </template>
                </div>
            </div>
            @if ($hasData)
                <div class="relative h-64 rounded-lg bg-gray-50/70 p-2"><canvas x-ref="canvas"></canvas></div>
            @else
                <div class="rounded-lg bg-gray-50/70">
                    <x-app.empty-state icon="ri-bar-chart-2-line" size="sm" class="h-64" :title="__('No data yet')"
                        :message="__('As soon as your dealers make quotes with your products, they appear here month by month.')" />
                </div>
            @endif
        </section>

        <section class="rise rounded-xl border border-gray-200 bg-white p-4" style="--d: 5">
            <h3 class="text-[15px] font-semibold text-gray-900 mb-3">{{ __('Quote status') }}</h3>
            <div class="rounded-lg bg-gray-50/70 p-4 h-[calc(100%-2.25rem)] flex items-center">
                @if ($statusTotal > 0)
                    <div class="w-full flex flex-col sm:flex-row xl:flex-col 2xl:flex-row items-center gap-4">
                        <div class="relative w-40 h-40 shrink-0"><canvas id="brandStatus"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-2xl font-semibold text-gray-900">{{ $statusTotal }}</span><span class="text-xs text-gray-500">{{ __('quotes') }}</span>
                            </div>
                        </div>
                        <ul class="space-y-2 text-sm">
                            @foreach ($statusMeta as $s => [$label, $color])
                                <li class="inline-flex w-full items-center gap-2 rounded-md border border-gray-200 bg-white px-2.5 py-1.5">
                                    <span class="w-0.5 h-3.5 rounded-full" style="background: {{ $color === '#e5e7eb' ? '#9ca3af' : $color }}"></span>
                                    <span class="font-medium text-gray-900">{{ $label }} :</span>
                                    <span class="text-gray-500">{{ round($statuses[$s] / $statusTotal * 100) }} % · {{ $statuses[$s] }}</span>
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

    {{-- Funnel + target / best dealerships --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-4">
        <section class="rise rounded-xl border border-gray-200 bg-white p-4" style="--d: 6">
            <h3 class="text-[15px] font-semibold text-gray-900 mb-2">{{ $period === '6m' ? __('Semester funnel') : __('Funnel') }}</h3>
            <ul class="divide-y divide-gray-100">
                @foreach ($funnel as $f)
                    @php [$icon, $tone, $title, $text] = $funnelMeta[$f['key']]; @endphp
                    <li class="flex items-start gap-3 py-3.5">
                        <span class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center {{ $tone }}"><i class="{{ $icon }}"></i></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-medium text-gray-900">{{ $title }}</p>
                                <span class="font-semibold text-gray-900 tabular-nums">{{ $f['n'] }}</span>
                            </div>
                            <p class="text-sm text-gray-500">{{ $text }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ $periodLabel }} · {{ round($f['n'] / $funnelBase * 100) }} %</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="space-y-4">
            {{-- Target --}}
            <section class="rise rounded-xl border border-gray-200 bg-white p-4" style="--d: 7" x-data="{ edit: {{ $monthlyTarget > 0 ? 'false' : 'true' }} }">
                <div class="flex items-center justify-between">
                    <h3 class="text-[15px] font-semibold text-gray-900">{{ $period === '6m' ? __('Semester target (HT)') : __('Period target (HT)') }}</h3>
                    <button type="button" x-show="!edit" @click="edit = true" class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700" title="{{ __('Edit targets') }}"><i class="ri-pencil-line"></i></button>
                </div>
                @if ($targetPct !== null)
                    <div x-show="!edit">
                        <p class="mt-2 flex items-baseline gap-2">
                            <span class="text-[28px] font-semibold text-gray-900 tabular-nums">{{ number_format($targetPct, 1, ',', ' ') }} %</span>
                            <span class="text-sm text-gray-500">{{ $periodQuoted >= $periodTarget ? __('target reached') : __(':v remaining', ['v' => $eur($periodTarget - $periodQuoted)]) }}</span>
                        </p>
                        <div class="mt-3 flex h-6 gap-1 overflow-hidden">
                            <div class="grow-x h-full rounded-md bg-primary-800" style="width: {{ $targetPct }}%"></div>
                            @if ($targetPct < 100)
                                <div class="h-full flex-1 rounded-md" style="background: repeating-linear-gradient(135deg, #e5e7eb 0 6px, #f3f4f6 6px 12px)"></div>
                            @endif
                        </div>
                        <div class="mt-2 flex justify-between text-xs">
                            <span class="text-gray-500">{{ __('Quoted:') }} {{ $eur($periodQuoted) }}</span>
                            <span class="font-medium text-gray-900">{{ __('Target:') }} {{ $eur($periodTarget) }}</span>
                        </div>
                    </div>
                @endif
                <form x-show="edit" x-cloak method="POST" action="{{ route('brand.targets') }}" class="mt-3 space-y-3">
                    @csrf
                    <p class="text-sm text-gray-500">{{ __('Set your own targets: they draw the target line on the chart and fill this gauge.') }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-xs font-medium text-gray-600">{{ __('Quoted per month (€ HT)') }}
                            <input type="number" name="monthly_quoted" min="0" step="1000" value="{{ $monthlyTarget ?: '' }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800"></label>
                        <label class="text-xs font-medium text-gray-600">{{ __('Conversion rate (%)') }}
                            <input type="number" name="win_rate" min="0" max="100" step="1" value="{{ $rateTarget ?? '' }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800"></label>
                    </div>
                    <div class="flex justify-end gap-2">
                        @if ($monthlyTarget > 0) <button type="button" @click="edit = false" class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</button> @endif
                        <button class="rounded-lg bg-primary-800 px-3.5 py-1.5 text-sm font-semibold text-white hover:bg-primary-900">{{ __('Save targets') }}</button>
                    </div>
                </form>
            </section>

            {{-- Best dealerships --}}
            <section class="rise rounded-xl border border-gray-200 bg-white p-4" style="--d: 8">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-[15px] font-semibold text-gray-900">{{ __('Best dealerships') }}</h3>
                    <a href="{{ route('brand.dealers.index') }}" class="text-sm font-medium text-primary-800 hover:underline">{{ __('See all') }} <i class="ri-arrow-right-line"></i></a>
                </div>
                @forelse ($topDealers as $d)
                    <div class="flex items-center gap-3 py-2.5">
                        <span class="w-9 h-9 shrink-0 rounded-full bg-primary-50 text-primary-800 text-xs font-semibold flex items-center justify-center">{{ $initials($d['name']) }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 truncate">{{ $d['name'] }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ collect([$d['city'], $d['level']])->filter()->implode(' · ') }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-700"><i class="ri-check-line text-gray-400"></i>{{ trans_choice('{1}:count quote|[2,*]:count quotes', $d['quotes'], ['count' => $d['quotes']]) }}</span>
                    </div>
                @empty
                    <x-app.empty-state icon="ri-store-2-line" size="sm" :title="__('No data yet')" :message="__('Your most active dealerships will rank here.')" />
                @endforelse
            </section>
        </div>
    </div>

    {{-- Latest quotes --}}
    <section class="rise rounded-xl border border-gray-200 bg-white overflow-hidden" style="--d: 9">
        <div class="px-4 py-3.5 flex items-center justify-between">
            <h3 class="text-[15px] font-semibold text-gray-900">{{ __('Latest quotes') }}</h3>
            <a href="{{ route('brand.quotes.index') }}" class="text-sm font-medium text-primary-800 hover:underline">{{ __('All quotes') }} <i class="ri-arrow-right-line"></i></a>
        </div>
        @if ($latest->isEmpty())
            <x-app.empty-state icon="ri-file-list-3-line" size="sm" :title="__('No quotes yet')" :message="__('Quotes your dealers make with your products appear here. End clients stay anonymous.')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs text-gray-500">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">{{ __('Model') }}</th>
                            <th class="px-4 py-2.5 font-medium">{{ __('Dealership') }}</th>
                            <th class="px-4 py-2.5 font-medium">{{ __('Client') }}</th>
                            <th class="px-4 py-2.5 font-medium text-right">{{ __('Discount') }}</th>
                            <th class="px-4 py-2.5 font-medium text-right">{{ __('Amount HT') }}</th>
                            <th class="px-4 py-2.5 font-medium">{{ __('Status') }}</th>
                            <th class="px-4 py-2.5 font-medium">{{ __('Salesperson') }}</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($latest as $r)
                            @php [$sLabel, $sCls, $sDot] = $statusPill($r); @endphp
                            <tr class="hover:bg-gray-50/70">
                                <td class="px-4 py-3">
                                    <a href="{{ route('brand.quotes.show', $r['id']) }}" class="font-medium text-gray-900 hover:text-primary-800">{{ $r['boat'] ?: '—' }}</a>
                                    <div class="text-xs text-gray-500 truncate max-w-[220px]">{{ implode(', ', $r['fitted_engines']) ?: $r['version'] }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-primary-50 text-primary-800 text-[10px] font-semibold flex items-center justify-center">{{ $initials($r['dealer']) }}</span><span class="text-gray-900">{{ $r['dealer'] }}</span></span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $r['client'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $r['discount_pct'] > 0 ? '−' . rtrim(rtrim(number_format($r['discount_pct'], 1, ',', ''), '0'), ',') . ' %' : '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900">{{ $eur($r['value_ht']) }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $sCls }}"><span class="w-1.5 h-1.5 rounded-full {{ $sDot }}"></span>{{ $sLabel }}</span></td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-amber-50 text-amber-700 text-[10px] font-semibold flex items-center justify-center">{{ $initials($r['salesperson']) }}</span><span class="text-gray-700">{{ $r['salesperson'] ?: '—' }}</span></span>
                                </td>
                                <td class="px-2 py-3 text-right">
                                    <a href="{{ route('brand.quotes.show', $r['id']) }}" class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700" title="{{ __('Open') }}"><i class="ri-more-2-fill"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @push('scripts')
        <script>
            // Past months hatched grey, the current month in navy with its value on top,
            // the monthly target as a dashed line (amount tabs only).
            function pipelineChart(series, totals, target) {
                return {
                    tab: 'quoted', series, totals, target, chart: null,
                    eur(v) { return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(v)) + ' €'; },
                    fmt(v) { return this.tab === 'count' ? String(v) : this.tab === 'discount' ? v + ' %' : this.eur(v); },
                    totalLabel() { return this.fmt(this.totals[this.tab][0]); },
                    init() {
                        if (!this.$refs.canvas) return;
                        // Chart.js comes in a deferred module that can land after Alpine starts.
                        const go = () => { if (window.Chart && !this.chart) this.draw(); };
                        window.Chart ? this.$nextTick(go) : window.addEventListener('load', go, { once: true });
                    },
                    show(t) { this.tab = t; if (this.chart) { this.chart.destroy(); this.draw(); } },
                    draw() {
                        const ctx = this.$refs.canvas.getContext('2d');
                        const hatch = document.createElement('canvas'); hatch.width = hatch.height = 8;
                        const h = hatch.getContext('2d'); h.fillStyle = '#ededed'; h.fillRect(0, 0, 8, 8);
                        h.strokeStyle = '#e2e2e2'; h.lineWidth = 2; h.beginPath(); h.moveTo(0, 8); h.lineTo(8, 0); h.stroke();
                        const pattern = ctx.createPattern(hatch, 'repeat');
                        const data = this.series.map(s => s[this.tab]);
                        const last = data.length - 1;
                        const showTarget = this.target > 0 && this.tab === 'quoted';
                        const self = this;
                        const overlay = {
                            id: 'overlay',
                            afterDatasetsDraw(c) {
                                const g = c.ctx, meta = c.getDatasetMeta(0), bar = meta.data[last];
                                if (bar && data[last] > 0) {
                                    const label = self.fmt(data[last]); g.save(); g.font = '600 12px Geist, sans-serif';
                                    const w = g.measureText(label).width + 16, x = bar.x - w / 2, y = bar.y - 30;
                                    g.fillStyle = '#fff'; g.strokeStyle = '#e5e7eb'; g.beginPath(); g.roundRect(x, y, w, 22, 6); g.fill(); g.stroke();
                                    g.fillStyle = '#111827'; g.textAlign = 'center'; g.textBaseline = 'middle'; g.fillText(label, bar.x, y + 11); g.restore();
                                }
                                if (showTarget) {
                                    const y = c.scales.y.getPixelForValue(self.target), a = c.chartArea; g.save();
                                    g.setLineDash([4, 4]); g.strokeStyle = '#6b7280'; g.beginPath(); g.moveTo(a.left, y); g.lineTo(a.right, y); g.stroke();
                                    g.setLineDash([]); g.fillStyle = '#4b5563'; g.font = '11px Geist, sans-serif';
                                    g.fillText(@js(__('Target')) + ' : ' + Math.round(self.target / 1000) + ' k€', a.left + 8, y - 6); g.restore();
                                }
                            },
                        };
                        this.chart = new Chart(this.$refs.canvas, {
                            type: 'bar',
                            data: { labels: this.series.map(s => s.label), datasets: [{ data,
                                backgroundColor: data.map((_, i) => i === last ? '#0e4f79' : pattern),
                                hoverBackgroundColor: data.map((_, i) => i === last ? '#093a5c' : '#e5e7eb'),
                                borderRadius: 6, maxBarThickness: 84, categoryPercentage: 0.8, barPercentage: 0.95 }] },
                            options: {
                                responsive: true, maintainAspectRatio: false, layout: { padding: { top: 34 } },
                                animation: { duration: 800, easing: 'easeOutQuart', delay: c => c.type === 'data' ? c.dataIndex * 60 : 0 },
                                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8, callbacks: { label: c => self.fmt(c.raw) } } },
                                scales: {
                                    x: { grid: { display: false }, border: { display: false }, ticks: { color: '#6b7280' } },
                                    y: { beginAtZero: true, suggestedMax: showTarget ? this.target * 1.2 : undefined, border: { display: false }, grid: { display: false },
                                        ticks: { color: '#6b7280', precision: 0, callback: v => this.tab === 'count' ? v : this.tab === 'discount' ? v + ' %' : (v >= 1000 ? Math.round(v / 1000) + 'k' : v) } },
                                },
                            },
                            plugins: [overlay],
                        });
                    },
                };
            }

            document.addEventListener('DOMContentLoaded', () => {
                const s = document.getElementById('brandStatus');
                if (!s || !window.Chart) return;
                const st = @json($statuses);
                new Chart(s, {
                    type: 'doughnut',
                    data: { labels: [@js(__('Sent')), @js(__('Won')), @js(__('Drafts')), @js(__('Lost'))],
                        datasets: [{ data: [st.sent, st.won, st.draft, st.lost], backgroundColor: ['#0e4f79', '#3b8fd9', '#e8a33d', '#e5e7eb'], borderWidth: 3, borderColor: '#f9fafb', borderRadius: 4, hoverOffset: 4 }] },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '68%', animation: { animateRotate: true, duration: 900 },
                        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8 } } },
                });
            });
        </script>
    @endpush
</x-app-layout>
