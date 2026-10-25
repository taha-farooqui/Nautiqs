<x-app-layout :title="__('Catalogue')" :header="__('Catalogue')">
    @include('brand.partials.flash')

    <div class="mb-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <p class="text-sm text-gray-600 max-w-2xl">{{ __('Public prices and purchase levels. Every change reaches your connected dealers; quotes they already made never change.') }}</p>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('brand.transfer.export') }}" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg">
                <i class="ri-download-2-line"></i> {{ __('Export') }}
            </a>
            <a href="{{ route('brand.transfer.form') }}" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg">
                <i class="ri-upload-2-line"></i> {{ __('Import') }}
            </a>
            @if ($brand)
                <a href="{{ route('brand.catalogue.create', ['brand' => $brand->_id]) }}" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                    <i class="ri-add-line"></i> {{ __('Add model') }}
                </a>
            @endif
        </div>
    </div>

    @if ($brands->count() > 1)
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ($brands as $b)
                @php $on = $brand && (string) $b->_id === (string) $brand->_id; @endphp
                <a href="{{ route('brand.catalogue.index', ['brand' => $b->_id]) }}"
                    class="px-4 py-2 rounded-full text-sm font-semibold {{ $on ? 'bg-primary-800 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}">
                    {{ $b->name }} <span class="{{ $on ? 'text-white/70' : 'text-gray-400' }}">{{ $counts[(string) $b->_id] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($models->isEmpty())
        <x-app.empty-state icon="ri-sailboat-line" :title="__('No models yet')"
            :message="__('Add your first model by hand, or import your price list from Excel.')"
            :ctaLabel="__('Add model')" ctaHref="{{ $brand ? route('brand.catalogue.create', ['brand' => $brand->_id]) : '#' }}" size="lg" />
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 font-semibold">{{ __('Model') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('Versions') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('Options') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('From (HT)') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($models as $m)
                            @php
                                $vs = $variants[(string) $m->_id] ?? collect();
                                $min = $vs->min('price');
                            @endphp
                            <tr class="hover:bg-gray-50 {{ $m->is_archived ? 'opacity-60' : '' }}">
                                <td class="px-5 py-3">
                                    <a href="{{ route('brand.catalogue.edit', $m->_id) }}" class="font-semibold text-gray-900 hover:text-primary-800">{{ $m->name }}</a>
                                    @if ($m->is_archived)
                                        <span class="ml-1 text-[11px] font-semibold text-gray-600 bg-gray-100 rounded px-1.5 py-0.5">{{ __('archived') }}</span>
                                    @endif
                                    @if ($m->year) <div class="text-xs text-gray-500">{{ $m->year }}</div> @endif
                                </td>
                                <td class="px-5 py-3 text-right">{{ $vs->count() }}</td>
                                <td class="px-5 py-3 text-right">{{ ($options[(string) $m->_id] ?? collect())->count() }}</td>
                                <td class="px-5 py-3 text-right font-semibold">{{ $min !== null ? number_format($min, 2, ',', ' ') . ' €' : '—' }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('brand.catalogue.edit', $m->_id) }}" class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}"><i class="ri-pencil-line"></i></a>
                                        <form method="POST" action="{{ route('brand.catalogue.archive', $m->_id) }}"
                                            @if (! $m->is_archived) data-confirm="{{ __('Archive this model? It disappears from your dealers\' catalogues; their quotes keep it.') }}" @endif>
                                            @csrf
                                            <button class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:bg-gray-100 rounded-lg" title="{{ $m->is_archived ? __('Restore') : __('Archive') }}">
                                                <i class="{{ $m->is_archived ? 'ri-inbox-unarchive-line' : 'ri-archive-line' }}"></i>
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
