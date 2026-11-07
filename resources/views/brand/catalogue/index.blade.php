{{-- Brand catalogue list: same page as the dealer catalogue. --}}
<x-app-layout :title="__('Catalogue')" :header="__('Catalogue')">
    @include('brand.partials.flash')
    @php
        $brandFilter = $brand ? (string) $brand->_id : '';
        $brandUrl = fn ($id) => route('brand.catalogue.index', array_filter(['brand' => $id, 'q' => $q]));
        $activeCount = $models->filter(fn ($m) => ! $m->is_archived && ($variants[(string) $m->_id] ?? collect())->isNotEmpty())->count();
        $createUrl = route('brand.catalogue.create', array_filter(['brand' => $brandFilter]));

        $card = 'bg-white border border-nq-border rounded-xl';
        $btn = 'h-9 inline-flex items-center gap-2 px-4 rounded-lg text-sm leading-[22px] font-medium transition-colors whitespace-nowrap';
        $btnSecondary = $btn . ' bg-white border border-nq-border text-nq-text hover:bg-nq-subtle';
        $iconBtn = 'w-7 h-7 inline-flex items-center justify-center rounded-lg transition-colors';
    @endphp

    <div class="flex flex-col gap-4">
        {{-- Page heading --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl leading-8 font-semibold tracking-[-0.24px] text-nq-text">{{ __('Catalogue') }}</h2>
                    <span class="h-6 inline-flex items-center gap-1.5 px-2 rounded-md bg-nq-success-bg border border-nq-success-border text-xs leading-5 font-medium text-nq-success-text">
                        <span class="w-1.5 h-1.5 rounded-full bg-nq-success"></span>
                        {{ trans_choice('{0}no active boat|{1}:count active boat|[2,*]:count active boats', $activeCount, ['count' => $activeCount]) }}
                    </span>
                </div>
                <p class="text-sm leading-[22px] text-nq-text-2">{{ __('Your boats, versions, options and prices.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('brand.transfer.form') }}" class="{{ $btnSecondary }}"><i class="ph-bold ph-upload-simple text-base"></i> {{ __('Import') }}</a>
                @if ($brands->isNotEmpty())
                    <a href="{{ $createUrl }}" class="{{ $btn }} bg-primary-800 text-white hover:bg-nq-active"><i class="ph-bold ph-plus text-base"></i> {{ __('Add boat') }}</a>
                @endif
            </div>
        </div>

        {{-- Toolbar: search, brand, export --}}
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('brand.catalogue.index') }}" class="w-full sm:w-auto">
                @if ($brandFilter !== '') <input type="hidden" name="brand" value="{{ $brandFilter }}"> @endif
                <label class="w-full sm:w-[300px] h-9 flex items-center gap-2 px-3 bg-white border border-nq-border rounded-lg focus-within:border-primary-800 transition-colors">
                    <i class="ph-bold ph-magnifying-glass text-base text-nq-text-3"></i>
                    <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('Search by brand, model, or variant…') }}"
                        class="flex-1 min-w-0 p-0 border-0 bg-transparent text-sm leading-[22px] text-nq-text placeholder:text-nq-text-3 focus:ring-0" />
                </label>
            </form>
            @if ($brands->count() > 0)
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" class="{{ $btnSecondary }}">
                        <i class="ph-bold ph-tag text-base"></i>
                        {{ $brand?->name ?? __('All brands') }}
                        <i class="ph-bold ph-caret-down text-base"></i>
                    </button>
                    <div x-show="open" x-cloak x-transition @click.outside="open = false"
                        class="absolute left-0 z-20 mt-1 w-60 max-h-80 overflow-y-auto rounded-xl border border-nq-border bg-white p-1 shadow-lg">
                        <a href="{{ $brandUrl(null) }}" class="block rounded-lg px-3 py-2 text-sm {{ $brandFilter === '' ? 'bg-nq-primary-bg text-primary-800 font-medium' : 'text-nq-text hover:bg-nq-subtle' }}">{{ __('All brands') }}</a>
                        @foreach ($brands as $b)
                            <a href="{{ $brandUrl((string) $b->_id) }}" class="block truncate rounded-lg px-3 py-2 text-sm {{ $brandFilter === (string) $b->_id ? 'bg-nq-primary-bg text-primary-800 font-medium' : 'text-nq-text hover:bg-nq-subtle' }}">{{ $b->name }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
            @if ($q !== '' || $brandFilter !== '')
                <a href="{{ route('brand.catalogue.index') }}" class="h-9 inline-flex items-center gap-1 px-2 text-sm text-nq-text-2 hover:text-nq-text"><i class="ph-bold ph-x text-sm"></i> {{ __('Clear') }}</a>
            @endif
            <a href="{{ route('brand.transfer.export') }}" class="ml-auto {{ $btnSecondary }}">
                <i class="ph-bold ph-export text-base"></i> {{ __('Export') }}</a>
        </div>

        @if ($models->isEmpty())
            <div class="{{ $card }}">
                @if ($q !== '' || $brandFilter !== '')
                    <x-app.empty-state icon="ri-search-line" :title="__('No results')" :message="__('Try another search or brand.')" />
                @else
                    <x-app.empty-state icon="ri-sailboat-line" :title="__('No models yet')" size="lg"
                        :message="__('Add your first model by hand, or import your price list from Excel.')"
                        :ctaLabel="__('Add boat')" ctaHref="{{ $createUrl }}" />
                @endif
            </div>
        @else
            <section class="{{ $card }} overflow-hidden">
                <div class="p-4 flex items-center justify-between gap-2">
                    <h3 class="text-base leading-6 font-medium text-nq-text">{{ __('Boats') }}</h3>
                    <span class="text-xs leading-5 text-nq-text-3">{{ trans_choice('{1}:count boat|[2,*]:count boats', $models->count(), ['count' => $models->count()]) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="bg-nq-subtle text-left text-xs leading-5 text-nq-text-2">
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[220px]">{{ __('Brand') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2">{{ __('Model') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[130px]">{{ __('Status') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[140px] text-right">{{ __('From (HT)') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[150px]">{{ __('Updated') }}</th>
                                <th class="h-9 px-4 border-y border-nq-border-2 w-[96px]"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($models as $m)
                                @php
                                    $vs = $variants[(string) $m->_id] ?? collect();
                                    $min = $vs->min('price');
                                    $count = $vs->count();
                                    $rowBorder = $loop->last ? '' : 'border-b border-nq-border-2';
                                @endphp
                                <tr class="hover:bg-nq-subtle cursor-pointer {{ $m->is_archived ? 'opacity-60' : '' }}"
                                    data-href="{{ route('brand.catalogue.edit', $m->_id) }}"
                                    onclick="if (!event.target.closest('a, button, form, input')) window.location = this.dataset.href">
                                    <td class="h-14 px-4 text-sm leading-[22px] font-medium text-nq-text whitespace-nowrap {{ $rowBorder }}">{{ $names[(string) $m->managed_brand_id] ?? '—' }}</td>
                                    <td class="h-14 px-4 {{ $rowBorder }}">
                                        <p class="text-sm leading-[22px] font-medium text-nq-text">{{ $m->name }}</p>
                                        @if ($count > 0)
                                            <p class="text-xs leading-5 text-nq-text-3">{{ $count }} {{ __('version(s)') }}</p>
                                        @else
                                            <p class="text-xs leading-5 text-nq-accent-text">{{ __('No versions yet') }}</p>
                                        @endif
                                    </td>
                                    <td class="h-14 px-4 {{ $rowBorder }}">
                                        @if ($m->is_archived)
                                            <span class="h-6 inline-flex items-center gap-1.5 px-2 rounded-md border bg-nq-muted border-nq-border text-xs leading-5 font-medium text-nq-text-2 whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-nq-text-3"></span>{{ ucfirst(__('archived')) }}</span>
                                        @elseif ($count > 0)
                                            <span class="h-6 inline-flex items-center gap-1.5 px-2 rounded-md border bg-nq-success-bg border-nq-success-border text-xs leading-5 font-medium text-nq-success-text whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-nq-success"></span>{{ __('Active') }}</span>
                                        @else
                                            <span class="h-6 inline-flex items-center gap-1.5 px-2 rounded-md border bg-nq-warning-bg border-[#fbe5bd] text-xs leading-5 font-medium text-[#8a5a12] whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-nq-chart3"></span>{{ __('Draft') }}</span>
                                        @endif
                                    </td>
                                    <td class="h-14 px-4 text-right text-sm leading-[22px] font-medium text-nq-text tabular-nums whitespace-nowrap {{ $rowBorder }}">{{ $min !== null ? number_format($min, 0, ',', ' ') . ' €' : '—' }}</td>
                                    <td class="h-14 px-4 text-xs leading-5 text-nq-text-3 whitespace-nowrap {{ $rowBorder }}">{{ $m->updated_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="h-14 px-4 text-right whitespace-nowrap {{ $rowBorder }}">
                                        <a href="{{ route('brand.catalogue.edit', $m->_id) }}" class="{{ $iconBtn }} text-nq-text-3 hover:bg-nq-muted hover:text-nq-text" title="{{ __('Edit') }}"><i class="ph-bold ph-pencil-simple text-base"></i></a>
                                        <form method="POST" action="{{ route('brand.catalogue.archive', $m->_id) }}" class="inline"
                                            @if (! $m->is_archived) data-confirm="{{ __('Archive this model? It disappears from your dealers\' catalogues; their quotes keep it.') }}" @endif>
                                            @csrf
                                            <button type="submit" class="{{ $iconBtn }} text-nq-text-3 hover:bg-nq-muted hover:text-nq-text" title="{{ $m->is_archived ? __('Restore') : __('Archive') }}"><i class="ph-bold {{ $m->is_archived ? 'ph-arrow-counter-clockwise' : 'ph-archive' }} text-base"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
