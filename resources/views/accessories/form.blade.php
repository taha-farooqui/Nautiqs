<x-app-layout :title="$accessory ? __('Edit accessory') : __('Add accessory')" :header="$accessory ? __('Edit accessory') : __('Add accessory')">
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('accessories.index') }}" class="hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to accessories') }}
        </a>
    </div>

    @php $defaultVat = auth()->user()->company->default_vat_rate ?? 20; @endphp

    <form method="POST"
        action="{{ $accessory ? route('accessories.update', $accessory->_id) : route('accessories.store') }}"
        class="max-w-3xl space-y-4">
        @csrf
        @if ($accessory) @method('PATCH') @endif

        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }} <span class="text-red-500">*</span></label>
                <input type="text" name="label" required maxlength="500" autofocus
                    value="{{ old('label', $accessory->label ?? '') }}"
                    placeholder="{{ __('e.g. Sound system Bose') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Purchase price HT') }}</label>
                    <input type="number" step="0.01" name="cost"
                        value="{{ old('cost', $accessory->cost ?? '') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Selling price HT') }} <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="price" required
                        value="{{ old('price', $accessory->price ?? '') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('VAT rate (%)') }} <span class="text-red-500">*</span></label>
                    <input type="number" step="0.1" min="0" max="100" name="vat_rate" required
                        value="{{ old('vat_rate', $accessory->vat_rate ?? $defaultVat) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Currency') }} <span class="text-red-500">*</span></label>
                    <select name="currency" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                        @foreach (\App\Models\Accessory::CURRENCIES as $ccy)
                            <option value="{{ $ccy }}" @selected(old('currency', $accessory->currency ?? 'EUR') === $ccy)>{{ $ccy }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('accessories.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-save-line"></i> {{ $accessory ? __('Save changes') : __('Add accessory') }}
            </button>
        </div>
    </form>
</x-app-layout>
