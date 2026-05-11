<x-app-layout :title="$engine ? __('Edit engine') : __('Add engine')" :header="$engine ? __('Edit engine') : __('Add engine')">
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('engines.index') }}" class="hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to engines') }}
        </a>
    </div>

    <form method="POST"
        action="{{ $engine ? route('engines.update', $engine->_id) : route('engines.store') }}"
        class="bg-white rounded-2xl border border-gray-200 p-6 max-w-3xl space-y-5">
        @csrf
        @if ($engine) @method('PATCH') @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Brand') }} <span class="text-red-500">*</span></label>
                <input type="text" name="brand" required list="engine-brands"
                    value="{{ old('brand', $engine->brand ?? '') }}"
                    placeholder="Suzuki, Yamaha, Mercury, …"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <datalist id="engine-brands">
                    <option value="Suzuki"></option>
                    <option value="Yamaha"></option>
                    <option value="Mercury"></option>
                    <option value="Honda"></option>
                    <option value="Tohatsu"></option>
                    <option value="Volvo Penta"></option>
                </datalist>
                <x-input-error :messages="$errors->get('brand')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Code / SKU') }} <span class="text-red-500">*</span></label>
                <input type="text" name="code" required
                    value="{{ old('code', $engine->code ?? '') }}"
                    placeholder="e.g. DF200A TL/TX"
                    class="w-full rounded-lg border-gray-300 font-mono text-sm focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('code')" class="mt-1" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Horsepower') }}</label>
                <input type="number" step="1" min="0" name="horsepower"
                    value="{{ old('horsepower', $engine->horsepower ?? '') }}"
                    placeholder="200"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Fuel') }}</label>
                <select name="fuel" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                    <option value="">—</option>
                    @php
                        $fuelLabels = [
                            'petrol'   => __('Petrol'),
                            'diesel'   => __('Diesel'),
                            'electric' => __('Electric'),
                            'unknown'  => __('Unknown'),
                        ];
                    @endphp
                    @foreach ($fuelLabels as $value => $label)
                        <option value="{{ $value }}" @selected(old('fuel', $engine->fuel ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Currency') }}</label>
                <select name="currency" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                    <option value="EUR" @selected(old('currency', $engine->currency ?? 'EUR') === 'EUR')>EUR</option>
                    <option value="USD" @selected(old('currency', $engine->currency ?? '') === 'USD')>USD</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
            <input type="text" name="description"
                value="{{ old('description', $engine->description ?? '') }}"
                placeholder="{{ __('Optional — extra notes shown next to the SKU') }}"
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Cost (dealer)') }}</label>
                <input type="number" step="0.01" min="0" name="cost"
                    value="{{ old('cost', $engine->cost ?? '') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <p class="text-xs text-gray-500 mt-1">{{ __('Internal — never shown to clients.') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Public HT') }} <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="price" required
                    value="{{ old('price', $engine->price ?? '') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('price')" class="mt-1" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('VAT rate (%)') }}</label>
                <input type="number" step="0.1" min="0" max="100" name="vat_rate"
                    value="{{ old('vat_rate', $engine->vat_rate ?? '20') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('engines.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-save-line"></i> {{ $engine ? __('Save changes') : __('Add engine') }}
            </button>
        </div>
    </form>
</x-app-layout>
