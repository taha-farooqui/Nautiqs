<x-admin-layout :title="__('Engines')" :header="__('Global engines')">

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
            <a href="{{ route('admin.engines.index', ['status' => $tab, 'q' => $q]) }}"
                class="px-3 py-1.5 text-sm font-medium rounded-lg transition
                    {{ $status === $tab ? 'bg-primary-800 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" action="{{ route('admin.engines.index') }}" class="flex-1 max-w-md">
            <input type="hidden" name="status" value="{{ $status }}" />
            <div class="relative">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="search" name="q" value="{{ $q }}"
                    placeholder="{{ __('Search brand, code or description…') }}"
                    class="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800" />
            </div>
        </form>
        @if ($status === 'active')
            <a href="{{ route('admin.engines.create') }}"
                class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-add-line"></i> {{ __('Add engine') }}
            </a>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($engines->isEmpty())
            <div class="px-6 py-12 text-center">
                <i class="ri-settings-3-line text-4xl text-gray-300"></i>
                <p class="text-sm text-gray-700 mt-3 font-medium">{{ __('No engines.') }}</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Brand') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Code') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('HP') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Cost') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Price') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('VAT') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($engines as $e)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $e->brand }}</td>
                            <td class="px-5 py-3"><span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $e->code }}</span></td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ $e->horsepower ? number_format($e->horsepower, 0) : '—' }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ $e->cost ? ($e->currency === 'USD' ? '$' : '€') . number_format($e->cost, 0, ',', ' ') : '—' }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900">{{ $e->currency === 'USD' ? '$' : '€' }}{{ number_format($e->price, 0, ',', ' ') }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ $e->vat_rate ? $e->vat_rate . '%' : '—' }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @if ($status === 'archived')
                                    <form method="POST" action="{{ route('admin.engines.archive', $e->_id) }}" class="inline"
                                        data-confirm="{{ __('Restore :name?', ['name' => $e->brand . ' ' . $e->code]) }}">
                                        @csrf
                                        <button class="inline-flex items-center justify-center w-8 h-8 text-emerald-600 hover:bg-emerald-50 rounded-lg" title="{{ __('Restore') }}">
                                            <i class="ri-arrow-go-back-line"></i>
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('admin.engines.edit', $e->_id) }}"
                                        class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.engines.archive', $e->_id) }}" class="inline"
                                        data-confirm="{{ __('Archive :name?', ['name' => $e->brand . ' ' . $e->code]) }}"
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
            <div class="px-5 py-3 border-t border-gray-100">{{ $engines->links() }}</div>
        @endif
    </div>
</x-admin-layout>
