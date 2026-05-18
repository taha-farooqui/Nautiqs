<x-admin-layout :title="__('Variants')" :header="__('Global variants')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
        </div>
    @endif

    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" action="{{ route('admin.variants.index') }}" class="flex flex-1 items-center gap-2 max-w-2xl">
            <div class="relative flex-1">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="search" name="q" value="{{ $q }}"
                    placeholder="{{ __('Search variant name…') }}"
                    class="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800" />
            </div>
            <select name="model" onchange="this.form.submit()"
                class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800 min-w-[200px]">
                <option value="">{{ __('All models') }}</option>
                @foreach ($allModels as $m)
                    <option value="{{ $m->_id }}" @selected($modelId === (string) $m->_id)>
                        {{ $m->name }} @if ($m->code) ({{ $m->code }}) @endif
                    </option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('admin.variants.create', $modelId ? ['model' => $modelId] : []) }}"
            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
            <i class="ri-add-line"></i> {{ __('Add variant') }}
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($variants->isEmpty())
            <div class="px-6 py-12 text-center">
                <i class="ri-layout-grid-line text-4xl text-gray-300"></i>
                <p class="text-sm text-gray-700 mt-3 font-medium">{{ __('No variants.') }}</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Model') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Variant') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Base price') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Cost') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Currency') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($variants as $v)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3">
                                <div class="text-gray-900">{{ $v->_model_name }}</div>
                                @if ($v->_model_code)
                                    <div class="text-[11px] text-gray-500 font-mono">{{ $v->_model_code }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $v->name }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900">{{ $v->currency === 'USD' ? '$' : '€' }}{{ number_format($v->base_price, 0, ',', ' ') }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ $v->cost ? ($v->currency === 'USD' ? '$' : '€') . number_format($v->cost, 0, ',', ' ') : '—' }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $v->currency }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.variants.edit', $v->_id) }}"
                                    class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}">
                                    <i class="ri-pencil-line"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.variants.archive', $v->_id) }}" class="inline"
                                    data-confirm="{{ __('Archive :name?', ['name' => $v->name]) }}"
                                    data-confirm-danger="1">
                                    @csrf
                                    <button class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:bg-gray-100 rounded-lg" title="{{ __('Archive') }}">
                                        <i class="ri-archive-line"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-5 py-3 border-t border-gray-100">{{ $variants->links() }}</div>
        @endif
    </div>
</x-admin-layout>
