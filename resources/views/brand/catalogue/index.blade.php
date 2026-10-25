<x-app-layout :title="__('Catalogue')" :header="__('Catalogue')">
    @include('brand.partials.flash')

    <div class="mb-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <p class="text-sm text-gray-600 max-w-2xl">{{ __('Public prices and purchase levels. Every change reaches your connected dealers; quotes they already made never change.') }}</p>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('brand.transfer.export') }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg"><i class="ri-download-2-line"></i> {{ __('Export') }}</a>
            <a href="{{ route('brand.transfer.form') }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg"><i class="ri-upload-2-line"></i> {{ __('Import') }}</a>
            @if ($brand)
                <a href="{{ route('brand.catalogue.create', ['brand' => $brand->_id]) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg shadow-sm"><i class="ri-add-line"></i> {{ __('Add model') }}</a>
            @endif
        </div>
    </div>

    <div x-data="{ q: '' }">
        <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            @if ($brands->count() > 1)
                <div class="inline-flex flex-wrap rounded-xl border border-gray-200 bg-white p-1 text-sm">
                    @foreach ($brands as $b)
                        @php $on = $brand && (string) $b->_id === (string) $brand->_id; @endphp
                        <a href="{{ route('brand.catalogue.index', ['brand' => $b->_id]) }}" class="px-3 py-1.5 rounded-lg font-medium transition {{ $on ? 'bg-primary-800 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                            {{ $b->name }} <span class="{{ $on ? 'text-white/70' : 'text-gray-400' }}">{{ $counts[(string) $b->_id] ?? 0 }}</span></a>
                    @endforeach
                </div>
            @else
                <span></span>
            @endif
            @if ($models->isNotEmpty())
                <div class="relative sm:w-64">
                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="search" x-model="q" placeholder="{{ __('Search a model…') }}" class="w-full pl-9 rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800">
                </div>
            @endif
        </div>

        @if ($models->isEmpty())
            <div class="bg-white rounded-2xl border border-gray-200">
                <x-app.empty-state icon="ri-sailboat-line" :title="__('No models yet')" size="lg"
                    :message="__('Add your first model by hand, or import your price list from Excel.')"
                    :ctaLabel="__('Add model')" ctaHref="{{ $brand ? route('brand.catalogue.create', ['brand' => $brand->_id]) : '#' }}" />
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($models as $m)
                    @php
                        $vs = $variants[(string) $m->_id] ?? collect();
                        $min = $vs->min('price');
                        $optCount = ($options[(string) $m->_id] ?? collect())->count();
                    @endphp
                    <article x-show="q === '' || @js(mb_strtolower($m->name)).includes(q.toLowerCase())"
                        class="rise lift group bg-white rounded-2xl border border-gray-200 overflow-hidden flex flex-col {{ $m->is_archived ? 'opacity-60' : '' }}" style="--d: {{ min($loop->index, 8) }}">
                        <a href="{{ route('brand.catalogue.edit', $m->_id) }}" class="block relative h-24 bg-gradient-to-br {{ $m->is_archived ? 'from-gray-300 to-gray-400' : 'from-primary-800 to-primary-600' }}">
                            <i class="ri-sailboat-line absolute right-4 bottom-1 text-6xl text-white/15 group-hover:text-white/25 transition"></i>
                            <div class="absolute left-5 bottom-3 text-white">
                                <div class="text-xs text-white/70">{{ $brand?->name }}@if ($m->year) · {{ $m->year }}@endif</div>
                                <div class="text-lg font-bold leading-tight">{{ $m->name }}</div>
                            </div>
                            @if ($m->is_archived) <span class="absolute top-3 right-3 text-[11px] font-semibold text-gray-700 bg-white/90 rounded-full px-2 py-0.5"><i class="ri-archive-line"></i> {{ __('archived') }}</span> @endif
                        </a>
                        <div class="p-5 flex-1 flex flex-col">
                            <div class="flex gap-2 text-xs">
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 text-gray-700 px-2.5 py-1"><i class="ri-stack-line"></i> {{ trans_choice('{0}no version|{1}:count version|[2,*]:count versions', $vs->count(), ['count' => $vs->count()]) }}</span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 text-gray-700 px-2.5 py-1"><i class="ri-list-check-2"></i> {{ trans_choice('{0}no option|{1}:count option|[2,*]:count options', $optCount, ['count' => $optCount]) }}</span>
                            </div>
                            <div class="mt-4 flex items-end justify-between gap-2 mt-auto pt-4">
                                <div>
                                    <div class="text-xs text-gray-500">{{ __('From (HT)') }}</div>
                                    <div class="text-lg font-bold text-gray-900 tabular-nums">{{ $min !== null ? number_format($min, 0, ',', ' ') . ' €' : '—' }}</div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('brand.catalogue.edit', $m->_id) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-semibold text-primary-800 bg-primary-50 hover:bg-primary-100 rounded-lg"><i class="ri-pencil-line"></i> {{ __('Edit') }}</a>
                                    <form method="POST" action="{{ route('brand.catalogue.archive', $m->_id) }}"
                                        @if (! $m->is_archived) data-confirm="{{ __('Archive this model? It disappears from your dealers\' catalogues; their quotes keep it.') }}" @endif>@csrf
                                        <button class="w-8 h-8 inline-flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg" title="{{ $m->is_archived ? __('Restore') : __('Archive') }}">
                                            <i class="{{ $m->is_archived ? 'ri-inbox-unarchive-line' : 'ri-archive-line' }}"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
                @if ($brand)
                    <a href="{{ route('brand.catalogue.create', ['brand' => $brand->_id]) }}" x-show="q === ''"
                        class="rise min-h-[220px] rounded-2xl border-2 border-dashed border-gray-300 hover:border-primary-400 hover:bg-primary-50/30 text-gray-500 hover:text-primary-800 flex flex-col items-center justify-center gap-2 transition">
                        <span class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center"><i class="ri-add-line text-2xl"></i></span>
                        <span class="font-semibold">{{ __('Add model') }}</span>
                    </a>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
