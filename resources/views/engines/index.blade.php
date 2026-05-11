<x-app-layout :title="__('Engines')" :header="__('Engines')">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif

    <div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <form method="GET" action="{{ route('engines.index') }}" class="flex-1 max-w-md">
            <div class="relative">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="q" value="{{ $q }}"
                    placeholder="{{ __('Search by brand, code, or description…') }}"
                    class="w-full pl-9 pr-3 py-2 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
            </div>
        </form>
        <a href="{{ route('engines.create') }}"
            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
            <i class="ri-add-line"></i> {{ __('Add engine') }}
        </a>
    </div>

    @if ($engines->isEmpty())
        <x-app.empty-state
            icon="ri-settings-3-line"
            :title="__('No engines yet')"
            :message="__('Add the engine SKUs you carry so they show up in the quote builder.')"
            :ctaLabel="__('Add the first engine')"
            ctaHref="{{ route('engines.create') }}"
            size="lg" />
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Brand') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Code') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('HP') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Fuel') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Public HT') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('VAT') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('TTC') }}</th>
                        <th class="px-5 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($engines as $engine)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $engine->brand }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-gray-700">{{ $engine->code }}</td>
                            <td class="px-5 py-3 text-gray-700">{{ $engine->horsepower ? number_format($engine->horsepower, 0) . ' ' . __('HP') : '—' }}</td>
                            <td class="px-5 py-3 text-gray-700 capitalize">{{ $engine->fuel ?? '—' }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900">€{{ number_format($engine->price ?? 0, 2, ',', ' ') }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ number_format($engine->vat_rate ?? 0, 2) }}%</td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900">€{{ number_format($engine->priceTtc(), 2, ',', ' ') }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('engines.edit', $engine->_id) }}"
                                        class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                    <form method="POST" action="{{ route('engines.destroy', $engine->_id) }}"
                                        onsubmit="return confirm('{{ __('Delete this engine?') }}');" class="inline">
                                        @csrf @method('DELETE')
                                        <button class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-50 rounded-lg" title="{{ __('Delete') }}">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $engines->links() }}</div>
    @endif
</x-app-layout>
