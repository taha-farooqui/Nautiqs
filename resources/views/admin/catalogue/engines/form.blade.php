@php
    $editing = isset($engine->_id) && $engine->_id;
    $action  = $editing ? route('admin.engines.update', $engine->_id) : route('admin.engines.store');
@endphp
<x-admin-layout
    :title="$editing ? __('Edit engine') : __('Add engine')"
    :header="$editing ? ($engine->brand . ' · ' . $engine->code) : __('Add engine')">

    <div class="mb-4 flex items-center gap-2 text-sm">
        <a href="{{ route('admin.engines.index') }}" class="text-gray-500 hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to engines') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
            <i class="ri-error-warning-line"></i> {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="bg-white rounded-2xl border border-gray-200 p-6 max-w-2xl space-y-4">
        @csrf
        @if ($editing) @method('PATCH') @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Brand') }} <span class="text-red-500">*</span></label>
                <input type="text" name="brand" required value="{{ old('brand', $engine->brand ?? '') }}"
                    placeholder="e.g. Suzuki, Yamaha, Mercury"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Code') }} <span class="text-red-500">*</span></label>
                <input type="text" name="code" required value="{{ old('code', $engine->code ?? '') }}"
                    placeholder="e.g. DF200A TL/TX"
                    class="w-full rounded-lg border-gray-300 font-mono text-sm focus:border-primary-800 focus:ring-primary-800" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Horsepower') }}</label>
                <input type="number" step="0.1" min="0" max="5000" name="horsepower" value="{{ old('horsepower', $engine->horsepower ?? '') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Fuel') }}</label>
                <select name="fuel"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                    @foreach (['unknown' => '—', 'petrol' => __('Petrol'), 'diesel' => __('Diesel'), 'electric' => __('Electric')] as $key => $label)
                        <option value="{{ $key }}" @selected(old('fuel', $engine->fuel ?? 'unknown') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Currency') }}</label>
                <select name="currency"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                    <option value="EUR" @selected(old('currency', $engine->currency ?? 'EUR') === 'EUR')>EUR</option>
                    <option value="USD" @selected(old('currency', $engine->currency ?? 'EUR') === 'USD')>USD</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Purchase price (cost)') }}</label>
                <input type="number" step="0.01" min="0" name="cost" value="{{ old('cost', $engine->cost ?? '') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <p class="text-[11px] text-gray-500 mt-1">{{ __('Never shown on client PDFs.') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Selling price (excl. VAT)') }} <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="price" required value="{{ old('price', $engine->price ?? '') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('VAT (%)') }}</label>
                <input type="number" step="0.1" min="0" max="100" name="vat_rate" value="{{ old('vat_rate', $engine->vat_rate ?? 20) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
            <textarea name="description" rows="2" maxlength="1000"
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">{{ old('description', $engine->description ?? '') }}</textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('admin.engines.index') }}"
                class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button type="submit"
                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-save-line"></i> {{ $editing ? __('Save changes') : __('Create engine') }}
            </button>
        </div>
    </form>
</x-admin-layout>
