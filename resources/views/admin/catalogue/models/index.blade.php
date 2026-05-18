<x-admin-layout :title="__('Models')" :header="__('Global models')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
        </div>
    @endif

    {{-- Status tabs --}}
    <div class="flex items-center gap-1 mb-4 bg-white p-1 rounded-xl border border-gray-200 w-fit">
        @foreach ([
            'active'   => __('Active') . ' (' . $tabCounts['active'] . ')',
            'archived' => __('Archived') . ' (' . $tabCounts['archived'] . ')',
        ] as $tab => $label)
            <a href="{{ route('admin.models.index', ['status' => $tab, 'q' => $q, 'brand' => $brandId]) }}"
                class="px-3 py-1.5 text-sm font-medium rounded-lg transition
                    {{ $status === $tab ? 'bg-primary-800 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" action="{{ route('admin.models.index') }}" class="flex flex-1 items-center gap-2 max-w-2xl">
            <input type="hidden" name="status" value="{{ $status }}" />
            <div class="relative flex-1">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="search" name="q" value="{{ $q }}"
                    placeholder="{{ __('Search by name or code…') }}"
                    class="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800" />
            </div>
            <select name="brand" onchange="this.form.submit()"
                class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800 min-w-[180px]">
                <option value="">{{ __('All brands') }}</option>
                @foreach ($allBrands as $b)
                    <option value="{{ $b->_id }}" @selected($brandId === (string) $b->_id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </form>
        @if ($status === 'active')
            <a href="{{ route('admin.models.create') }}"
                class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-add-line"></i> {{ __('Add model') }}
            </a>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($models->isEmpty())
            <div class="px-6 py-12 text-center">
                <i class="ri-sailboat-line text-4xl text-gray-300"></i>
                <p class="text-sm text-gray-700 mt-3 font-medium">{{ __('No models.') }}</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Brand') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Model') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Code') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Variants') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Options') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Default margin') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($models as $m)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3 text-gray-700">{{ $m->_brand_name }}</td>
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $m->name }}</td>
                            <td class="px-5 py-3"><span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $m->code }}</span></td>
                            <td class="px-5 py-3 text-right font-medium text-gray-900">{{ $m->_variants_count }}</td>
                            <td class="px-5 py-3 text-right font-medium text-gray-900">{{ $m->_options_count }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ $m->default_margin_pct ? $m->default_margin_pct . '%' : '—' }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.variants.index', ['model' => $m->_id]) }}"
                                    class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Variants') }}">
                                    <i class="ri-layout-grid-line"></i>
                                </a>
                                <a href="{{ route('admin.options.index', ['model' => $m->_id]) }}"
                                    class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Options') }}">
                                    <i class="ri-list-check-2"></i>
                                </a>
                                @if ($status === 'archived')
                                    <form method="POST" action="{{ route('admin.models.archive', $m->_id) }}" class="inline"
                                        data-confirm="{{ __('Restore :name?', ['name' => $m->name]) }}">
                                        @csrf
                                        <button class="inline-flex items-center justify-center w-8 h-8 text-emerald-600 hover:bg-emerald-50 rounded-lg" title="{{ __('Restore') }}">
                                            <i class="ri-arrow-go-back-line"></i>
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('admin.models.edit', $m->_id) }}"
                                        class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.models.archive', $m->_id) }}" class="inline"
                                        data-confirm="{{ __('Archive :name?', ['name' => $m->name]) }}"
                                        data-confirm-danger="1">
                                        @csrf
                                        <button class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:bg-gray-100 rounded-lg" title="{{ __('Archive') }}">
                                            <i class="ri-archive-line"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-5 py-3 border-t border-gray-100">{{ $models->links() }}</div>
        @endif
    </div>
</x-admin-layout>
