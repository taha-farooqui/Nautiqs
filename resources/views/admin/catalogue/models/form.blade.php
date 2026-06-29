@php
    $editing = isset($model->_id) && $model->_id;
    $action  = $editing ? route('admin.models.update', $model->_id) : route('admin.models.store');
@endphp
<x-admin-layout
    :title="$editing ? __('Edit model') : __('Add model')"
    :header="$editing ? $model->name : __('Add model')">

    <div class="mb-4 flex items-center gap-2 text-sm">
        <a href="{{ route('admin.models.index') }}" class="text-gray-500 hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to models') }}
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
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Brand') }} <span class="text-red-500">*</span></label>
            <select name="brand_id" required
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">
                <option value="">{{ __('Select a brand…') }}</option>
                @foreach ($brands as $b)
                    <option value="{{ $b->_id }}" @selected(old('brand_id', $model->brand_id ?? '') === (string) $b->_id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Code') }} <span class="text-red-500">*</span></label>
                <input type="text" name="code" required value="{{ old('code', $model->code ?? '') }}"
                    placeholder="{{ __('e.g. SG250') }}"
                    class="w-full rounded-lg border-gray-300 font-mono text-sm focus:border-primary-800 focus:ring-primary-800" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }} <span class="text-red-500">*</span></label>
                <input type="text" name="name" required value="{{ old('name', $model->name ?? '') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
            </div>
        </div>

        <div class="max-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Default margin (%)') }}</label>
            <input type="number" step="0.1" min="0" max="100" name="default_margin_pct"
                value="{{ old('default_margin_pct', $model->default_margin_pct ?? '') }}"
                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('admin.models.index') }}"
                class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button type="submit"
                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-save-line"></i> {{ $editing ? __('Save changes') : __('Create model') }}
            </button>
        </div>
    </form>
</x-admin-layout>
