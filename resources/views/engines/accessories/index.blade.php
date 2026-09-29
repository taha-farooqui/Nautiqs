<x-app-layout :title="__('Accessories')" :header="__('Accessories')">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif

    <p class="mb-4 text-sm text-gray-600 max-w-3xl">
        {{ __('Pre-rigging kits and propellers sold with an engine. Link each one to the engines it fits and it is suggested on the quote whenever one of those engines is added.') }}
    </p>

    <div class="mb-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        {{-- Type tabs --}}
        <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm self-start">
            @foreach ([null => __('All'), 'kit' => __('Kits'), 'propeller' => __('Propellers')] as $t => $label)
                <a href="{{ route('engine-accessories.index', array_filter(['type' => $t, 'q' => $q])) }}"
                    class="px-3 py-1.5 rounded-md font-medium whitespace-nowrap
                        {{ $type === ($t ?: null) ? 'bg-primary-800 text-white' : 'text-gray-600 hover:text-gray-900' }}">
                    {{ $label }}
                    <span class="{{ $type === ($t ?: null) ? 'text-white/70' : 'text-gray-400' }}">{{ $counts[$t ?: 'all'] }}</span>
                </a>
            @endforeach
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
            <form method="GET" action="{{ route('engine-accessories.index') }}" class="w-full sm:w-72">
                @if ($type) <input type="hidden" name="type" value="{{ $type }}"> @endif
                <div class="relative">
                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('Search by name or reference…') }}"
                        class="w-full pl-9 pr-3 py-2 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                </div>
            </form>
            <a href="{{ route('engine-accessories.create', array_filter(['type' => $type])) }}"
                class="inline-flex items-center justify-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg whitespace-nowrap">
                <i class="ri-add-line"></i> {{ __('Add accessory') }}
            </a>
        </div>
    </div>

    @if ($accessories->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 px-6 py-12 text-center">
            <i class="ri-tools-line text-4xl text-gray-300"></i>
            <p class="text-sm font-medium text-gray-900 mt-3">
                {{ $q !== '' ? __('No accessory matches.') : __('No accessories yet') }}
            </p>
            @if ($q === '')
                <p class="text-sm text-gray-500 mt-1 max-w-md mx-auto">
                    {{ __('Add them one by one, or import your engine price list with its KIT and HELICE columns and they are created and linked for you.') }}
                </p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <a href="{{ route('engine-accessories.create') }}"
                        class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                        <i class="ri-add-line"></i> {{ __('Add accessory') }}
                    </a>
                    <a href="{{ route('engines.index') }}"
                        class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium border border-gray-300 hover:bg-gray-50 text-gray-800 rounded-lg">
                        <i class="ri-upload-2-line"></i> {{ __('Import from the engines page') }}
                    </a>
                </div>
            @endif
        </div>
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 font-semibold">{{ __('Accessory') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('Engines') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('Cost') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('Public HT') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('TTC') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($accessories as $a)
                            @php $n = $engineCount[(string) $a->_id] ?? 0; @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <div class="flex items-start gap-3">
                                        <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0
                                            {{ $a->type === 'kit' ? 'bg-primary-50 text-primary-800' : 'bg-sky-50 text-sky-700' }}">
                                            <i class="{{ $a->icon() }}"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $a->typeLabel() }}</div>
                                            <div class="font-medium text-gray-900 break-words">{{ $a->label }}</div>
                                            @if ($a->reference)
                                                <div class="text-xs text-gray-500 font-mono">{{ $a->reference }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @if ($n)
                                        <span class="inline-flex items-center gap-1 text-gray-900">
                                            <i class="ri-links-line text-gray-400"></i> {{ $n }}
                                        </span>
                                    @else
                                        <span class="text-xs text-amber-700 bg-amber-50 rounded px-1.5 py-0.5">{{ __('not linked') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right text-gray-500 whitespace-nowrap">
                                    {{ $a->cost !== null ? number_format($a->cost, 2, ',', ' ') . ' €' : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right font-semibold text-gray-900 whitespace-nowrap">{{ number_format($a->price, 2, ',', ' ') }} €</td>
                                <td class="px-5 py-3 text-right text-gray-700 whitespace-nowrap">
                                    {{ number_format($a->price * (1 + ($a->vat_rate ?? 20) / 100), 2, ',', ' ') }} €
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('engine-accessories.edit', $a->_id) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                        <form method="POST" action="{{ route('engine-accessories.destroy', $a->_id) }}"
                                            data-confirm="{{ __('Delete this accessory? It is unlinked from its engines; quotes already written keep it.') }}"
                                            data-confirm-danger="1" class="inline">
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
        </div>
    @endif
</x-app-layout>
