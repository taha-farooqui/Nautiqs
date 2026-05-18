@php
    $editing = isset($option->_id) && $option->_id;
    $action  = $editing ? route('admin.options.update', $option->_id) : route('admin.options.store');
@endphp
<x-admin-layout
    :title="$editing ? __('Edit option') : __('Add option')"
    :header="$editing ? $option->label : __('Add option')">

    <div class="mb-4 flex items-center gap-2 text-sm">
        <a href="{{ route('admin.options.index') }}" class="text-gray-500 hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to options') }}
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
                    <option value="{{ $m->_id }}" @selected(old('model_id', $option->model_id ?? '') === (string) $m->_id)>{{ $m->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Category') }} <span class="text-red-500">*</span></label>
                <input type="text" name="category" required value="{{ old('category', $option->category ?? '') }}"
                    placeholder="{{ __('e.g. CC Configuration, Electronics') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Position') }}</label>
                <input type="number" min="0" max="9999" name="position" value="{{ old('position', $option->position ?? 0) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Label') }} <span class="text-red-500">*</span></label>
            <input type="text" name="label" required value="{{ old('label', $option->label ?? '') }}"
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Selling price (excl. VAT)') }} <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="price" required value="{{ old('price', $option->price ?? '') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Cost') }}</label>
                <input type="number" step="0.01" min="0" name="cost" value="{{ old('cost', $option->cost ?? '') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <p class="text-[11px] text-gray-500 mt-1">{{ __('Never shown on client PDFs.') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Currency') }}</label>
                <select name="currency"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                    <option value="EUR" @selected(old('currency', $option->currency ?? 'EUR') === 'EUR')>EUR</option>
                    <option value="USD" @selected(old('currency', $option->currency ?? 'EUR') === 'USD')>USD</option>
                </select>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('admin.options.index') }}"
                class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button type="submit"
                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-save-line"></i> {{ $editing ? __('Save changes') : __('Create option') }}
            </button>
        </div>
    </form>
</x-admin-layout>
