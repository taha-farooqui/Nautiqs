<x-app-layout :title="__('Quotes')" :header="__('Quotes')">
    @include('brand.partials.flash')

    <p class="mb-4 text-sm text-gray-600 max-w-3xl">{{ __('Every quote your connected dealers make with your products. End clients stay anonymous.') }}</p>

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3 bg-white rounded-2xl border border-gray-200 px-4 py-3">
        <span class="w-9 h-9 rounded-lg bg-gray-50 text-gray-500 flex items-center justify-center self-end"><i class="ri-filter-3-line"></i></span>
        <label class="text-xs text-gray-500">{{ __('Dealer') }}
            <select name="dealer" onchange="this.form.submit()" class="block mt-1 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                <option value="">{{ __('All dealers') }}</option>
                @foreach ($dealers as $cid => $name) <option value="{{ $cid }}" @selected($filters['dealer'] === (string) $cid)>{{ $name }}</option> @endforeach
            </select>
        </label>
        @if ($brands->count() > 1 && ! $account->isEngine())
            <label class="text-xs text-gray-500">{{ __('Brand') }}
                <select name="brand" onchange="this.form.submit()" class="block mt-1 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                    <option value="">{{ __('All brands') }}</option>
                    @foreach ($brands as $b) <option value="{{ $b->_id }}" @selected($filters['brand'] === (string) $b->_id)>{{ $b->name }}</option> @endforeach
                </select>
            </label>
        @endif
        @if ($models->isNotEmpty())
            <label class="text-xs text-gray-500">{{ __('Model') }}
                <select name="model" onchange="this.form.submit()" class="block mt-1 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                    <option value="">{{ __('All models') }}</option>
                    @foreach ($models as $m) <option value="{{ $m }}" @selected($filters['model'] === $m)>{{ $m }}</option> @endforeach
                </select>
            </label>
        @endif
        <label class="text-xs text-gray-500">{{ __('Status') }}
            <select name="status" onchange="this.form.submit()" class="block mt-1 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                <option value="">{{ __('All') }}</option>
                @foreach (['draft' => __('Draft'), 'sent' => __('Sent'), 'won' => __('Won'), 'lost' => __('Lost')] as $s => $label)
                    <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs text-gray-500">{{ __('Period') }}
            <select name="period" onchange="this.form.submit()" class="block mt-1 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                <option value="month" @selected($filters['period'] === 'month')>{{ __('This month') }}</option>
                <option value="6m" @selected($filters['period'] === '6m')>{{ __('Last 6 months') }}</option>
                <option value="year" @selected($filters['period'] === 'year')>{{ __('Last 12 months') }}</option>
            </select>
        </label>
    </form>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        @foreach ([['total', __('Quotes'), 'ri-file-list-3-line', 'bg-primary-50 text-primary-800'], ['open', __('Open (sent)'), 'ri-send-plane-line', 'bg-sky-50 text-sky-700'],
                   ['won', __('Won'), 'ri-trophy-line', 'bg-emerald-50 text-emerald-700'], ['lost', __('Lost'), 'ri-close-circle-line', 'bg-red-50 text-red-600']] as $i => [$k, $label, $icon, $tone])
            <div class="rise lift bg-white rounded-2xl border border-gray-200 p-4 flex items-center gap-3" style="--d: {{ $i }}">
                <span class="w-11 h-11 rounded-xl {{ $tone }} flex items-center justify-center shrink-0"><i class="{{ $icon }} text-xl"></i></span>
                <div><div class="text-2xl font-bold text-gray-900 tabular-nums" x-data="countUp({{ (int) $stats[$k] }})" x-text="display">{{ $stats[$k] }}</div><div class="text-xs text-gray-500">{{ $label }}</div></div>
            </div>
        @endforeach
    </div>

    <div class="rise" style="--d: 4">@include('brand.quotes.table', ['rows' => $rows])</div>
</x-app-layout>
