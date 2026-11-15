<x-app-layout :title="__('Brands')" :header="__('Brands')">
    @include('brand.partials.flash')
    @php $catalogue = $account->isEngine() ? 'brand.engines.index' : 'brand.catalogue.index'; @endphp

    <p class="mb-5 text-sm text-gray-600 max-w-2xl">{{ __('The brands you manage on Nautiqs. Each one has its own catalogue; you choose which brands each dealer receives.') }}</p>

    <div class="grid grid-cols-1 gap-5">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 content-start">
            @forelse ($brands as $b)
                @php $id = (string) $b->_id; $items = $itemCounts[$id] ?? 0; $dealers = $dealerCounts[$id] ?? 0; @endphp
                <div x-data="{ edit: false }" class="rise lift bg-white rounded-2xl border border-gray-200 p-5" style="--d: {{ min($loop->index, 8) }}">
                    <div x-show="!edit" class="flex items-start gap-3">
                        <span class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-800 to-primary-600 text-white font-bold flex items-center justify-center shrink-0">{{ mb_strtoupper(mb_substr($b->name, 0, 2)) }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-900 text-lg truncate">{{ $b->name }}</div>
                            <div class="mt-1 flex flex-wrap gap-2 text-xs">
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 text-gray-700 px-2.5 py-1"><i class="{{ $account->isEngine() ? 'ri-settings-3-line' : 'ri-sailboat-line' }}"></i>
                                    {{ trans_choice($account->isEngine() ? '{0}No engine|{1}:count engine|[2,*]:count engines' : '{0}No model|{1}:count model|[2,*]:count models', $items, ['count' => $items]) }}</span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 text-gray-700 px-2.5 py-1"><i class="ri-store-2-line"></i>
                                    {{ trans_choice('{0}no dealer|{1}:count dealer|[2,*]:count dealers', $dealers, ['count' => $dealers]) }}</span>
                            </div>
                        </div>
                        <button type="button" @click="edit = true; $nextTick(() => $refs.n.focus())" class="w-8 h-8 inline-flex items-center justify-center text-gray-400 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Rename') }}"><i class="ri-pencil-line"></i></button>
                    </div>
                    <form x-show="edit" x-cloak method="POST" action="{{ route('brand.brands.update', $id) }}" class="flex gap-2">
                        @csrf @method('PATCH')
                        <input x-ref="n" name="name" value="{{ $b->name }}" required maxlength="120" @keydown.escape="edit = false" aria-label="{{ __('Brand name') }}"
                            class="flex-1 min-w-0 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                        <button class="w-9 h-9 inline-flex items-center justify-center bg-primary-800 text-white rounded-lg" aria-label="{{ __('Save') }}"><i class="ri-check-line"></i></button>
                        <button type="button" @click="edit = false" class="w-9 h-9 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg" aria-label="{{ __('Cancel') }}"><i class="ri-close-line"></i></button>
                    </form>
                    <a x-show="!edit" href="{{ $account->isEngine() ? route($catalogue) : route($catalogue, ['brand' => $id]) }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary-800 hover:underline">
                        {{ __('Open its catalogue') }} <i class="ri-arrow-right-line"></i></a>
                </div>
            @empty
                <div class="md:col-span-2 bg-white rounded-2xl border border-gray-200">
                    <x-app.empty-state icon="ri-building-4-line" :title="__('No brands yet')" :message="__('Nautiqs sets up your brands. Contact your account manager to add one.')" />
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
