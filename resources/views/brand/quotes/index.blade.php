<x-app-layout :title="__('Quotes')" :header="__('Quotes')">
    @include('brand.partials.flash')

    <p class="mb-4 text-sm text-gray-600 max-w-3xl">{{ __('Every quote your connected dealers make with your products. End clients stay anonymous.') }}</p>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
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

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        @foreach ([['total', __('Quotes')], ['open', __('Open (sent)')], ['won', __('Won')], ['lost', __('Lost')]] as [$k, $label])
            <div class="bg-white rounded-xl border border-gray-200 px-4 py-3"><div class="text-xs text-gray-500">{{ $label }}</div><div class="text-2xl font-bold text-gray-900">{{ $stats[$k] }}</div></div>
        @endforeach
    </div>

    @include('brand.quotes.table', ['rows' => $rows])
</x-app-layout>
