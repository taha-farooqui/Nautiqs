<x-app-layout :title="__('Dashboard')" :header="__('Dashboard')">
    @include('brand.partials.flash')
    @php
        // Built to Clement's Figma (frame 19:3). Sizes and colours are the --nq-* tokens.
        $eur = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' €';
        $firstName = explode(' ', trim(auth()->user()->name))[0];
        $periods = ['month' => __('This month'), '6m' => __('Last 6 months'), 'year' => __('Last 12 months')];
        $q = fn (array $over) => route('brand.dashboard', array_filter(array_merge(['period' => $period, 'brand' => $brandId, 'dealer' => $dealerId], $over), fn ($v) => $v !== null && $v !== ''));
        $stepsDone = collect($steps)->where('done', true)->count();
        $hasData = collect($series)->sum('count') > 0;
        $statusTotal = $statuses->sum();
        $statusMeta = [
            'sent'  => [__('Sent'), '#0e4f79'],
            'won'   => [__('Won'), '#2b8fd6'],
            'draft' => [__('Drafts'), '#e59a1a'],
            'lost'  => [__('Lost'), '#e8e8eb'],
        ];
        $funnelMeta = [
            'created' => ['file-text', 'bg-nq-primary-bg text-primary-800', __('Quotes created'), __('Configured by your dealerships in the configurator')],
            'sent'    => ['paper-plane-tilt', 'bg-nq-info-bg text-nq-chart2', __('Sent to the client'), __('PDF shared with the end client')],
            'nego'    => ['handshake', 'bg-nq-warning-bg text-nq-chart3', __('In negotiation'), __('Sent with a discount being discussed')],
            'won'     => ['trophy', 'bg-nq-success-bg text-nq-success', __('Signed'), __('Order confirmed by the dealership')],
        ];
        $funnelBase = max(1, $funnel[0]['n']);
        $periodLabel = $period === 'month' ? now()->translatedFormat('F Y') : now()->startOfMonth()->subMonthsNoOverflow(\App\Http\Controllers\Brand\DashboardController::PERIODS[$period])->translatedFormat('M') . ' – ' . now()->translatedFormat('M Y');
        $targetPct = $periodTarget > 0 ? min(100, round($periodQuoted / $periodTarget * 100, 1)) : null;
        // Quote status tags (Figma "Tag" component: 24 px, dot + 12 px medium label).
        $statusTag = function ($r) {
            if ($r['status'] === 'sent' && $r['discount_pct'] > 0) return [__('In negotiation'), 'bg-nq-warning-bg border-[#fbe5bd] text-[#8a5a12]', 'bg-nq-chart3'];
            return match ($r['status']) {
                'won'   => [__('Won'), 'bg-nq-success-bg border-nq-success-border text-nq-success-text', 'bg-nq-success'],
                'lost'  => [__('Lost'), 'bg-nq-error-bg border-[#fbd5d6] text-nq-error-text', 'bg-nq-error'],
                'draft' => [__('Draft'), 'bg-nq-muted border-nq-border text-nq-text-2', 'bg-nq-text-3'],
                default => [__('Sent'), 'bg-nq-info-bg border-[#d4e9fb] text-[#1d6aa5]', 'bg-nq-chart2'],
            };
        };
        $initials = fn ($s) => mb_strtoupper(collect(preg_split('/\s+/', trim((string) $s)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('')) ?: '—';
        $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, ',', ''), '0'), ',');

        // Shared pieces.
        $card = 'bg-white border border-nq-border rounded-xl';
        $btn = 'h-9 inline-flex items-center gap-2 px-4 rounded-lg text-sm leading-[22px] font-medium transition-colors whitespace-nowrap';
        $btnSecondary = $btn . ' bg-white border border-nq-border text-nq-text hover:bg-nq-subtle';
        $cardTitle = 'text-base leading-6 font-medium text-nq-text';
        $menuBtn = 'w-7 h-7 inline-flex items-center justify-center rounded-lg text-nq-text-3 hover:bg-nq-muted hover:text-nq-text';
        $menuPanel = 'absolute right-0 z-20 mt-1 w-56 rounded-xl border border-nq-border bg-white p-1 shadow-lg';
        $menuItem = 'flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-nq-text hover:bg-nq-subtle';
        $link = 'inline-flex items-center gap-1 text-sm leading-[22px] font-medium text-primary-800 hover:underline';
        $exportUrl = route('brand.export', array_filter(['period' => $period, 'brand' => $brandId, 'dealer' => $dealerId]));
    @endphp

    <div class="flex flex-col gap-4">
        {{-- Page heading --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl leading-8 font-semibold tracking-[-0.24px] text-nq-text">{{ __('Hello :name', ['name' => $firstName]) }}</h2>
                    <span class="h-6 inline-flex items-center gap-1.5 px-2 rounded-md bg-nq-success-bg border border-nq-success-border text-xs leading-5 font-medium text-nq-success-text">
                        <span class="w-1.5 h-1.5 rounded-full bg-nq-success"></span>
                        {{ trans_choice('{0}no active dealership|{1}:count active dealership|[2,*]:count active dealerships', $connected, ['count' => $connected]) }}
                    </span>
                </div>
                <p class="text-sm leading-[22px] text-nq-text-2">{{ __('Follow your network\'s quotes, your models and your price levels in one place.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('brand.dealers.index', ['invite' => 1]) }}" class="{{ $btnSecondary }}">
                    <i class="ph-bold ph-user-plus text-base"></i> {{ __('Invite dealers') }}</a>
                <a href="{{ route($account->isEngine() ? 'brand.engines.create' : 'brand.catalogue.create') }}" class="{{ $btn }} bg-primary-800 text-white hover:bg-nq-active">
                    <i class="ph-bold ph-plus text-base"></i> {{ $account->isEngine() ? __('Add engine') : __('Add model') }}</a>
            </div>
        </div>

        {{-- Getting started — until every step is done --}}
        @if ($stepsDone < count($steps))
            <section class="rise {{ $card }} p-4">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <h3 class="{{ $cardTitle }}">{{ __('Getting started') }} <span class="text-sm font-normal text-nq-text-3">· {{ __(':done of :total steps done', ['done' => $stepsDone, 'total' => count($steps)]) }}</span></h3>
                    <div class="w-40 h-1.5 rounded-full bg-nq-muted overflow-hidden"><div class="h-full rounded-full bg-nq-success grow-x" style="width: {{ round($stepsDone / count($steps) * 100) }}%"></div></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2">
                    @foreach ($steps as $i => $s)
                        <a href="{{ $s['href'] }}" class="flex items-center gap-3 rounded-lg border px-3 py-2.5 transition-colors {{ $s['done'] ? 'border-nq-success-border bg-nq-success-bg/60' : 'border-nq-border hover:border-primary-300' }}">
                            <span class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center {{ $s['done'] ? 'bg-nq-success text-white' : 'bg-nq-primary-bg text-primary-800' }}"><i class="ph-bold ph-{{ $s['done'] ? 'check' : $s['icon'] }} text-base"></i></span>
                            <span class="text-sm font-medium {{ $s['done'] ? 'text-nq-success-text' : 'text-nq-text' }}">{{ $i + 1 }}. {{ $s['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-2">
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open" class="{{ $btnSecondary }}">
                    <i class="ph-bold ph-sliders text-base"></i> {{ __('Filters') }}
                    @if ($dealerId) <span class="w-1.5 h-1.5 rounded-full bg-primary-800"></span> @endif
                </button>
                <div x-show="open" x-cloak x-transition @click.outside="open = false" class="{{ $menuPanel }} !left-0 !right-auto w-64 max-h-80 overflow-y-auto">
                    <p class="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-[0.66px] text-nq-text-3">{{ __('Dealership') }}</p>
                    <a href="{{ $q(['dealer' => null]) }}" class="block rounded-lg px-3 py-2 text-sm {{ $dealerId === '' ? 'bg-nq-primary-bg text-primary-800 font-medium' : 'text-nq-text hover:bg-nq-subtle' }}">{{ __('All dealers') }}</a>
                    @foreach ($dealerOptions as $id => $name)
                        <a href="{{ $q(['dealer' => $id]) }}" class="block rounded-lg px-3 py-2 text-sm truncate {{ $dealerId === (string) $id ? 'bg-nq-primary-bg text-primary-800 font-medium' : 'text-nq-text hover:bg-nq-subtle' }}">{{ $name }}</a>
                    @endforeach
                </div>
            </div>
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open" class="{{ $btnSecondary }}">
                    <i class="ph-bold ph-calendar-blank text-base"></i> {{ $periods[$period] }} <i class="ph-bold ph-caret-down text-base"></i>
                </button>
                <div x-show="open" x-cloak x-transition @click.outside="open = false" class="{{ $menuPanel }} !left-0 !right-auto w-52">
                    @foreach ($periods as $p => $label)
                        <a href="{{ $q(['period' => $p]) }}" class="block rounded-lg px-3 py-2 text-sm {{ $period === $p ? 'bg-nq-primary-bg text-primary-800 font-medium' : 'text-nq-text hover:bg-nq-subtle' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            @if ($brands->count() > 1 && ! $account->isEngine())
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" class="{{ $btnSecondary }}">
                        <i class="ph-bold ph-tag text-base"></i> {{ $brandId ? ($brands->first(fn ($b) => (string) $b->_id === $brandId)?->name ?? __('All brands')) : __('All brands') }} <i class="ph-bold ph-caret-down text-base"></i>
                    </button>
                    <div x-show="open" x-cloak x-transition @click.outside="open = false" class="{{ $menuPanel }} !left-0 !right-auto">
                        <a href="{{ $q(['brand' => null]) }}" class="block rounded-lg px-3 py-2 text-sm {{ $brandId === '' ? 'bg-nq-primary-bg text-primary-800 font-medium' : 'text-nq-text hover:bg-nq-subtle' }}">{{ __('All brands') }}</a>
                        @foreach ($brands as $b)
                            <a href="{{ $q(['brand' => (string) $b->_id]) }}" class="block rounded-lg px-3 py-2 text-sm {{ $brandId === (string) $b->_id ? 'bg-nq-primary-bg text-primary-800 font-medium' : 'text-nq-text hover:bg-nq-subtle' }}">{{ $b->name }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
            <a href="{{ $exportUrl }}" class="ml-auto {{ $btnSecondary }}">
                <i class="ph-bold ph-export text-base"></i> {{ __('Export') }}</a>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach ($kpis as $i => $k)
                @php
                    $up = $k['delta'] !== null && $k['delta'] >= 0;
                    $deltaLabel = $k['delta'] === null ? null : ($k['delta'] >= 0 ? '+' : '−') . abs($k['delta']) . $k['unit'];
                @endphp
                <div class="rise {{ $card }} pt-4 px-4 pb-[30px] flex flex-col gap-6" style="--d: {{ $i }}">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm leading-[22px] font-medium text-nq-text-2">{{ $k['label'] }}</p>
                        @if ($deltaLabel)
                            <span class="inline-flex items-center gap-[3px] px-1.5 py-0.5 rounded-md border text-xs leading-5 font-medium whitespace-nowrap {{ $up ? 'bg-nq-success-bg border-nq-success text-nq-success-text' : 'bg-nq-error-bg border-nq-error text-nq-error-text' }}">
                                <i class="ph-bold {{ $up ? 'ph-trend-up' : 'ph-trend-down' }} text-xs"></i>{{ $deltaLabel }}</span>
                        @endif
                    </div>
                    <div>
                        @if ($k['value'] === null)
                            <p class="text-2xl leading-8 font-semibold text-nq-border">—</p>
                        @else
                            <p class="text-2xl leading-8 font-semibold text-nq-text tabular-nums"
                                x-data="countUp({{ (float) $k['value'] }}, { suffix: @js(! empty($k['money']) ? ' €' : ($k['suffix'] ?? '')) })" x-text="display">{{ ! empty($k['money']) ? $eur($k['value']) : $k['value'] . ($k['suffix'] ?? '') }}</p>
                        @endif
                        <p class="text-xs leading-5 text-nq-text-3">{{ $k['note'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pipeline + status --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <section class="rise xl:col-span-2 {{ $card }} p-4 flex flex-col gap-4" style="--d: 4"
                x-data="pipelineChart(@js($series), @js($chartTotals), @js($monthlyTarget))">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="{{ $cardTitle }}">{{ __('Quote pipeline (HT)') }}</h3>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" class="{{ $menuBtn }}" title="{{ __('More') }}"><i class="ph-bold ph-dots-three-vertical text-base"></i></button>
                        <div x-show="open" x-cloak x-transition @click.outside="open = false" class="{{ $menuPanel }}">
                            <a href="{{ $exportUrl }}" class="{{ $menuItem }}"><i class="ph-bold ph-export text-base text-nq-text-2"></i> {{ __('Export') }}</a>
                            <a href="{{ route('brand.quotes.index') }}" class="{{ $menuItem }}"><i class="ph-bold ph-file-text text-base text-nq-text-2"></i> {{ __('All quotes') }}</a>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="inline-flex gap-0.5 p-[3px] rounded-lg bg-nq-muted">
                        @foreach (['quoted' => __('Quoted'), 'won' => __('Won'), 'count' => __('No. of quotes'), 'discount' => __('Avg. discount')] as $tab => $label)
                            <button type="button" @click="show('{{ $tab }}')" class="h-8 px-3 rounded-md text-sm leading-[22px] transition-colors"
                                :class="tab === '{{ $tab }}' ? 'bg-white shadow-nq-xs font-medium text-nq-text' : 'text-nq-text-2 hover:text-nq-text'">{{ $label }}</button>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs leading-5 text-nq-text-3">{{ __('Total:') }}</span>
                        <span class="text-sm leading-[22px] font-medium text-nq-text tabular-nums" x-text="totalLabel()"></span>
                        <template x-if="totals[tab][1] !== null">
                            <span class="px-1.5 py-0.5 rounded-md border text-xs leading-5 font-medium"
                                :class="totals[tab][1] >= 0 ? 'bg-nq-success-bg border-nq-success text-nq-success-text' : 'bg-nq-error-bg border-nq-error text-nq-error-text'"
                                x-text="(totals[tab][1] >= 0 ? '+' : '−') + Math.abs(totals[tab][1]) + ' %'"></span>
                        </template>
                    </div>
                </div>
                @if ($hasData)
                    <div class="relative h-[268px] rounded-[10px] bg-nq-subtle p-3"><canvas x-ref="canvas"></canvas></div>
                @else
                    <div class="rounded-[10px] bg-nq-subtle">
                        <x-app.empty-state icon="ri-bar-chart-2-line" size="sm" class="h-[268px]" :title="__('No data yet')"
                            :message="__('As soon as your dealers make quotes with your products, they appear here month by month.')" />
                    </div>
                @endif
            </section>

            <section class="rise {{ $card }} p-4 flex flex-col gap-4" style="--d: 5">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="{{ $cardTitle }}">{{ __('Quote status') }}</h3>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" class="{{ $menuBtn }}" title="{{ __('More') }}"><i class="ph-bold ph-dots-three-vertical text-base"></i></button>
                        <div x-show="open" x-cloak x-transition @click.outside="open = false" class="{{ $menuPanel }}">
                            <a href="{{ route('brand.quotes.index') }}" class="{{ $menuItem }}"><i class="ph-bold ph-file-text text-base text-nq-text-2"></i> {{ __('All quotes') }}</a>
                        </div>
                    </div>
                </div>
                <div class="flex-1 rounded-[10px] bg-nq-subtle px-4 py-6 flex items-center justify-center">
                    @if ($statusTotal > 0)
                        <div class="w-full flex flex-col sm:flex-row xl:flex-col 2xl:flex-row items-center justify-center gap-4">
                            <div class="relative w-[156px] h-[156px] shrink-0"><canvas id="brandStatus"></canvas>
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

        {{-- Funnel + target / best dealerships --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <section class="rise {{ $card }} p-4 flex flex-col gap-2" style="--d: 6">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="{{ $cardTitle }}">{{ $period === '6m' ? __('Semester funnel') : __('Funnel') }}</h3>
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open" class="{{ $menuBtn }}" title="{{ __('More') }}"><i class="ph-bold ph-dots-three-vertical text-base"></i></button>
                        <div x-show="open" x-cloak x-transition @click.outside="open = false" class="{{ $menuPanel }}">
                            <a href="{{ route('brand.quotes.index') }}" class="{{ $menuItem }}"><i class="ph-bold ph-file-text text-base text-nq-text-2"></i> {{ __('All quotes') }}</a>
                        </div>
                    </div>
                </div>
                <ul>
                    @foreach ($funnel as $f)
                        @php [$icon, $tone, $title, $text] = $funnelMeta[$f['key']]; @endphp
                        <li class="flex items-start gap-3.5 py-3 {{ $loop->last ? '' : 'border-b border-nq-border-2' }}">
                            <span class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center {{ $tone }}"><i class="ph-bold ph-{{ $icon }} text-base"></i></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-base leading-6 text-nq-text">{{ $title }}</p>
                                    <span class="text-sm leading-[22px] font-medium text-nq-text tabular-nums">{{ $f['n'] }}</span>
                                </div>
                                <p class="text-sm leading-[22px] text-nq-text-2">{{ $text }}</p>
                                <p class="text-xs leading-5 text-nq-text-3">{{ $periodLabel }}&nbsp;&nbsp;•&nbsp;&nbsp;{{ round($f['n'] / $funnelBase * 100) }} %</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            <div class="flex flex-col gap-4">
                {{-- Target --}}
                <section class="rise {{ $card }} p-4 flex flex-col gap-4" style="--d: 7" x-data="{ edit: {{ $monthlyTarget > 0 ? 'false' : 'true' }} }">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="{{ $cardTitle }}">{{ $period === '6m' ? __('Semester target (HT)') : __('Period target (HT)') }}</h3>
                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = !open" class="{{ $menuBtn }}" title="{{ __('More') }}"><i class="ph-bold ph-dots-three-vertical text-base"></i></button>
                            <div x-show="open" x-cloak x-transition @click.outside="open = false" class="{{ $menuPanel }}">
                                <button type="button" @click="edit = true; open = false" class="w-full {{ $menuItem }}"><i class="ph-bold ph-pencil-simple text-base text-nq-text-2"></i> {{ __('Edit targets') }}</button>
                            </div>
                        </div>
                    </div>
                    @if ($targetPct !== null)
                        <div x-show="!edit" class="flex flex-col gap-3">
                            <p class="flex items-baseline gap-2">
                                <span class="text-2xl leading-8 font-semibold text-nq-text tabular-nums">{{ number_format($targetPct, 1, ',', ' ') }} %</span>
                                <span class="text-xs leading-5 text-nq-text-3">{{ $periodQuoted >= $periodTarget ? __('target reached') : __(':v remaining', ['v' => $eur($periodTarget - $periodQuoted)]) }}</span>
                            </p>
                            <div class="flex h-7 gap-1.5">
                                <div class="grow-x h-full rounded-lg bg-primary-800" style="width: {{ $targetPct }}%"></div>
                                @if ($targetPct < 100)
                                    <div class="h-full flex-1 rounded-lg" style="background: repeating-linear-gradient(135deg, #e8e8eb 0 1.5px, #f0f0f2 1.5px 7px)"></div>
                                @endif
                            </div>
                            <div class="flex justify-between text-xs leading-5">
                                <span class="text-nq-text-2">{{ __('Quoted:') }} {{ $eur($periodQuoted) }}</span>
                                <span class="font-medium text-nq-text">{{ __('Target:') }} {{ $eur($periodTarget) }}</span>
                            </div>
                        </div>
                    @endif
                    <form x-show="edit" x-cloak method="POST" action="{{ route('brand.targets') }}" class="flex flex-col gap-3">
                        @csrf
                        <p class="text-sm text-nq-text-2">{{ __('Set your own targets: they draw the target line on the chart and fill this gauge.') }}</p>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="text-xs font-medium text-nq-text-2">{{ __('Quoted per month (€ HT)') }}
                                <input type="number" name="monthly_quoted" min="0" step="1000" value="{{ $monthlyTarget ?: '' }}" class="mt-1 w-full h-9 rounded-lg border-nq-border text-sm focus:border-primary-800 focus:ring-primary-800"></label>
                            <label class="text-xs font-medium text-nq-text-2">{{ __('Conversion rate (%)') }}
                                <input type="number" name="win_rate" min="0" max="100" step="1" value="{{ $rateTarget ?? '' }}" class="mt-1 w-full h-9 rounded-lg border-nq-border text-sm focus:border-primary-800 focus:ring-primary-800"></label>
                        </div>
                        <div class="flex justify-end gap-2">
                            @if ($monthlyTarget > 0) <button type="button" @click="edit = false" class="{{ $btnSecondary }}">{{ __('Cancel') }}</button> @endif
                            <button class="{{ $btn }} bg-primary-800 text-white hover:bg-nq-active">{{ __('Save targets') }}</button>
                        </div>
                    </form>
                </section>

                {{-- Best dealerships --}}
                <section class="rise {{ $card }} p-4 flex flex-col gap-2" style="--d: 8">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="{{ $cardTitle }}">{{ __('Best dealerships') }}</h3>
                        <a href="{{ route('brand.dealers.index') }}" class="{{ $link }}">{{ __('See all') }} <i class="ph-bold ph-arrow-right text-base"></i></a>
                    </div>
                    <div>
                        @forelse ($topDealers as $d)
                            @php $accentTone = $loop->index === 2; @endphp
                            <div class="flex items-center gap-3 py-2">
                                <span class="w-8 h-8 shrink-0 rounded-full text-xs font-semibold flex items-center justify-center {{ $accentTone ? 'bg-nq-accent-bg text-nq-accent-text' : 'bg-nq-primary-bg text-primary-800' }}">{{ $initials($d['name']) }}</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm leading-[22px] font-medium text-nq-text truncate">{{ $d['name'] }}</p>
                                    <p class="text-xs leading-5 text-nq-text-3 truncate">{{ collect([$d['city'], $d['level']])->filter()->implode(' · ') }}</p>
                                </div>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-nq-subtle border border-nq-border text-xs leading-5 font-medium text-nq-text-2 whitespace-nowrap"><i class="ph-bold ph-check text-xs"></i>{{ trans_choice('{1}:count quote|[2,*]:count quotes', $d['quotes'], ['count' => $d['quotes']]) }}</span>
                            </div>
                        @empty
                            <x-app.empty-state icon="ri-store-2-line" size="sm" :title="__('No data yet')" :message="__('Your most active dealerships will rank here.')" />
                        @endforelse
                    </div>
                </section>
            </div>
        </div>

        {{-- Latest quotes --}}
        <section class="rise {{ $card }} overflow-hidden" style="--d: 9">
            <div class="p-4 flex items-center justify-between gap-2">
                <h3 class="{{ $cardTitle }}">{{ __('Latest quotes') }}</h3>
                <a href="{{ route('brand.quotes.index') }}" class="{{ $link }}">{{ __('All quotes') }} <i class="ph-bold ph-arrow-right text-base"></i></a>
            </div>
            @if ($latest->isEmpty())
                <x-app.empty-state icon="ri-file-list-3-line" size="sm" :title="__('No quotes yet')" :message="__('Quotes your dealers make with your products appear here. End clients stay anonymous.')" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-sm">
                        <thead>
                            <tr class="bg-nq-subtle text-left text-xs leading-5 text-nq-text-2">
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2">{{ __('Model') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[220px]">{{ __('Dealership') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[150px]">{{ __('Client') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[90px] text-right">{{ __('Discount') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[130px] text-right">{{ __('Amount HT') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[140px]">{{ __('Status') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[170px]">{{ __('Salesperson') }}</th>
                                <th class="h-9 px-4 border-y border-nq-border-2 w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($latest as $r)
                                @php [$sLabel, $sCls, $sDot] = $statusTag($r); $rowBorder = $loop->last ? '' : 'border-b border-nq-border-2'; @endphp
                                <tr class="hover:bg-nq-subtle">
                                    <td class="h-14 px-4 {{ $rowBorder }}">
                                        <a href="{{ route('brand.quotes.show', $r['id']) }}" class="block text-sm leading-[22px] font-medium text-nq-text hover:text-primary-800 whitespace-nowrap">{{ $r['boat'] ?: '—' }}</a>
                                        <div class="text-xs leading-5 text-nq-text-3 truncate max-w-[240px]">{{ implode(', ', $r['fitted_engines']) ?: $r['version'] }}</div>
                                    </td>
                                    <td class="h-14 px-4 {{ $rowBorder }}">
                                        <span class="inline-flex items-center gap-2 whitespace-nowrap"><span class="w-6 h-6 shrink-0 rounded-full bg-nq-primary-bg text-primary-800 text-[10px] font-semibold flex items-center justify-center">{{ $initials($r['dealer']) }}</span><span class="text-sm leading-[22px] text-nq-text truncate max-w-[160px]">{{ $r['dealer'] }}</span></span>
                                    </td>
                                    <td class="h-14 px-4 text-sm leading-[22px] text-nq-text whitespace-nowrap {{ $rowBorder }}">{{ $r['client'] }}</td>
                                    <td class="h-14 px-4 text-right text-sm leading-[22px] font-medium text-nq-text tabular-nums whitespace-nowrap {{ $rowBorder }}">{{ $r['discount_pct'] > 0 ? '−' . $pct($r['discount_pct']) . ' %' : '—' }}</td>
                                    <td class="h-14 px-4 text-right text-sm leading-[22px] font-medium text-nq-text tabular-nums whitespace-nowrap {{ $rowBorder }}">{{ $eur($r['value_ht']) }}</td>
                                    <td class="h-14 px-4 {{ $rowBorder }}"><span class="h-6 inline-flex items-center gap-1.5 px-2 rounded-md border text-xs leading-5 font-medium whitespace-nowrap {{ $sCls }}"><span class="w-1.5 h-1.5 rounded-full {{ $sDot }}"></span>{{ $sLabel }}</span></td>
                                    <td class="h-14 px-4 {{ $rowBorder }}">
                                        <span class="inline-flex items-center gap-2 whitespace-nowrap"><span class="w-6 h-6 shrink-0 rounded-full bg-nq-accent-bg text-nq-accent-text text-[10px] font-semibold flex items-center justify-center">{{ $initials($r['salesperson']) }}</span><span class="text-sm leading-[22px] text-nq-text-2 truncate max-w-[110px]">{{ $r['salesperson'] ?: '—' }}</span></span>
                                    </td>
                                    <td class="h-14 px-4 text-right {{ $rowBorder }}">
                                        <a href="{{ route('brand.quotes.show', $r['id']) }}" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-nq-text-3 hover:bg-nq-muted hover:text-nq-text" title="{{ __('Open') }}"><i class="ph-bold ph-dots-three-vertical text-base"></i></a>
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
            // Past months hatched grey, the current month in navy with its value on top,
            // the monthly target as a dashed line (amount tabs only). Figma node 19:228.
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
                        const hatch = document.createElement('canvas'); hatch.width = hatch.height = 7;
                        const h = hatch.getContext('2d'); h.fillStyle = '#f0f0f2'; h.fillRect(0, 0, 7, 7);
                        h.strokeStyle = '#e4e4e7'; h.lineWidth = 1.5; h.beginPath(); h.moveTo(0, 7); h.lineTo(7, 0); h.stroke();
                        const pattern = ctx.createPattern(hatch, 'repeat');
                        const data = this.series.map(s => s[this.tab]);
                        const last = data.length - 1;
                        const showTarget = this.target > 0 && this.tab === 'quoted';
                        const self = this;
                        const font = (w, s) => `${w} ${s}px Geist, sans-serif`;
                        const overlay = {
                            id: 'overlay',
                            afterDatasetsDraw(c) {
                                const g = c.ctx, meta = c.getDatasetMeta(0), bar = meta.data[last];
                                if (showTarget) {
                                    const y = c.scales.y.getPixelForValue(self.target), a = c.chartArea; g.save();
                                    g.setLineDash([4, 4]); g.lineWidth = 1; g.strokeStyle = '#73737c'; g.beginPath(); g.moveTo(a.left, y); g.lineTo(a.right, y); g.stroke();
                                    g.setLineDash([]); g.fillStyle = '#55555e'; g.font = font(400, 12); g.textBaseline = 'bottom';
                                    g.fillText(@js(__('Target')) + ' : ' + Math.round(self.target / 1000) + ' k€', a.left + 8, y - 4); g.restore();
                                }
                                if (bar && data[last] > 0) {
                                    const label = self.fmt(data[last]); g.save(); g.font = font(500, 12);
                                    const w = g.measureText(label).width + 16, x = bar.x - w / 2, y = bar.y - 34;
                                    g.fillStyle = '#f0f0f2'; g.strokeStyle = '#e8e8eb'; g.lineWidth = 1; g.beginPath(); g.roundRect(x, y, w, 26, 6); g.fill(); g.stroke();
                                    g.fillStyle = '#0b0b0d'; g.textAlign = 'center'; g.textBaseline = 'middle'; g.fillText(label, bar.x, y + 13); g.restore();
                                }
                            },
                        };
                        this.chart = new Chart(this.$refs.canvas, {
                            type: 'bar',
                            data: { labels: this.series.map(s => s.label), datasets: [{ data,
                                backgroundColor: data.map((_, i) => i === last ? '#0e4f79' : pattern),
                                hoverBackgroundColor: data.map((_, i) => i === last ? '#093a5c' : '#e8e8eb'),
                                borderRadius: 8, borderSkipped: false, maxBarThickness: 84, categoryPercentage: 0.8, barPercentage: 0.95 }] },
                            options: {
                                responsive: true, maintainAspectRatio: false, layout: { padding: { top: 38 } },
                                animation: { duration: 800, easing: 'easeOutQuart', delay: c => c.type === 'data' ? c.dataIndex * 60 : 0 },
                                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0b0b0d', padding: 10, cornerRadius: 8, titleFont: { family: 'Geist' }, bodyFont: { family: 'Geist' }, callbacks: { label: c => self.fmt(c.raw) } } },
                                scales: {
                                    x: { grid: { display: false }, border: { display: false }, ticks: { color: '#73737c', font: { family: 'Geist', size: 12 } } },
                                    y: { beginAtZero: true, suggestedMax: showTarget ? this.target * 1.2 : undefined, border: { display: false }, grid: { display: false },
                                        ticks: { color: '#73737c', font: { family: 'Geist', size: 12 }, precision: 0, maxTicksLimit: 6, callback: v => this.tab === 'count' ? v : this.tab === 'discount' ? v + ' %' : (v >= 1000 ? Math.round(v / 1000) + 'k' : v) } },
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
                        datasets: [{ data: [st.sent, st.won, st.draft, st.lost], backgroundColor: ['#0e4f79', '#2b8fd6', '#e59a1a', '#e8e8eb'], borderWidth: 4, borderColor: '#fbfbfb', borderRadius: 6, hoverOffset: 4 }] },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '62%', animation: { animateRotate: true, duration: 900 },
                        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0b0b0d', padding: 10, cornerRadius: 8, titleFont: { family: 'Geist' }, bodyFont: { family: 'Geist' } } } },
                });
            });
        </script>
    @endpush
</x-app-layout>
