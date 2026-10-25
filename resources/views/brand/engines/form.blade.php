<x-app-layout :title="$engine ? $engine->code : __('Add engine')" :header="$engine ? $engine->code : __('Add engine')">
    @include('brand.partials.flash')
    @php $lp = $engine->level_prices ?? []; @endphp

    <div class="mb-4 text-sm"><a href="{{ route('brand.engines.index') }}" class="text-gray-500 hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ __('Engines') }}</a></div>

    <form method="POST" action="{{ $engine ? route('brand.engines.update', $engine->_id) : route('brand.engines.store') }}"
        x-data="{ price: @js(old('price', $engine->price ?? '')) }" class="space-y-4 max-w-4xl">
        @csrf
        @if ($engine) @method('PATCH') @endif

        <section class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Brand') }} <span class="text-red-500">*</span></label>
                    <select name="managed_brand_id" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                        @foreach ($brands as $b) <option value="{{ $b->_id }}" @selected((string) old('managed_brand_id', $engine->managed_brand_id ?? '') === (string) $b->_id)>{{ $b->name }}</option> @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Model / code') }} <span class="text-red-500">*</span></label>
                    <input name="code" required maxlength="120" value="{{ old('code', $engine->code ?? '') }}" placeholder="{{ __('e.g. DF150APX') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Horsepower') }}</label>
                    <input name="horsepower" type="number" step="1" min="0" value="{{ old('horsepower', $engine->horsepower ?? '') }}" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Fuel') }}</label>
                    <input name="fuel" maxlength="40" value="{{ old('fuel', $engine->fuel ?? '') }}" placeholder="{{ __('Petrol') }}" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Public price HT') }} <span class="text-red-500">*</span></label>
                    <input name="price" type="number" step="0.01" min="0" required x-model="price" class="w-full rounded-lg border-gray-300 text-right focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('VAT %') }}</label>
                    <input name="vat_rate" type="number" step="0.1" min="0" max="100" value="{{ old('vat_rate', $engine->vat_rate ?? 20) }}" class="w-full rounded-lg border-gray-300 text-right focus:border-primary-800 focus:ring-primary-800" />
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
                <textarea name="description" rows="2" maxlength="2000" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">{{ old('description', $engine->description ?? '') }}</textarea>
            </div>
        </section>

        <section class="bg-white rounded-2xl border border-gray-200 p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                <h3 class="font-semibold text-gray-900">{{ __('Dealer purchase prices') }}</h3>
                <span class="text-xs text-gray-500">{{ __('Leave a level empty to use its % (shown in grey); type a price to set it exactly.') }}</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach ($levels as $i => $lv)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $lv['name'] }} <span class="font-normal text-gray-500">(−{{ $lv['pct'] }} %)</span></label>
                        <input name="levels[{{ $i }}]" type="number" step="0.01" min="0" value="{{ old('levels.' . $i, $lp[$i] ?? '') }}"
                            :placeholder="price === '' ? '' : (price * (1 - {{ (float) $lv['pct'] }} / 100)).toFixed(2)"
                            class="w-full rounded-lg border-gray-300 text-right focus:border-primary-800 focus:ring-primary-800" />
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-3">{{ __('Kits & propellers for this engine') }}</h3>
            @if ($accessories->isEmpty())
                <p class="text-sm text-gray-500">{{ __('No kits or propellers yet. Add them in the Kits & propellers tab, then link them here.') }}</p>
            @else
                @php $sel = array_map('strval', old('accessory_ids', $engine ? $engine->accessoryIds() : [])); @endphp
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 max-h-80 overflow-y-auto">
                    @foreach ($accessories as $a)
                        <label class="flex items-start gap-2 text-sm text-gray-800">
                            <input type="checkbox" name="accessory_ids[]" value="{{ $a->_id }}" @checked(in_array((string) $a->_id, $sel, true)) class="mt-0.5 rounded border-gray-300 text-primary-800 focus:ring-primary-800">
                            <span><span class="text-[11px] font-semibold uppercase text-gray-400">{{ $a->type === 'kit' ? __('Kit') : __('Propeller') }}</span> {{ $a->label }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('brand.engines.index') }}" class="px-4 py-2 text-sm font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg">{{ __('Cancel') }}</a>
            <button class="px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">{{ __('Save and publish') }}</button>
        </div>
    </form>
</x-app-layout>
