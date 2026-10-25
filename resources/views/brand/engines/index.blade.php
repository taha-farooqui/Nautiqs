<x-app-layout :title="__('Catalogue')" :header="__('Catalogue')">
    @include('brand.partials.flash')
    @php $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €'; @endphp

    <div class="mb-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm self-start">
            <a href="{{ route('brand.engines.index') }}" class="px-3 py-1.5 rounded-md font-medium transition {{ $tab === 'engines' ? 'bg-primary-800 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900' }}"><i class="ri-settings-3-line"></i> {{ __('Engines') }} <span class="{{ $tab === 'engines' ? 'text-white/70' : 'text-gray-400' }}">{{ $engines->count() }}</span></a>
            <a href="{{ route('brand.engines.index', ['tab' => 'accessories']) }}" class="px-3 py-1.5 rounded-md font-medium transition {{ $tab === 'accessories' ? 'bg-primary-800 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900' }}"><i class="ri-tools-line"></i> {{ __('Kits & propellers') }} <span class="{{ $tab === 'accessories' ? 'text-white/70' : 'text-gray-400' }}">{{ $accessories->count() }}</span></a>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('brand.transfer.export') }}" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg"><i class="ri-download-2-line"></i> {{ __('Export') }}</a>
            <a href="{{ route('brand.transfer.form') }}" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg"><i class="ri-upload-2-line"></i> {{ __('Import') }}</a>
            @if ($tab === 'engines')
                <a href="{{ route('brand.engines.create') }}" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg"><i class="ri-add-line"></i> {{ __('Add engine') }}</a>
            @else
                <a href="{{ route('brand.engines.accessories.create') }}" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg"><i class="ri-add-line"></i> {{ __('Add kit or propeller') }}</a>
            @endif
        </div>
    </div>

    <div class="rise bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            @if ($tab === 'engines')
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr><th class="px-5 py-3">{{ __('Engine') }}</th><th class="px-4 py-3 text-right">{{ __('HP') }}</th><th class="px-4 py-3 text-right">{{ __('Kits & propellers') }}</th><th class="px-4 py-3 text-right">{{ __('Public HT') }}</th><th class="px-5 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($engines as $e)
                            <tr class="hover:bg-gray-50 {{ $e->is_archived ? 'opacity-60' : '' }}">
                                <td class="px-5 py-3"><span class="inline-flex items-center gap-3"><span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0"><i class="ri-settings-3-line"></i></span><a href="{{ route('brand.engines.edit', $e->_id) }}" class="font-semibold text-gray-900 hover:text-primary-800">{{ $brandNames[(string) $e->managed_brand_id] ?? '' }} {{ $e->code }}</a></span>
                                    @if ($e->is_archived) <span class="ml-1 text-[11px] font-semibold text-gray-600 bg-gray-100 rounded px-1.5 py-0.5">{{ __('archived') }}</span> @endif</td>
                                <td class="px-4 py-3 text-right">{{ $e->horsepower ? (int) $e->horsepower : '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ count($e->accessoryIds()) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ $eur($e->price) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('brand.engines.archive', $e->_id) }}" class="inline">@csrf
                                        <button class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg" title="{{ $e->is_archived ? __('Restore') : __('Archive') }}"><i class="{{ $e->is_archived ? 'ri-inbox-unarchive-line' : 'ri-archive-line' }}"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-app.empty-state icon="ri-settings-3-line" :title="__('No engines yet')" :message="__('No engines yet. Add them one by one, or import your price list.')"
                                :ctaLabel="__('Add engine')" :ctaHref="route('brand.engines.create')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr><th class="px-5 py-3">{{ __('Kit / propeller') }}</th><th class="px-4 py-3 text-right">{{ __('Public HT') }}</th><th class="px-5 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($accessories as $a)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3"><div class="text-[11px] font-semibold uppercase text-gray-400">{{ $a->type === 'kit' ? __('Kit') : __('Propeller') }}</div>
                                    <a href="{{ route('brand.engines.accessories.edit', $a->_id) }}" class="font-medium text-gray-900 hover:text-primary-800">{{ $a->label }}</a></td>
                                <td class="px-4 py-3 text-right font-semibold">{{ $eur($a->price) }}</td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('brand.engines.accessories.destroy', $a->_id) }}" class="inline" data-confirm="{{ __('Delete this kit or propeller? It is removed from your dealers; their quotes keep it.') }}" data-confirm-danger="1">@csrf @method('DELETE')
                                        <button class="w-8 h-8 inline-flex items-center justify-center text-red-600 hover:bg-red-50 rounded-lg" title="{{ __('Delete') }}"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><x-app.empty-state icon="ri-tools-line" :title="__('No kits or propellers yet.')" :message="__('Add kits and propellers, then link them to the engines they fit.')"
                                :ctaLabel="__('Add kit or propeller')" :ctaHref="route('brand.engines.accessories.create')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
