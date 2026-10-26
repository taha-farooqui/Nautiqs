<x-app-layout :title="__('Brands')" :header="__('Brands')">
    @include('brand.partials.flash')
    @php $catalogue = $account->isEngine() ? 'brand.engines.index' : 'brand.catalogue.index'; @endphp

    <p class="mb-5 text-sm text-gray-600 max-w-2xl">{{ __('The brands you manage on Nautiqs. Each one has its own catalogue; you choose which brands each dealer receives.') }}</p>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 content-start">
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
                    <x-app.empty-state icon="ri-building-4-line" :title="__('No brands yet')" :message="__('Add your first brand to start your catalogue.')" />
                </div>
            @endforelse
        </div>

        <section class="rise bg-white rounded-2xl border border-gray-200 p-5 self-start" style="--d: 2">
            <span class="w-11 h-11 rounded-xl bg-primary-50 text-primary-800 flex items-center justify-center mb-3"><i class="ri-add-line text-xl"></i></span>
            <h3 class="font-semibold text-gray-900 mb-1">{{ __('Add a brand') }}</h3>
            <p class="text-sm text-gray-500 mb-4">{{ __('A brand can be managed by one account only on Nautiqs.') }}</p>
            <form method="POST" action="{{ route('brand.brands.store') }}" class="space-y-3">
                @csrf
                <div class="relative"><i class="ri-building-4-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input name="name" required maxlength="120" value="{{ old('name') }}" placeholder="{{ __('Brand name') }}" aria-label="{{ __('Brand name') }}"
                        class="w-full pl-9 rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800"></div>
                @if ($connected > 0)
                    <label class="flex items-start gap-2.5 rounded-lg bg-gray-50 p-3 text-sm cursor-pointer">
                        <input type="checkbox" name="share" value="1" checked class="mt-0.5 rounded border-gray-300 text-primary-800 focus:ring-primary-800">
                        <span class="text-gray-700">{{ trans_choice('{1}Share it with my connected dealer|[2,*]Share it with my :count connected dealers', $connected, ['count' => $connected]) }}</span>
                    </label>
                @endif
                <button class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg"><i class="ri-add-line"></i> {{ __('Add brand') }}</button>
            </form>
        </section>
    </div>
</x-app-layout>
