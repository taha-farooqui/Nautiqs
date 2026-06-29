@php
    $editing = isset($variant->_id) && $variant->_id;
    $action  = $editing ? route('admin.variants.update', $variant->_id) : route('admin.variants.store');
@endphp
<x-admin-layout
    :title="$editing ? __('Edit variant') : __('Add variant')"
    :header="$editing ? $variant->name : __('Add variant')">

    <div class="mb-4 flex items-center gap-2 text-sm">
        <a href="{{ route('admin.variants.index') }}" class="text-gray-500 hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to variants') }}
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

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Model') }} <span class="text-red-500">*</span></label>
            <select name="model_id" required
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                <option value="">{{ __('Select a model…') }}</option>
                @foreach ($models as $m)
                    <option value="{{ $m->_id }}" @selected(old('model_id', $variant->model_id ?? '') === (string) $m->_id)>
                        {{ $m->name }} @if ($m->code) ({{ $m->code }}) @endif
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Variant name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" required value="{{ old('name', $variant->name ?? '') }}"
                placeholder="{{ __('e.g. 250 Sport — 2x 200HP') }}"
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Base price (excl. VAT)') }} <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="base_price" required value="{{ old('base_price', $variant->base_price ?? '') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Cost') }}</label>
                <input type="number" step="0.01" min="0" name="cost" value="{{ old('cost', $variant->cost ?? '') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <p class="text-[11px] text-gray-500 mt-1">{{ __('Never shown on client PDFs.') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Currency') }}</label>
                <select name="currency"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                    <option value="EUR" @selected(old('currency', $variant->currency ?? 'EUR') === 'EUR')>EUR</option>
                    <option value="USD" @selected(old('currency', $variant->currency ?? 'EUR') === 'USD')>USD</option>
                </select>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('admin.variants.index') }}"
                class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button type="submit"
                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-save-line"></i> {{ $editing ? __('Save changes') : __('Create variant') }}
            </button>
        </div>
    </form>
</x-admin-layout>
