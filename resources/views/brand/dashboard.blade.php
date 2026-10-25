<x-app-layout :title="__('Dashboard')" :header="__('Dashboard')">
    @include('brand.partials.flash')
    @php $eur = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' €'; @endphp

    <form method="GET" class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <p class="text-sm text-gray-600">{{ __('How your :n connected dealers are quoting your products.', ['n' => $connected]) }}</p>
        <div class="flex flex-wrap gap-2">
            @if ($brands->count() > 1 && ! $account->isEngine())
                <label class="text-xs text-gray-500">{{ __('Brand') }}
                    <select name="brand" onchange="this.form.submit()" class="block mt-1 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                        <option value="">{{ __('All brands') }}</option>
                        @foreach ($brands as $b) <option value="{{ $b->_id }}" @selected($brandId === (string) $b->_id)>{{ $b->name }}</option> @endforeach
                    </select>
                </label>
            @endif
            <label class="text-xs text-gray-500">{{ __('Period') }}
                <select name="period" onchange="this.form.submit()" class="block mt-1 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                    <option value="month" @selected($period === 'month')>{{ __('This month') }}</option>
                    <option value="6m" @selected($period === '6m')>{{ __('Last 6 months') }}</option>
                    <option value="year" @selected($period === 'year')>{{ __('Last 12 months') }}</option>
                </select>
            </label>
        </div>
    </form>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-app.stat-card :label="__('Quotes made')" :value="$kpis['quotes']" icon="ri-file-list-3-line" />
        <x-app.stat-card :label="__('Quoted value (HT)')" :value="$eur($kpis['quoted'])" icon="ri-money-euro-circle-line" />
        <x-app.stat-card :label="__('Won (HT)')" :value="$eur($kpis['won'])" icon="ri-trophy-line" iconColor="bg-emerald-50 text-emerald-700" />
        <x-app.stat-card :label="__('Win rate')" :value="$kpis['win_rate'] !== null ? $kpis['win_rate'] . ' %' : '—'" icon="ri-percent-line" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mb-6">
        @php $max = max(1, collect($months)->max('quoted')); @endphp
        <section class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
                <h3 class="font-semibold text-gray-900">{{ __('Quotes per month') }}</h3>
                <div class="flex gap-4 text-xs text-gray-600">
                    <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-primary-200 mr-1"></span>{{ __('Quoted') }}</span>
                    <span><span class="inline-block w-2.5 h-2.5 rounded-sm bg-primary-800 mr-1"></span>{{ __('Won') }}</span>
                </div>
            </div>
            <div class="flex items-end gap-3 h-48 border-b border-gray-200 px-1">
                @foreach ($months as $m)
                    <div class="flex-1 h-full flex items-end gap-1" title="{{ $m['label'] }} · {{ $m['quoted'] }} / {{ $m['won'] }}">
                        <div class="flex-1 bg-primary-200 rounded-t" style="height: {{ round($m['quoted'] / $max * 100) }}%"></div>
                        <div class="flex-1 bg-primary-800 rounded-t" style="height: {{ round($m['won'] / $max * 100) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-3 px-1 mt-2 text-xs text-gray-500">
                @foreach ($months as $m) <div class="flex-1 text-center">{{ $m['label'] }}</div> @endforeach
            </div>
        </section>

        <section class="bg-white rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900 mb-4">{{ $account->isEngine() ? __('Most quoted engines') : __('Most quoted versions') }}</h3>
            @php $topMax = max(1, $top->max() ?? 1); @endphp
            <div class="space-y-3 text-sm">
                @forelse ($top as $label => $n)
                    <div>
                        <div class="flex justify-between gap-2"><span class="font-medium text-gray-900 truncate">{{ $label }}</span><span>{{ $n }}</span></div>
                        <div class="h-2 bg-gray-100 rounded mt-1"><div class="h-2 bg-primary-800 rounded" style="width: {{ round($n / $topMax * 100) }}%"></div></div>
                    </div>
                @empty
                    <p class="text-gray-500">{{ __('No quotes yet in this period.') }}</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-4 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">{{ __('Dealers') }}</h3>
            <a href="{{ route('brand.dealers.index') }}" class="text-sm font-medium text-primary-800 hover:underline">{{ __('Manage dealers') }} →</a>
        </div>
        @if ($dealers->isEmpty())
            <p class="px-5 pb-5 text-sm text-gray-500">{{ __('No quotes from your dealers in this period yet.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr><th class="px-5 py-2.5">{{ __('Dealer') }}</th><th class="px-4 py-2.5">{{ __('Level') }}</th><th class="px-4 py-2.5 text-right">{{ __('Quotes') }}</th><th class="px-4 py-2.5 text-right">{{ __('Quoted HT') }}</th><th class="px-4 py-2.5 text-right">{{ __('Won') }}</th><th class="px-4 py-2.5 text-right">{{ __('Win rate') }}</th><th class="px-5 py-2.5 text-right">{{ __('Avg. discount') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($dealers as $d)
                            <tr>
                                <td class="px-5 py-2.5 font-medium text-gray-900">{{ $d['name'] }}</td>
                                <td class="px-4 py-2.5 text-gray-700">{{ $d['level'] }}</td>
                                <td class="px-4 py-2.5 text-right">{{ $d['quotes'] }}</td>
                                <td class="px-4 py-2.5 text-right">{{ $eur($d['quoted']) }}</td>
                                <td class="px-4 py-2.5 text-right">{{ $d['won'] }}</td>
                                <td class="px-4 py-2.5 text-right">{{ $d['win_rate'] !== null ? $d['win_rate'] . ' %' : '—' }}</td>
                                <td class="px-5 py-2.5 text-right">{{ $d['discount'] }} %</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @include('brand.quotes.table', ['rows' => $latest, 'title' => __('Latest quotes'), 'more' => true])
</x-app-layout>
