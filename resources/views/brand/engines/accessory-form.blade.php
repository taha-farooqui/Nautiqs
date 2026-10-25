<x-app-layout :title="$acc ? $acc->label : __('Add kit or propeller')" :header="$acc ? $acc->label : __('Add kit or propeller')">
    @include('brand.partials.flash')
    @php $lp = $acc->level_prices ?? []; @endphp

    <div class="mb-4 text-sm"><a href="{{ route('brand.engines.index', ['tab' => 'accessories']) }}" class="text-gray-500 hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ __('Kits & propellers') }}</a></div>

    <form method="POST" action="{{ $acc ? route('brand.engines.accessories.update', $acc->_id) : route('brand.engines.accessories.store') }}"
        x-data="{ price: @js(old('price', $acc->price ?? '')), type: @js(old('type', $type)), levels: @js($levels) }" class="space-y-4 max-w-4xl">
        @csrf
        @if ($acc) @method('PATCH') @endif

        <section class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Type') }}</label>
                    <select name="type" x-model="type" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                        <option value="kit">{{ __('Kit') }}</option>
                        <option value="propeller">{{ __('Propeller') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Brand') }}</label>
                    <select name="managed_brand_id" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                        @foreach ($brands as $b) <option value="{{ $b->_id }}" @selected((string) old('managed_brand_id', $acc->managed_brand_id ?? '') === (string) $b->_id)>{{ $b->name }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Reference') }}</label>
                    <input name="reference" maxlength="120" value="{{ old('reference', $acc->reference ?? '') }}" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }} <span class="text-red-500">*</span></label>
                <input name="label" required maxlength="500" value="{{ old('label', $acc->label ?? '') }}" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
            <div class="grid grid-cols-2 gap-4 max-w-md">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Public price HT') }} <span class="text-red-500">*</span></label>
                    <input name="price" type="number" step="0.01" min="0" required x-model="price" class="w-full rounded-lg border-gray-300 text-right focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('VAT %') }}</label>
                    <input name="vat_rate" type="number" step="0.1" min="0" max="100" value="{{ old('vat_rate', $acc->vat_rate ?? 20) }}" class="w-full rounded-lg border-gray-300 text-right focus:border-primary-800 focus:ring-primary-800" />
                </div>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $lv['name'] }} <span class="font-normal text-gray-500" x-text="'(−' + (type === 'kit' ? levels[{{ $i }}].kits : levels[{{ $i }}].propellers) + ' %)'"></span></label>
                        <input name="levels[{{ $i }}]" type="number" step="0.01" min="0" value="{{ old('levels.' . $i, $lp[$i] ?? '') }}"
                            :placeholder="price === '' ? '' : (price * (1 - (type === 'kit' ? levels[{{ $i }}].kits : levels[{{ $i }}].propellers) / 100)).toFixed(2)"
                            class="w-full rounded-lg border-gray-300 text-right focus:border-primary-800 focus:ring-primary-800" />
                    </div>
                @endforeach
            </div>
        </section>

        <section class="bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-3">{{ __('Fits these engines') }}</h3>
            @if ($engines->isEmpty())
                <p class="text-sm text-gray-500">{{ __('No engines yet.') }}</p>
            @else
                @php $sel = array_map('strval', old('engine_ids', $linked)); @endphp
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-1.5 max-h-80 overflow-y-auto">
                    @foreach ($engines as $e)
                        <label class="flex items-center gap-2 text-sm text-gray-800">
                            <input type="checkbox" name="engine_ids[]" value="{{ $e->_id }}" @checked(in_array((string) $e->_id, $sel, true)) class="rounded border-gray-300 text-primary-800 focus:ring-primary-800">
                            {{ $e->code }}@if ($e->horsepower) <span class="text-gray-400">· {{ (int) $e->horsepower }} {{ __('HP') }}</span>@endif
                        </label>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('brand.engines.index', ['tab' => 'accessories']) }}" class="px-4 py-2 text-sm font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg">{{ __('Cancel') }}</a>
            <button class="px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">{{ __('Save and publish') }}</button>
        </div>
    </form>
</x-app-layout>
